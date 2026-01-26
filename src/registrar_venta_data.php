<?php
require_once "../connection/connection.php";

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
                c.nombre AS cliente_nombre
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
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
                p.nombre AS producto_nombre
            FROM detalle_venta dv
            INNER JOIN productos p ON dv.producto_id = p.id
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
                COALESCE(SUM(dv.cantidad), 0) AS cantidad_total,
                COUNT(dv.id) AS cantidad_items,
                c.nombre AS cliente_nombre
            FROM ventas v
            INNER JOIN clientes c ON v.cliente_id = c.id
            LEFT JOIN detalle_venta dv ON v.id = dv.venta_id
            GROUP BY v.id, v.fecha, v.numero_factura, v.total, v.estado, c.nombre
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
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($v['cliente_nombre']) . '</td>';
                echo '<td>' . date('d/m/Y', strtotime($v['fecha'])) . '</td>';
                echo '<td>' . htmlspecialchars($v['numero_factura'] ?? '-') . '</td>';
                echo '<td>' . number_format($v['cantidad_total'], 2, '.', ',') .'</td>';
                echo '<td>$' . number_format($v['total'], 2, '.', ',') . '</td>';
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
                echo '<button class="btn btn-sm btn-primary" onclick="verDetalle(' . $v['id'] . ')" style="margin-right: 5px;">Ver Detalle</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8" class="text-center">No se encontraron ventas registradas</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    if ($action === 'obtener_precio_producto') {
        $producto_id = $_POST['producto_id'] ?? null;
        
        if (!$producto_id) {
            throw new Exception("ID de producto requerido");
        }
        
        // Asegurar que las tablas existan
        $createInventarioProductos = "
        CREATE TABLE IF NOT EXISTS inventario_productos (
            producto_id INT NOT NULL,
            rango_tallas_id INT NOT NULL,
            tipo_produccion_id INT NOT NULL,
            stock_actual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (producto_id, rango_tallas_id, tipo_produccion_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        $conn->query($createInventarioProductos);
        
        // Obtener el precio_total y datos de la receta más reciente para este producto
        $sql = "
            SELECT precio_total, rango_tallas_id, tipo_produccion_id
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
            $precio_total = floatval($row['precio_total']);
            $rango_tallas_id = intval($row['rango_tallas_id']);
            $tipo_produccion_id = intval($row['tipo_produccion_id']);
            
            // Obtener el stock actual del producto
            $sqlStock = "
                SELECT stock_actual 
                FROM inventario_productos 
                WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?
            ";
            $stmtStock = $conn->prepare($sqlStock);
            $stmtStock->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
            $stmtStock->execute();
            $resultStock = $stmtStock->get_result();
            $stock_actual = 0;
            
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

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("
                INSERT INTO ventas (cliente_id, fecha, numero_factura, total, estado)
                VALUES (?, ?, ?, ?, ?)
            ");

            $numero_factura = empty($numero_factura) ? null : $numero_factura;
            $stmt->bind_param("issds", $cliente_id, $fecha, $numero_factura, $total, $estado);
            $stmt->execute();
            
            $venta_id = $conn->insert_id;
            $stmt->close();

            if (!$venta_id) {
                throw new Exception("Error al crear la venta");
            }

            // Asegurar que las tablas de inventario de productos existan
            $createInventarioProductos = "
            CREATE TABLE IF NOT EXISTS inventario_productos (
                producto_id INT NOT NULL,
                rango_tallas_id INT NOT NULL,
                tipo_produccion_id INT NOT NULL,
                stock_actual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (producto_id, rango_tallas_id, tipo_produccion_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ";
            $conn->query($createInventarioProductos);

            $createMovimientosProductos = "
            CREATE TABLE IF NOT EXISTS movimientos_productos_detalle (
                id INT PRIMARY KEY AUTO_INCREMENT,
                producto_id INT NOT NULL,
                rango_tallas_id INT NOT NULL,
                tipo_produccion_id INT NOT NULL,
                tipo ENUM('entrada', 'salida') NOT NULL,
                cantidad DECIMAL(12,2) NOT NULL,
                observaciones TEXT,
                fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_producto (producto_id, rango_tallas_id, tipo_produccion_id),
                INDEX idx_fecha (fecha_movimiento),
                INDEX idx_tipo (tipo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ";
            $conn->query($createMovimientosProductos);

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

                // Obtener la receta más reciente para este producto
                $sqlReceta = "
                    SELECT rango_tallas_id, tipo_produccion_id 
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
                    $rango_tallas_id = intval($rowReceta['rango_tallas_id']);
                    $tipo_produccion_id = intval($rowReceta['tipo_produccion_id']);
                    
                    // Obtener stock actual
                    $sqlStock = "
                        SELECT stock_actual 
                        FROM inventario_productos 
                        WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?
                    ";
                    $stmtStock = $conn->prepare($sqlStock);
                    $stmtStock->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                    $stmtStock->execute();
                    $resultStock = $stmtStock->get_result();
                    $stockActual = 0;
                    
                    if ($rowStock = $resultStock->fetch_assoc()) {
                        $stockActual = floatval($rowStock['stock_actual']);
                    }
                    $stmtStock->close();
                    
                    // Calcular nuevo stock (restar cantidad vendida)
                    $nuevoStock = $stockActual - $cantidad;
                    if ($nuevoStock < 0) {
                        $nuevoStock = 0;
                    }
                    
                    // Registrar movimiento de salida
                    $stmtMovimiento = $conn->prepare("
                        INSERT INTO movimientos_productos_detalle 
                        (producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, observaciones)
                        VALUES (?, ?, ?, 'salida', ?, ?)
                    ");
                    $observacionesMovimiento = "Salida por venta #{$venta_id}";
                    $stmtMovimiento->bind_param("iiids", $producto_id, $rango_tallas_id, $tipo_produccion_id, $cantidad, $observacionesMovimiento);
                    $stmtMovimiento->execute();
                    $stmtMovimiento->close();
                    
                    // Actualizar inventario
                    $stmtInventario = $conn->prepare("
                        INSERT INTO inventario_productos (producto_id, rango_tallas_id, tipo_produccion_id, stock_actual, ultima_actualizacion)
                        VALUES (?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                            stock_actual = VALUES(stock_actual),
                            ultima_actualizacion = NOW()
                    ");
                    $stmtInventario->bind_param("iiid", $producto_id, $rango_tallas_id, $tipo_produccion_id, $nuevoStock);
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

