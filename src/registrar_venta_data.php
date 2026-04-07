<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';

$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

$data = [];
if (empty($action)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: [];
    $action = $data['action'] ?? '';
}

try {
    if ($action === 'listar_cotizaciones_venta') {
        header('Content-Type: application/json; charset=utf-8');
        $id_cliente_f = (int) ($_GET['id_cliente'] ?? $_POST['id_cliente'] ?? 0);
        if ($id_cliente_f <= 0) {
            echo json_encode(['success' => true, 'cotizaciones' => []]);
            $conn->close();
            exit;
        }
        $sql = "SELECT c.id_cotizacion, c.codigo_cotizacion, c.id_cliente, cl.nombre AS cliente_nombre, c.total,
                DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') AS fecha
                FROM cotizaciones c
                INNER JOIN clientes cl ON cl.id = c.id_cliente
                WHERE c.id_cliente = ?
                AND c.status != 3
                AND NOT EXISTS (
                    SELECT 1 FROM ventas v
                    WHERE v.cotizacion_id = c.id_cotizacion
                    AND v.tasa_cambiaria_id IS NOT NULL
                )
                ORDER BY c.id_cotizacion DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_cliente_f);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }
        $stmt->close();
        echo json_encode(['success' => true, 'cotizaciones' => $rows]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_items_cotizacion') {
        header('Content-Type: application/json; charset=utf-8');
        $cotizacion_id = (int) ($_GET['cotizacion_id'] ?? 0);
        if ($cotizacion_id <= 0) {
            throw new Exception('Cotización no válida');
        }

        $chk = $conn->prepare(
            'SELECT id_cliente FROM cotizaciones WHERE id_cotizacion = ? AND status != 3'
        );
        $chk->bind_param('i', $cotizacion_id);
        $chk->execute();
        $cr = $chk->get_result();
        if (!$cr || $cr->num_rows === 0) {
            $chk->close();
            throw new Exception('Cotización no encontrada');
        }
        $cinfo = $cr->fetch_assoc();
        $chk->close();

        $id_cliente_req = (int) ($_GET['id_cliente'] ?? 0);
        if ($id_cliente_req > 0 && (int) $cinfo['id_cliente'] !== $id_cliente_req) {
            throw new Exception('La cotización no corresponde al cliente seleccionado');
        }

        $dup = $conn->prepare(
            'SELECT 1 FROM ventas WHERE cotizacion_id = ? AND tasa_cambiaria_id IS NOT NULL LIMIT 1'
        );
        $dup->bind_param('i', $cotizacion_id);
        $dup->execute();
        if ($dup->get_result()->num_rows > 0) {
            $dup->close();
            throw new Exception('Esta cotización ya tiene una venta facturada');
        }
        $dup->close();

        $sql = "SELECT cd.id_receta, cd.cantidad, cd.precio_unitario, cd.subtotal,
                r.producto_id AS catalogo_producto_id,
                p.nombre AS producto_nombre
                FROM cotizacion_detalles cd
                INNER JOIN recetas r ON r.id = cd.id_receta
                INNER JOIN productos p ON p.id = r.producto_id
                WHERE cd.id_cotizacion = ?
                ORDER BY cd.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $cotizacion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $productos = [];
        while ($row = $result->fetch_assoc()) {
            $productos[] = [
                'producto_id' => (int) $row['catalogo_producto_id'],
                'producto_nombre' => $row['producto_nombre'],
                'cantidad' => (float) $row['cantidad'],
                'precio_unitario' => (float) $row['precio_unitario'],
                'subtotal' => (float) $row['subtotal'],
            ];
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'id_cliente' => (int) $cinfo['id_cliente'],
            'productos' => $productos,
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_detalle') {
        $venta_id = $_POST['venta_id'] ?? null;
        
        if (!$venta_id) {
            throw new Exception("ID de venta requerido");
        }
        
        $sqlVenta = "
            SELECT 
                v.id,
                v.fecha,
                v.numero_factura,
                v.total,
                v.tasa_cambiaria_id,
                tc.tasa AS tasa_venta,
                c.nombre AS cliente_nombre,
                cot.codigo_cotizacion
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = v.tasa_cambiaria_id
            LEFT JOIN cotizaciones cot ON cot.id_cotizacion = v.cotizacion_id
            WHERE v.id = ?
        ";
        
        $stmt = $conn->prepare($sqlVenta);
        $stmt->bind_param("i", $venta_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Venta no encontrada");
        }
        
        $venta = $result->fetch_assoc();
        $stmt->close();
        
        $sqlDetalles = "
            SELECT 
                dv.id,  
                dv.cantidad,
                dv.precio_unitario,
                dv.subtotal,
                p.nombre AS producto_nombre,
                rt.nombre_rango AS talla_nombre
            FROM detalle_venta dv
            INNER JOIN recetas r ON dv.producto_id = r.id
            INNER JOIN productos p ON r.producto_id = p.id
            INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
            WHERE dv.venta_id = ?
            ORDER BY p.nombre ASC
        ";

        $stmt = $conn->prepare($sqlDetalles);
        $stmt->bind_param("i", $venta_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $detalles = [];
        while ($row = $result->fetch_assoc()) {
            $detalles[] = $row;
        }
        $stmt->close();
        
        $venta['fecha_formateada'] = date('d/m/Y', strtotime($venta['fecha']));
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'venta' => $venta,
            'detalles' => $detalles
        ]);
        $conn->close();
        exit;
    }
    
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                v.id,
                v.fecha,
                v.numero_factura,
                v.total,
                v.tasa_cambiaria_id,
                v.cotizacion_id,
                tc.tasa AS tasa_venta,
                COALESCE(SUM(dv.cantidad), 0) AS cantidad_total,
                COUNT(dv.id) AS cantidad_items,
                c.nombre AS cliente_nombre,
                cot.codigo_cotizacion
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = v.tasa_cambiaria_id
            LEFT JOIN cotizaciones cot ON cot.id_cotizacion = v.cotizacion_id
            LEFT JOIN detalle_venta dv ON v.id = dv.venta_id
            WHERE NOT (
                v.cotizacion_id IS NOT NULL
                AND COALESCE(v.estado, 'pendiente') = 'pendiente'
                AND v.tasa_cambiaria_id IS NULL
            )
            GROUP BY v.id, v.fecha, v.numero_factura, v.total, v.tasa_cambiaria_id, v.cotizacion_id, tc.tasa, c.nombre, cot.codigo_cotizacion
            ORDER BY v.creado_en DESC, v.fecha DESC
        ";

        $result = $conn->query($sql);
        $ventas = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ventas[] = $row;
            }
        }
        $i = 0;
        if (!empty($ventas)) {
            foreach ($ventas as $v) {
                $i++;
                $totalBs = null;
                if (!empty($v['tasa_venta']) && (float)$v['tasa_venta'] > 0) {
                    $totalBs = (float)$v['total'] * (float)$v['tasa_venta'];
                }
                $totalBsTexto = $totalBs !== null ? 'Bs. ' . number_format($totalBs, 2, '.', ',') : '—';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($v['cliente_nombre']) . '</td>';
                echo '<td>' . date('d/m/Y', strtotime($v['fecha'])) . '</td>';
                echo '<td>' . htmlspecialchars($v['numero_factura'] ?? '-') . '</td>';
                $codCot = $v['codigo_cotizacion'] ?? '';
                echo '<td>' . ($codCot !== '' && $codCot !== null ? htmlspecialchars($codCot) : '<span class="text-muted">—</span>') . '</td>';
                echo '<td>' . number_format($v['cantidad_total'], 2, '.', ',') .'</td>';
                echo '<td>$' . number_format($v['total'], 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($totalBsTexto) . '</td>';
                echo '<td style="white-space: nowrap;">';
                echo '<a href="../formatos/ver_factura.php?id=' . $v['id'] . '" target="_blank" class="btn btn-sm btn-info"><i class="fas fa-print"></i>Ver Impresión</a>';
                echo '<button style = "margin-left: 5px;" class="btn btn-sm btn-primary" onclick="verDetalle(' . $v['id'] . ')" style="margin-right: 5px;">Ver Detalle</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="9" class="text-center">No se encontraron ventas registradas</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    if ($action === 'obtener_siguiente_numero_factura') {
        $siguiente = '1';
        $res = $conn->query("SELECT numero_factura FROM ventas ORDER BY id DESC LIMIT 1");
        if ($res && ($row = $res->fetch_assoc()) && !empty($row['numero_factura'])) {
            $ultimo = trim($row['numero_factura']);
            if (ctype_digit($ultimo)) {
                $siguiente = (string)((int)$ultimo + 1);
            } elseif (preg_match('/(\d+)\s*$/', $ultimo, $m)) {
                $siguiente = (string)((int)$m[1] + 1);
            } elseif (preg_match('/^(\d+)/', $ultimo, $m)) {
                $siguiente = (string)((int)$m[1] + 1);
            }
        }
        echo json_encode(['success' => true, 'siguiente_numero_factura' => $siguiente]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_precio_producto') {
        $producto_id = $_POST['producto_id'] ?? null;
        
        if (!$producto_id) {
            throw new Exception("ID de producto requerido");
        }
        
        // Obtener el precio_total y datos de la receta más reciente para este producto
        $sql = "
            SELECT id, precio_total, rango_tallas_id, tipo_produccion_id
            FROM recetas 
            WHERE producto_id = ? AND precio_total > 0
            ORDER BY creado_en DESC 
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $producto_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $receta_id = intval($row['id']);
            $precio_total = floatval($row['precio_total']);
            $rango_tallas_id = intval($row['rango_tallas_id']);
            $tipo_produccion_id = intval($row['tipo_produccion_id']);
            
            // Obtener el stock actual del producto (inventario unificado o inventario_productos)
            $stock_actual = 0;
            if ($tieneInventarioNuevo) {
                $sqlStock = "SELECT stock_actual FROM inventario WHERE tipo_item = 'producto' AND tipo_item_id = ?";
                $stmtStock = $conn->prepare($sqlStock);
                $stmtStock->bind_param("i", $receta_id);
            } else {
                $sqlStock = "SELECT stock_actual FROM inventario_productos WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?";
                $stmtStock = $conn->prepare($sqlStock);
                $stmtStock->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
            }
            $stmtStock->execute();
            $resultStock = $stmtStock->get_result();
            if ($rowStock = $resultStock->fetch_assoc()) {
                $stock_actual = floatval($rowStock['stock_actual']);
            }
            $stmtStock->close();
            
            echo json_encode([
                'success' => true,
                'precio_total' => $precio_total,
                'stock_actual' => $stock_actual
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró una receta con precio para este producto'
            ]);
        }
        
        $stmt->close();
        $conn->close();
        exit;
    }

    if ($action === 'crear') {
        if (empty($data) || !isset($data['cliente_id'])) {
            $raw = file_get_contents('php://input');
            $data = json_decode($raw, true) ?: [];
        }
        $cliente_id = $data['cliente_id'] ?? null;
        $fecha = $data['fecha'] ?? null;
        $numero_factura = trim($data['numero_factura'] ?? '');
        $productos = $data['productos'] ?? [];
        $cotizacion_id = !empty($data['cotizacion_id']) ? (int) $data['cotizacion_id'] : null;

        if (!$cliente_id) {
            throw new Exception("El cliente es obligatorio");
        }

        if (!$fecha) {
            throw new Exception("La fecha es obligatoria");
        }

        if (empty($productos) || !is_array($productos)) {
            throw new Exception("Debe agregar al menos un producto a la venta");
        }

        $idsLinea = [];
        foreach ($productos as $producto) {
            $pid = (int) ($producto['producto_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            if (isset($idsLinea[$pid])) {
                throw new Exception('No puede repetir el mismo artículo en la venta. Use una sola línea y ajuste la cantidad.');
            }
            $idsLinea[$pid] = true;
        }

        if ($cotizacion_id) {
            $stc = $conn->prepare('SELECT id_cliente FROM cotizaciones WHERE id_cotizacion = ? AND status != 3');
            $stc->bind_param('i', $cotizacion_id);
            $stc->execute();
            $rc = $stc->get_result();
            if (!$rc || $rc->num_rows === 0) {
                $stc->close();
                throw new Exception('La cotización no existe o no está disponible');
            }
            $crow = $rc->fetch_assoc();
            $stc->close();
            if ((int) $cliente_id !== (int) $crow['id_cliente']) {
                throw new Exception('El cliente debe ser el mismo que el de la cotización seleccionada');
            }
            $std = $conn->prepare(
                'SELECT 1 FROM ventas WHERE cotizacion_id = ? AND tasa_cambiaria_id IS NOT NULL LIMIT 1'
            );
            $std->bind_param('i', $cotizacion_id);
            $std->execute();
            if ($std->get_result()->num_rows > 0) {
                $std->close();
                throw new Exception('Esta cotización ya tiene una venta facturada');
            }
            $std->close();
        }

        $total = 0;
        foreach ($productos as $producto) {
            $cantidad = floatval($producto['cantidad'] ?? 0);
            $precio_unitario = floatval($producto['precio_unitario'] ?? 0);
            $total += $cantidad * $precio_unitario;
        }

        $tasa_cambiaria_id = null;
        $rt = $conn->query("SELECT id FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
        if ($rt && $row_tasa = $rt->fetch_assoc()) {
            $tasa_cambiaria_id = (int) $row_tasa['id'];
        }

        $conn->begin_transaction();

        try {
            $numero_factura = empty($numero_factura) ? null : $numero_factura;

            if ($cotizacion_id) {
                $colTasa = $conn->query("SHOW COLUMNS FROM ventas LIKE 'tasa_cambiaria_id'");
                if ($colTasa && $colTasa->num_rows > 0) {
                    $delPh = $conn->prepare(
                        'DELETE FROM ventas WHERE cotizacion_id = ? AND tasa_cambiaria_id IS NULL'
                    );
                    $delPh->bind_param('i', $cotizacion_id);
                    $delPh->execute();
                    $delPh->close();
                }
            }

            if ($cotizacion_id) {
                $stmt = $conn->prepare("
                    INSERT INTO ventas (cliente_id, fecha, numero_factura, total, tasa_cambiaria_id, cotizacion_id, estado)
                    VALUES (?, ?, ?, ?, ?, ?, 'entregado')
                ");
                $stmt->bind_param("issdii", $cliente_id, $fecha, $numero_factura, $total, $tasa_cambiaria_id, $cotizacion_id);
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO ventas (cliente_id, fecha, numero_factura, total, tasa_cambiaria_id, estado)
                    VALUES (?, ?, ?, ?, ?, 'entregado')
                ");
                $stmt->bind_param("issdi", $cliente_id, $fecha, $numero_factura, $total, $tasa_cambiaria_id);
            }

            $stmt->execute();

            $venta_id = $conn->insert_id;
            $stmt->close();

            if (!$venta_id) {
                throw new Exception("Error al crear la venta");
            }

            $stmtDetalle = $conn->prepare("
                INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($productos as $producto) {
                $producto_id = intval($producto['producto_id'] ?? 0);
                $cantidad = floatval($producto['cantidad'] ?? 0);
                $precio_unitario = floatval($producto['precio_unitario'] ?? 0);

                if ($producto_id <= 0 || $cantidad <= 0 || $precio_unitario <= 0) {
                    throw new Exception("Datos inválidos en el detalle de la venta");
                }

                $stmtDetalle->bind_param("iidd", $venta_id, $producto_id, $cantidad, $precio_unitario);
                $stmtDetalle->execute();

                // Obtener la receta más reciente para este producto (y su id para inventario unificado)
                $sqlReceta = "
                    SELECT id, rango_tallas_id, tipo_produccion_id 
                    FROM recetas 
                    WHERE producto_id = ? 
                    ORDER BY creado_en DESC 
                    LIMIT 1
                ";
                $stmtReceta = $conn->prepare($sqlReceta);
                $stmtReceta->bind_param("i", $producto_id);
                $stmtReceta->execute();
                $resultReceta = $stmtReceta->get_result();
                
                if ($rowReceta = $resultReceta->fetch_assoc()) {
                    $receta_id = intval($rowReceta['id']);
                    $rango_tallas_id = intval($rowReceta['rango_tallas_id']);
                    $tipo_produccion_id = intval($rowReceta['tipo_produccion_id']);
                    
                    if ($tieneInventarioNuevo) {
                        $sqlStock = "SELECT stock_actual FROM inventario WHERE tipo_item = 'producto' AND tipo_item_id = ?";
                        $stmtStock = $conn->prepare($sqlStock);
                        $stmtStock->bind_param("i", $receta_id);
                    } else {
                        $sqlStock = "SELECT stock_actual FROM inventario_productos WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?";
                        $stmtStock = $conn->prepare($sqlStock);
                        $stmtStock->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                    }
                    $stmtStock->execute();
                    $resultStock = $stmtStock->get_result();
                    $stockActual = 0;
                    if ($rowStock = $resultStock->fetch_assoc()) {
                        $stockActual = floatval($rowStock['stock_actual']);
                    }
                    $stmtStock->close();
                    $nuevoStock = $stockActual - $cantidad;
                    if ($nuevoStock < 0) $nuevoStock = 0;
                    
                    $observacionesMovimiento = "Salida por venta #{$venta_id}";
                    $tieneRecetaIdDet = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'receta_id'")->num_rows > 0;
                    if ($tieneRecetaIdDet) {
                        $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, receta_id, tipo, cantidad, observaciones) VALUES ('producto', ?, 'salida', ?, ?)");
                        $stmtMovimiento->bind_param("ids", $receta_id, $cantidad, $observacionesMovimiento);
                    } else {
                        $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, observaciones) VALUES ('producto', ?, ?, ?, 'salida', ?, ?)");
                        $stmtMovimiento->bind_param("iiids", $producto_id, $rango_tallas_id, $tipo_produccion_id, $cantidad, $observacionesMovimiento);
                    }
                    $stmtMovimiento->execute();
                    $stmtMovimiento->close();
                    
                    if ($tieneInventarioNuevo) {
                        $stmtInventario = $conn->prepare("
                            INSERT INTO inventario (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion)
                            VALUES ('producto', ?, ?, 'manual', NOW())
                            ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()
                        ");
                        $stmtInventario->bind_param("id", $receta_id, $nuevoStock);
                    } else {
                        $stmtInventario = $conn->prepare("
                            INSERT INTO inventario_productos (producto_id, rango_tallas_id, tipo_produccion_id, stock_actual, ultima_actualizacion)
                            VALUES (?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()
                        ");
                        $stmtInventario->bind_param("iiid", $producto_id, $rango_tallas_id, $tipo_produccion_id, $nuevoStock);
                    }
                    $stmtInventario->execute();
                    $stmtInventario->close();
                }
                $stmtReceta->close();
            }

            $stmtDetalle->close();

            if ($cotizacion_id) {
                $statusAprobada = 2;
                $stUpCot = $conn->prepare('UPDATE cotizaciones SET status = ? WHERE id_cotizacion = ?');
                $stUpCot->bind_param('ii', $statusAprobada, $cotizacion_id);
                if (!$stUpCot->execute()) {
                    $stUpCot->close();
                    throw new Exception('No se pudo marcar la cotización como aprobada');
                }
                $stUpCot->close();
            }

            $conn->commit();

            $facturaTxt = $numero_factura !== null && $numero_factura !== '' ? $numero_factura : 'sin número';
            $cotTxt = $cotizacion_id ? " Cotización #{$cotizacion_id}." : '';
            Auditoria::registrar(
                $conn,
                "Venta #{$venta_id} registrada. Cliente #{$cliente_id}. Factura: {$facturaTxt}. Total: {$total}.{$cotTxt}",
                'Ventas'
            );

            echo json_encode([
                'success' => true, 
                'message' => 'Venta registrada exitosamente',
                'venta_id' => $venta_id
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } else {
        throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="9" class="text-center text-danger">Error al cargar ventas</td></tr>';
    }
}

$conn->close();
?>

