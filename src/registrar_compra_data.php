<?php
require_once "../connection/connection.php";

$checkColumn = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_movimiento'");
if ($checkColumn->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE inventario 
                     ADD COLUMN tipo_movimiento ENUM('compra', 'orden_produccion', 'manual', 'ajuste') 
                     DEFAULT 'manual' 
                     AFTER stock_actual");
        $conn->query("ALTER TABLE inventario 
                     ADD COLUMN referencia_id INT NULL 
                     AFTER tipo_movimiento");
        $conn->query("ALTER TABLE inventario 
                     ADD INDEX idx_tipo_movimiento (tipo_movimiento)");
        $conn->query("UPDATE inventario SET tipo_movimiento = 'manual' WHERE tipo_movimiento IS NULL");
    } catch (Exception $e) {
    }
}

$action = $_POST['action'] ?? '';

if (empty($action)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? '';
}

try {
    if ($action === 'obtener_insumos_proveedor') {
        $proveedor_id = $_POST['proveedor_id'] ?? null;
        
        if (!$proveedor_id) {
            throw new Exception("ID de proveedor requerido");
        }
        
        $sql = "SELECT id, nombre, costo_unitario, unidad_medida 
                FROM insumos 
                WHERE proveedor_id = ? 
                ORDER BY nombre ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $proveedor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $insumos = [];
        while ($row = $result->fetch_assoc()) {
            $insumos[] = $row;
        }
        
        $stmt->close();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'insumos' => $insumos
        ]);
        $conn->close();
        exit;
    }
    
    if ($action === 'obtener_detalle') {
        $compra_id = $_POST['compra_id'] ?? null;
        
        if (!$compra_id) {
            throw new Exception("ID de compra requerido");
        }
        
        $sqlCompra = "
            SELECT 
                c.id,
                c.fecha,
                c.numero_factura,
                c.total,
                c.estado,
                p.nombre AS proveedor_nombre
            FROM compras c
            INNER JOIN proveedores p ON c.proveedor_id = p.id
            WHERE c.id = ?
        ";
        
        $stmt = $conn->prepare($sqlCompra);
        $stmt->bind_param("i", $compra_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Compra no encontrada");
        }
        
        $compra = $result->fetch_assoc();
        $stmt->close();
        
        $sqlDetalles = "
            SELECT 
                dc.id,  
                dc.cantidad,
                dc.costo_unitario,
                dc.subtotal,
                i.nombre AS insumo_nombre,
                i.unidad_medida
            FROM detalle_compra dc
            INNER JOIN insumos i ON dc.insumo_id = i.id
            WHERE dc.compra_id = ?
            ORDER BY i.nombre ASC
        ";
        
        $stmt = $conn->prepare($sqlDetalles);
        $stmt->bind_param("i", $compra_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $detalles = [];
        while ($row = $result->fetch_assoc()) {
            $detalles[] = $row;
        }
        $stmt->close();
        
        $compra['fecha_formateada'] = date('d/m/Y', strtotime($compra['fecha']));
        
        $estadoBadge = '';
        switch($compra['estado']) {
            case 'pendiente':
                $estadoBadge = '<span style="color: orange; font-weight: bold;">Pendiente</span>';
                break;
            case 'recibido':
                $estadoBadge = '<span style="color: green; font-weight: bold;">Recibido</span>';
                break;
            case 'cancelado':
                $estadoBadge = '<span style="color: red; font-weight: bold;">Cancelado</span>';
                break;
            default:
                $estadoBadge = htmlspecialchars($compra['estado']);
        }
        $compra['estado_badge'] = $estadoBadge;
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'compra' => $compra,
            'detalles' => $detalles
        ]);
        $conn->close();
        exit;
    }
    
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                c.id,
                c.fecha,
                c.numero_factura,
                c.total,
                c.estado,
                COALESCE(SUM(dc.cantidad), 0) AS cantidad_total,
                COUNT(dc.id) AS cantidad_items,
                p.nombre AS proveedor_nombre
            FROM compras c
            INNER JOIN proveedores p ON c.proveedor_id = p.id
            LEFT JOIN detalle_compra dc ON c.id = dc.compra_id
            GROUP BY c.id, c.fecha, c.numero_factura, c.total, c.estado, p.nombre
            ORDER BY c.creado_en DESC, c.fecha DESC
        ";

        $result = $conn->query($sql);
        $compras = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $compras[] = $row;
            }
        }
        $i = 0;
        if (!empty($compras)) {
            foreach ($compras as $c) {
                $i++;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($c['proveedor_nombre']) . '</td>';
                echo '<td>' . date('d/m/Y', strtotime($c['fecha'])) . '</td>';
                echo '<td>' . htmlspecialchars($c['numero_factura'] ?? '-') . '</td>';
                echo '<td>' . number_format($c['cantidad_total'], 2, '.', ',') .'</td>';
                echo '<td>$' . number_format($c['total'], 2, '.', ',') . '</td>';
                $estadoBadge = '';
                switch($c['estado']) {
                    case 'pendiente':
                        $estadoBadge = '<span style="color: orange; font-weight: bold;">Pendiente</span>';
                        break;
                    case 'recibido':
                        $estadoBadge = '<span style="color: green; font-weight: bold;">Recibido</span>';
                        break;
                    case 'cancelado':
                        $estadoBadge = '<span style="color: red; font-weight: bold;">Cancelado</span>';
                        break;
                    default:
                        $estadoBadge = htmlspecialchars($c['estado']);
                }
                echo '<td>' . $estadoBadge . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="verDetalle(' . $c['id'] . ')">Ver Detalle</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7" class="text-center">No se encontraron compras registradas</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    if ($action === 'crear') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        $proveedor_id = $data['proveedor_id'] ?? null;
        $fecha = $data['fecha'] ?? null;
        $numero_factura = trim($data['numero_factura'] ?? '');
        $estado = $data['estado'] ?? 'pendiente';
        $insumos = $data['insumos'] ?? [];

        if (!$proveedor_id) {
            throw new Exception("El proveedor es obligatorio");
        }

        if (!$fecha) {
            throw new Exception("La fecha es obligatoria");
        }

        if (empty($insumos) || !is_array($insumos)) {
            throw new Exception("Debe agregar al menos un insumo a la compra");
        }

        $total = 0;
        foreach ($insumos as $insumo) {
            $cantidad = floatval($insumo['cantidad'] ?? 0);
            $costo_unitario = floatval($insumo['costo_unitario'] ?? 0);
            $total += $cantidad * $costo_unitario;
        }

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                INSERT INTO compras (proveedor_id, fecha, numero_factura, total, estado)
                VALUES (?, ?, ?, ?, ?)
            ");

            $numero_factura = empty($numero_factura) ? null : $numero_factura;
            $stmt->bind_param("issds", $proveedor_id, $fecha, $numero_factura, $total, $estado);
            $stmt->execute();
            
            $compra_id = $conn->insert_id;
            $stmt->close();

            if (!$compra_id) {
                throw new Exception("Error al crear la compra");
            }

            $stmtDetalle = $conn->prepare("
                INSERT INTO detalle_compra (compra_id, insumo_id, cantidad, costo_unitario)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($insumos as $insumo) {
                $insumo_id = intval($insumo['insumo_id'] ?? 0);
                $cantidad = floatval($insumo['cantidad'] ?? 0);
                $costo_unitario = floatval($insumo['costo_unitario'] ?? 0);

                if ($insumo_id <= 0 || $cantidad <= 0 || $costo_unitario <= 0) {
                    throw new Exception("Datos inválidos en el detalle de la compra");
                }

                $stmtDetalle->bind_param("iidd", $compra_id, $insumo_id, $cantidad, $costo_unitario);
                $stmtDetalle->execute();

                $sqlStock = "SELECT stock_actual FROM inventario WHERE insumo_id = ?";
                $stmtStock = $conn->prepare($sqlStock);
                $stmtStock->bind_param("i", $insumo_id);
                $stmtStock->execute();
                $resultStock = $stmtStock->get_result();
                $stockActual = 0;
                
                if ($rowStock = $resultStock->fetch_assoc()) {
                    $stockActual = floatval($rowStock['stock_actual']);
                }
                $stmtStock->close();

                $nuevoStock = $stockActual + $cantidad;

                $checkColumn = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_movimiento'");
                if ($checkColumn->num_rows > 0) {
                    $stmtInventario = $conn->prepare("
                        INSERT INTO inventario (insumo_id, stock_actual, tipo_movimiento, referencia_id, ultima_actualizacion)
                        VALUES (?, ?, 'compra', ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                            stock_actual = VALUES(stock_actual),
                            tipo_movimiento = VALUES(tipo_movimiento),
                            referencia_id = VALUES(referencia_id),
                            ultima_actualizacion = NOW()
                    ");
                    $stmtInventario->bind_param("idi", $insumo_id, $nuevoStock, $compra_id);
                } else {
                    $stmtInventario = $conn->prepare("
                        INSERT INTO inventario (insumo_id, stock_actual, ultima_actualizacion)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                            stock_actual = VALUES(stock_actual),
                            ultima_actualizacion = NOW()
                    ");
                    $stmtInventario->bind_param("id", $insumo_id, $nuevoStock);
                }
                $stmtInventario->execute();
                $stmtInventario->close();
            }

            $stmtDetalle->close();

            $conn->commit();

            echo json_encode([
                'success' => true, 
                'message' => 'Compra registrada exitosamente',
                'compra_id' => $compra_id
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
        echo '<tr><td colspan="7" class="text-center text-danger">Error al cargar compras</td></tr>';
    }
}

$conn->close();
?>

