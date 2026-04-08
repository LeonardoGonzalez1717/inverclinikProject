<?php
require_once "../connection/connection.php";

$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

$action = $_POST['action'] ?? '';

if (empty($action)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? '';
}

try {
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
                v.estado,
                v.tasa_cambiaria_id,
                tc.tasa AS tasa_venta,
                c.nombre AS cliente_nombre
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = v.tasa_cambiaria_id
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
        
        $estadoBadge = '';
        switch($venta['estado']) {
            case 'pendiente':
                $estadoBadge = '<span style="color: orange; font-weight: bold;">Pendiente</span>';
                break;
            case 'entregado':
                $estadoBadge = '<span style="color: green; font-weight: bold;">Entregado</span>';
                break;
            case 'cancelado':
                $estadoBadge = '<span style="color: red; font-weight: bold;">Cancelado</span>';
                break;
            default:
                $estadoBadge = htmlspecialchars($venta['estado']);
        }
        $venta['estado_badge'] = $estadoBadge;
        
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
                v.estado,
                v.tasa_cambiaria_id,
                tc.tasa AS tasa_venta,
                COALESCE(SUM(dv.cantidad), 0) AS cantidad_total,
                COUNT(dv.id) AS cantidad_items,
                c.nombre AS cliente_nombre
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = v.tasa_cambiaria_id
            LEFT JOIN detalle_venta dv ON v.id = dv.venta_id
            GROUP BY v.id, v.fecha, v.numero_factura, v.total, v.estado, v.tasa_cambiaria_id, tc.tasa, c.nombre
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
                echo '<td>' . number_format($v['cantidad_total'], 2, '.', ',') .'</td>';
                echo '<td>$' . number_format($v['total'], 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($totalBsTexto) . '</td>';
                $estadoBadge = '';
                switch($v['estado']) {
                    case 'pendiente':
                        $estadoBadge = '<span style="color: orange; font-weight: bold;">Pendiente</span>';
                        break;
                    case 'entregado':
                        $estadoBadge = '<span style="color: green; font-weight: bold;">Entregado</span>';
                        break;
                    case 'cancelado':
                        $estadoBadge = '<span style="color: red; font-weight: bold;">Cancelado</span>';
                        break;
                    default:
                        $estadoBadge = htmlspecialchars($v['estado']);
                }
                echo '<td>' . $estadoBadge . '</td>';
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

    restringirEscritura();

    header('Content-Type: application/json');

    if ($action === 'obtener_siguiente_numero_factura') {
        $siguiente = '1';
        $res = $conn->query("SELECT numero_factura FROM ventas ORDER BY id DESC LIMIT 1");
        if ($res && $row = $res->fetch_assoc() && !empty($row['numero_factura'])) {
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

    if ($action === 'actualizar_estatus') {
        $venta_id = $_POST['venta_id'] ?? null;
        $nuevo_estado = $_POST['estado'] ?? null;

        if (!$venta_id || !$nuevo_estado) {
            echo json_encode(['success' => false, 'mensaje' => 'Datos insuficientes']);
            exit;
        }

        $conn->begin_transaction();

        try {
            $stmt_ref = $conn->prepare("SELECT cotizacion_id FROM ventas WHERE id = ?");
            $stmt_ref->bind_param("i", $venta_id);
            $stmt_ref->execute();
            $res = $stmt_ref->get_result();
            $fila = $res->fetch_assoc();
            
            $id_cotizacion = $fila['cotizacion_id'] ?? null;

            $stmt_vta = $conn->prepare("UPDATE ventas SET estado = ? WHERE id = ?");
            $stmt_vta->bind_param("si", $nuevo_estado, $venta_id);
            
            if (!$stmt_vta->execute()) {
                throw new Exception("Error al actualizar la venta.");
            }

            if ($id_cotizacion) {
                $stmt_cot = $conn->prepare("UPDATE cotizaciones SET status = 2 WHERE id_cotizacion = ?");
                $stmt_cot->bind_param("i", $id_cotizacion);
                
                if (!$stmt_cot->execute()) {
                    throw new Exception("Error al actualizar la cotización vinculada.");
                }
            }

            $query_det = "SELECT producto_id, cantidad FROM detalle_venta WHERE venta_id = ?";
            $stmt_det = $conn->prepare($query_det);
            $stmt_det->bind_param("i", $venta_id);
            $stmt_det->execute();
            $res_det = $stmt_det->get_result();

            while ($item = $res_det->fetch_assoc()) {
                $prod_id = $item['producto_id'];
                $cant_vta = $item['cantidad'];

                $sql_rec = "SELECT id FROM recetas WHERE producto_id = ? ORDER BY creado_en DESC LIMIT 1";
                $stmt_rec = $conn->prepare($sql_rec);
                $stmt_rec->bind_param("i", $prod_id);
                $stmt_rec->execute();
                $res_rec = $stmt_rec->get_result();
                
                if ($row_rec = $res_rec->fetch_assoc()) {
                    $receta_id = $row_rec['id'];

                    $upd_inv = $conn->prepare("
                        UPDATE inventario 
                        SET stock_actual = GREATEST(stock_actual - ?, 0), 
                            ultima_actualizacion = NOW() 
                        WHERE tipo_item = 'producto' AND tipo_item_id = ?
                    ");
                    $upd_inv->bind_param("di", $cant_vta, $receta_id);
                    $upd_inv->execute();

                    $obs = "Salida por Venta Aprobada #{$venta_id}";
                    $ins_mov = $conn->prepare("
                        INSERT INTO inventario_detalle (tipo_item, receta_id, tipo, cantidad, observaciones) 
                        VALUES ('producto', ?, 'salida', ?, ?)
                    ");
                    $ins_mov->bind_param("ids", $receta_id, $cant_vta, $obs);
                    $ins_mov->execute();
                }
            }

            $conn->commit();
            echo json_encode(['success' => true]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'crear') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $cliente_id = $data['cliente_id'] ?? null;
        $fecha = $data['fecha'] ?? null;
        $numero_factura = trim($data['numero_factura'] ?? '');
        $estado = $data['estado'] ?? 'pendiente';
        $productos = $data['productos'] ?? [];

        if (!$cliente_id) {
            throw new Exception("El cliente es obligatorio");
        }

        if (!$fecha) {
            throw new Exception("La fecha es obligatoria");
        }

        if (empty($productos) || !is_array($productos)) {
            throw new Exception("Debe agregar al menos un producto a la venta");
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
            $stmt = $conn->prepare("
                INSERT INTO ventas (cliente_id, fecha, numero_factura, total, estado, tasa_cambiaria_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $numero_factura = empty($numero_factura) ? null : $numero_factura;
            $stmt->bind_param("issdsi", $cliente_id, $fecha, $numero_factura, $total, $estado, $tasa_cambiaria_id);
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

            $conn->commit();

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
        echo '<tr><td colspan="8" class="text-center text-danger">Error al cargar ventas</td></tr>';
    }
}

$conn->close();
?>

