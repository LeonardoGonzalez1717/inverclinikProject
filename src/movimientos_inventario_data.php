<?php
require_once "../connection/connection.php";

// Crear tabla de movimientos si no existe
$createTableSQL = "
CREATE TABLE IF NOT EXISTS movimientos_inventario_detalle (
    id INT PRIMARY KEY AUTO_INCREMENT,
    insumo_id INT NOT NULL,
    tipo ENUM('entrada', 'salida') NOT NULL,
    cantidad DECIMAL(12,2) NOT NULL,
    origen VARCHAR(50) DEFAULT 'manual',
    observaciones TEXT,
    orden_produccion_id INT NULL,
    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_insumo_id (insumo_id),
    INDEX idx_fecha (fecha_movimiento),
    INDEX idx_tipo (tipo),
    INDEX idx_orden_produccion (orden_produccion_id),
    FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE CASCADE,
    FOREIGN KEY (orden_produccion_id) REFERENCES ordenes_produccion(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";

$conn->query($createTableSQL);

$checkColumn = $conn->query("SHOW COLUMNS FROM movimientos_inventario_detalle LIKE 'orden_produccion_id'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE movimientos_inventario_detalle ADD COLUMN orden_produccion_id INT NULL");
    $conn->query("ALTER TABLE movimientos_inventario_detalle ADD INDEX idx_orden_produccion (orden_produccion_id)");
    try {
        $conn->query("ALTER TABLE movimientos_inventario_detalle 
                      ADD CONSTRAINT fk_movimientos_orden_produccion 
                      FOREIGN KEY (orden_produccion_id) REFERENCES ordenes_produccion(id) ON DELETE SET NULL");
    } catch (Exception $e) {
    }
}

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

$createRecetasUnicas = "
CREATE TABLE IF NOT EXISTS recetas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    rango_tallas_id INT NOT NULL,
    tipo_produccion_id INT NOT NULL,
    observaciones TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_receta (producto_id, rango_tallas_id, tipo_produccion_id),
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE,
    FOREIGN KEY (rango_tallas_id) REFERENCES rangos_tallas(id) ON DELETE CASCADE,
    FOREIGN KEY (tipo_produccion_id) REFERENCES tipos_produccion(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";
$conn->query($createRecetasUnicas);

$checkRecetas = $conn->query("SELECT COUNT(*) as count FROM recetas");
$row = $checkRecetas->fetch_assoc();
if ($row['count'] == 0) {
    $sqlInsertRecetas = "
        INSERT IGNORE INTO recetas (producto_id, rango_tallas_id, tipo_produccion_id, observaciones)
        SELECT DISTINCT producto_id, rango_tallas_id, tipo_produccion_id, NULL
        FROM recetas_productos
    ";
    $conn->query($sqlInsertRecetas);
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $tipoInventario = $_POST['tipo_inventario'] ?? 'materia_prima';
        
        if ($tipoInventario === 'materia_prima') {
            $sql = "
                SELECT 
                    inv.insumo_id,
                    i.nombre AS insumo_nombre,
                    i.unidad_medida,
                    inv.stock_actual,
                    DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %H:%i') AS fecha_actualizacion,
                    m.tipo AS ultimo_tipo_movimiento
                FROM inventario inv
                INNER JOIN insumos i ON inv.insumo_id = i.id
                LEFT JOIN (
                    SELECT m1.insumo_id, m1.tipo
                    FROM movimientos_inventario_detalle m1
                    INNER JOIN (
                        SELECT insumo_id, MAX(fecha_movimiento) AS max_fecha
                        FROM movimientos_inventario_detalle
                        GROUP BY insumo_id
                    ) m2 ON m1.insumo_id = m2.insumo_id AND m1.fecha_movimiento = m2.max_fecha
                ) m ON inv.insumo_id = m.insumo_id
                WHERE i.activo = 1
                ORDER BY i.nombre ASC
            ";

            $result = $conn->query($sql);
            $inventario = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $inventario[] = $row;
                }
            }

            if (!empty($inventario)) {
                $i = 0;
                foreach ($inventario as $inv) {
                    $i++;
                    $badgeClass = '';
                    $tipoTexto = '-';
                    
                    if (!empty($inv['ultimo_tipo_movimiento'])) {
                        $badgeClass = $inv['ultimo_tipo_movimiento'] === 'entrada' ? 'badge-entrada' : 'badge-salida';
                        $tipoTexto = $inv['ultimo_tipo_movimiento'] === 'entrada' ? 'Entrada' : 'Salida';
                    }
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($i) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['fecha_actualizacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['insumo_nombre'] . ' (' . $inv['unidad_medida'] . ')') . '</td>';
                    if ($badgeClass) {
                        echo '<td><span class="' . $badgeClass . '">' . htmlspecialchars($tipoTexto) . '</span></td>';
                    } else {
                        echo '<td>' . htmlspecialchars($tipoTexto) . '</td>';
                    }
                    echo '<td><strong>' . number_format($inv['stock_actual'], 2, '.', ',') . '</strong></td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="5" class="text-center">No hay registros en el inventario de materia prima</td></tr>';
            }
        } else {
            $sql = "
                SELECT 
                    r.id AS receta_id,
                    r.producto_id,
                    r.rango_tallas_id,
                    r.tipo_produccion_id,
                    p.nombre AS producto_nombre,
                    rt.nombre_rango AS rango_tallas_nombre,
                    tp.nombre AS tipo_produccion_nombre,
                    COALESCE(inv.stock_actual, 0) AS stock_actual,
                    CASE 
                        WHEN inv.ultima_actualizacion IS NOT NULL 
                        THEN DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %H:%i')
                        ELSE '-'
                    END AS fecha_actualizacion,
                    m.tipo AS ultimo_tipo_movimiento
                FROM recetas r
                INNER JOIN productos p ON r.producto_id = p.id
                INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
                LEFT JOIN inventario_productos inv ON r.producto_id = inv.producto_id 
                    AND r.rango_tallas_id = inv.rango_tallas_id 
                    AND r.tipo_produccion_id = inv.tipo_produccion_id
                LEFT JOIN (
                    SELECT m1.producto_id, m1.rango_tallas_id, m1.tipo_produccion_id, m1.tipo
                    FROM movimientos_productos_detalle m1
                    INNER JOIN (
                        SELECT producto_id, rango_tallas_id, tipo_produccion_id, MAX(fecha_movimiento) AS max_fecha
                        FROM movimientos_productos_detalle
                        GROUP BY producto_id, rango_tallas_id, tipo_produccion_id
                    ) m2 ON m1.producto_id = m2.producto_id 
                        AND m1.rango_tallas_id = m2.rango_tallas_id 
                        AND m1.tipo_produccion_id = m2.tipo_produccion_id
                        AND m1.fecha_movimiento = m2.max_fecha
                ) m ON r.producto_id = m.producto_id 
                    AND r.rango_tallas_id = m.rango_tallas_id 
                    AND r.tipo_produccion_id = m.tipo_produccion_id
                ORDER BY p.nombre, rt.nombre_rango
            ";

            $result = $conn->query($sql);
            $inventario = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $inventario[] = $row;
                }
            }

            if (!empty($inventario)) {
                $i = 0;
                foreach ($inventario as $inv) {
                    $i++;
                    $badgeClass = '';
                    $tipoTexto = '-';
                    
                    if (!empty($inv['ultimo_tipo_movimiento'])) {
                        $badgeClass = $inv['ultimo_tipo_movimiento'] === 'entrada' ? 'badge-entrada' : 'badge-salida';
                        $tipoTexto = $inv['ultimo_tipo_movimiento'] === 'entrada' ? 'Entrada' : 'Salida';
                    }
                    
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($i) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['fecha_actualizacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['producto_nombre']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['rango_tallas_nombre']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['tipo_produccion_nombre']) . '</td>';
                    if ($badgeClass) {
                        echo '<td><span class="' . $badgeClass . '">' . htmlspecialchars($tipoTexto) . '</span></td>';
                    } else {
                        echo '<td>' . htmlspecialchars($tipoTexto) . '</td>';
                    }
                    echo '<td><strong>' . number_format($inv['stock_actual'], 2, '.', ',') . '</strong></td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="7" class="text-center">No hay registros en el inventario de productos</td></tr>';
            }
        }
        $conn->close();
        exit;
    }
    
    if ($action === 'obtener_stock_producto') {
        $producto_id = $_POST['producto_id'] ?? null;
        $rango_tallas_id = $_POST['rango_tallas_id'] ?? null;
        $tipo_produccion_id = $_POST['tipo_produccion_id'] ?? null;
        
        if (!$producto_id || !$rango_tallas_id || !$tipo_produccion_id) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        
        $sql = "SELECT stock_actual FROM inventario_productos 
                WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $stock_actual = 0;
        if ($row = $result->fetch_assoc()) {
            $stock_actual = floatval($row['stock_actual']);
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'stock_actual' => $stock_actual]);
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $tipo_inventario = $_POST['tipo_inventario'] ?? '';
            $insumo_id = $_POST['insumo_id'] ?? null;
            $receta_id = $_POST['receta_id'] ?? null;
            $tipo = $_POST['tipo'] ?? '';
            $cantidad = $_POST['cantidad'] ?? 0;
            $observaciones = $_POST['observaciones'] ?? '';

            if (empty($tipo_inventario)) {
                throw new Exception("El tipo de inventario es obligatorio");
            }

            if ($tipo_inventario === 'materia_prima' && !$insumo_id) {
                throw new Exception("El insumo es obligatorio");
            }

            if ($tipo_inventario === 'productos' && !$receta_id) {
                throw new Exception("La receta/producto es obligatoria");
            }

            if (empty($tipo) || !in_array($tipo, ['entrada', 'salida'])) {
                throw new Exception("El tipo de movimiento es obligatorio y debe ser 'entrada' o 'salida'");
            }

            if (empty($cantidad) || $cantidad <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0");
            }

            $conn->begin_transaction();

            try {
                if ($tipo_inventario === 'materia_prima') {
                    $checkInsumo = $conn->prepare("SELECT id FROM insumos WHERE id = ? AND activo = 1");
                    $checkInsumo->bind_param("i", $insumo_id);
                    $checkInsumo->execute();
                    $resultInsumo = $checkInsumo->get_result();
                    
                    if ($resultInsumo->num_rows === 0) {
                        throw new Exception("El insumo seleccionado no existe o está inactivo");
                    }
                    $checkInsumo->close();

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

                    if ($tipo === 'entrada') {
                        $nuevoStock = $stockActual + floatval($cantidad);
                    } else {
                        $nuevoStock = $stockActual - floatval($cantidad);
                        if ($nuevoStock < 0) {
                            $nuevoStock = 0;
                        }
                    }

                    $stmtMovimiento = $conn->prepare("
                        INSERT INTO movimientos_inventario_detalle 
                        (insumo_id, tipo, cantidad, observaciones)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmtMovimiento->bind_param("isds", $insumo_id, $tipo, $cantidad, $observaciones);
                    $stmtMovimiento->execute();
                    $stmtMovimiento->close();

                    $stmtInventario = $conn->prepare("
                        INSERT INTO inventario (insumo_id, stock_actual, ultima_actualizacion)
                        VALUES (?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                            stock_actual = VALUES(stock_actual),
                            ultima_actualizacion = NOW()
                    ");
                    $stmtInventario->bind_param("id", $insumo_id, $nuevoStock);
                    $stmtInventario->execute();
                    $stmtInventario->close();

                } else {
                    $sqlReceta = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                    $stmtReceta = $conn->prepare($sqlReceta);
                    $stmtReceta->bind_param("i", $receta_id);
                    $stmtReceta->execute();
                    $resultReceta = $stmtReceta->get_result();
                    
                    if (!$rowReceta = $resultReceta->fetch_assoc()) {
                        throw new Exception("Receta no encontrada");
                    }
                    $stmtReceta->close();

                    $producto_id = $rowReceta['producto_id'];
                    $rango_tallas_id = $rowReceta['rango_tallas_id'];
                    $tipo_produccion_id = $rowReceta['tipo_produccion_id'];

                    $sqlStock = "SELECT stock_actual FROM inventario_productos 
                                WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?";
                    $stmtStock = $conn->prepare($sqlStock);
                    $stmtStock->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                    $stmtStock->execute();
                    $resultStock = $stmtStock->get_result();
                    $stockActual = 0;
                    
                    if ($rowStock = $resultStock->fetch_assoc()) {
                        $stockActual = floatval($rowStock['stock_actual']);
                    }
                    $stmtStock->close();

                    if ($tipo === 'entrada') {
                        $nuevoStock = $stockActual + floatval($cantidad);
                    } else {
                        $nuevoStock = $stockActual - floatval($cantidad);
                        if ($nuevoStock < 0) {
                            $nuevoStock = 0;
                        }
                    }

                    $stmtMovimiento = $conn->prepare("
                        INSERT INTO movimientos_productos_detalle 
                        (producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, observaciones)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmtMovimiento->bind_param("iiisds", $producto_id, $rango_tallas_id, $tipo_produccion_id, $tipo, $cantidad, $observaciones);
                    $stmtMovimiento->execute();
                    $stmtMovimiento->close();

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

                $conn->commit();

                $tipoTexto = $tipo === 'entrada' ? 'entrada' : 'salida';
                $inventarioTexto = $tipo_inventario === 'materia_prima' ? 'materia prima' : 'productos';
                echo json_encode([
                    'success' => true, 
                    'message' => "Movimiento de {$tipoTexto} de {$inventarioTexto} registrado exitosamente. Stock actualizado."
                ]);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="6" class="text-center text-danger">Error al cargar movimientos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
    }
}

$conn->close();
?>

