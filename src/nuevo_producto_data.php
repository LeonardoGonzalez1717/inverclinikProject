<?php
require_once "../connection/connection.php";

// Verificar y agregar columna precio_total a la tabla recetas si no existe
$checkColumnRecetas = $conn->query("SHOW COLUMNS FROM recetas LIKE 'precio_total'");
if ($checkColumnRecetas->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE recetas 
                     ADD COLUMN precio_total DECIMAL(10,2) DEFAULT 0.00 
                     AFTER observaciones");
    } catch (Exception $e) {
        // La columna ya existe o hay un error, continuar
    }
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        // Asegurar que la tabla recetas existe
        $createRecetasUnicas = "
        CREATE TABLE IF NOT EXISTS recetas (
            id INT PRIMARY KEY AUTO_INCREMENT,
            producto_id INT NOT NULL,
            rango_tallas_id INT NOT NULL,
            tipo_produccion_id INT NOT NULL,
            observaciones TEXT,
            precio_total DECIMAL(10,2) DEFAULT 0.00,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_receta (producto_id, rango_tallas_id, tipo_produccion_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
        ";
        $conn->query($createRecetasUnicas);
        
        // Verificar y agregar columna precio_total si no existe (para tablas existentes)
        $checkColumnRecetas = $conn->query("SHOW COLUMNS FROM recetas LIKE 'precio_total'");
        if ($checkColumnRecetas->num_rows == 0) {
            try {
                $conn->query("ALTER TABLE recetas 
                             ADD COLUMN precio_total DECIMAL(10,2) DEFAULT 0.00 
                             AFTER observaciones");
            } catch (Exception $e) {
                // La columna ya existe o hay un error, continuar
            }
        }

        // Migrar datos si la tabla está vacía
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

        $sql = "
            SELECT 
                r.id,
                p.nombre AS producto_nombre,
                rt.nombre_rango AS rango_tallas_nombre,
                tp.nombre AS tipo_produccion_nombre,
                r.observaciones,
                r.producto_id,
                r.rango_tallas_id,
                r.tipo_produccion_id,
                r.creado_en,
                COALESCE(SUM(rp.cantidad_por_unidad * i.costo_unitario), 0) AS costo_total,
                COALESCE(r.precio_total, 0) AS precio_total,
                COUNT(DISTINCT rp.insumo_id) AS cantidad_insumos
            FROM recetas r
            INNER JOIN productos p ON r.producto_id = p.id
            INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
            INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
            LEFT JOIN recetas_productos rp ON rp.producto_id = r.producto_id 
                AND rp.rango_tallas_id = r.rango_tallas_id 
                AND rp.tipo_produccion_id = r.tipo_produccion_id
            LEFT JOIN insumos i ON rp.insumo_id = i.id
            GROUP BY r.id, r.producto_id, r.rango_tallas_id, r.tipo_produccion_id, p.nombre, rt.nombre_rango, tp.nombre, r.observaciones, r.creado_en, r.precio_total
            ORDER BY r.id DESC
        ";

        $result = $conn->query($sql);
        $recetas = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recetas[] = $row;
            }
        }

        if (!empty($recetas)) {
            $i = 0;
            foreach ($recetas as $r) {
                $i++;
                $fecha = $r['creado_en'] ? date('d/m/Y H:i', strtotime($r['creado_en'])) : '';
                echo '<tr>';
                echo '<td>' .$i. '</td>';
                echo '<td>' . htmlspecialchars($r['producto_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($r['rango_tallas_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($r['tipo_produccion_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($r['cantidad_insumos']) . '</td>';
                echo '<td>$' . number_format($r['costo_total'] ?? 0, 2, '.', ',') . '</td>';
                echo '<td>$' . number_format($r['precio_total'] ?? 0, 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($r['observaciones'] ?? '') . '</td>';
                echo '<td>' . $fecha . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="editarReceta(' . htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') . ')">Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10" class="text-center">No se encontraron recetas registradas</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear_receta_completa':
            $producto_id = $_POST['producto_id'] ?? null;
            $rango_tallas_id = $_POST['rango_tallas_id'] ?? null;
            $tipo_produccion_id = $_POST['tipo_produccion_id'] ?? null;
            $precio_total = floatval($_POST['precio_total'] ?? 0);
            $observaciones = $_POST['observaciones'] ?? '';
            
            $insumos = $_POST['insumos'] ?? [];
            if (is_string($insumos)) {
                $insumos = json_decode($insumos, true) ?? [];
            }
            if (!is_array($insumos)) {
                $insumos = [];
            }
            
            if (!$producto_id || !$rango_tallas_id || !$tipo_produccion_id || empty($insumos)) {
                throw new Exception("Todos los campos son obligatorios y debes agregar al menos un insumo");
            }
            
            if ($precio_total <= 0) {
                throw new Exception("El precio total del producto debe ser mayor a 0");
            }
            
            $createRecetasUnicas = "
            CREATE TABLE IF NOT EXISTS recetas (
                id INT PRIMARY KEY AUTO_INCREMENT,
                producto_id INT NOT NULL,
                rango_tallas_id INT NOT NULL,
                tipo_produccion_id INT NOT NULL,
                observaciones TEXT,
                precio_total DECIMAL(10,2) DEFAULT 0.00,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_receta (producto_id, rango_tallas_id, tipo_produccion_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
            ";
            $conn->query($createRecetasUnicas);
            
            // Verificar y agregar columna precio_total si no existe
            $checkColumnRecetas = $conn->query("SHOW COLUMNS FROM recetas LIKE 'precio_total'");
            if ($checkColumnRecetas->num_rows == 0) {
                try {
                    $conn->query("ALTER TABLE recetas 
                                 ADD COLUMN precio_total DECIMAL(10,2) DEFAULT 0.00 
                                 AFTER observaciones");
                } catch (Exception $e) {
                    // La columna ya existe o hay un error, continuar
                }
            }
            
            $conn->begin_transaction();
            try {
                // Primero, insertar o actualizar el registro en la tabla recetas usando ON DUPLICATE KEY UPDATE
                $stmtReceta = $conn->prepare("
                    INSERT INTO recetas (producto_id, rango_tallas_id, tipo_produccion_id, observaciones, precio_total)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE observaciones = VALUES(observaciones), precio_total = VALUES(precio_total)
                ");
                $stmtReceta->bind_param("iiisd", $producto_id, $rango_tallas_id, $tipo_produccion_id, $observaciones, $precio_total);
                $stmtReceta->execute();
                $stmtReceta->close();
                
                // Luego, insertar los insumos en recetas_productos
                foreach ($insumos as $insumo) {
                    $checkSql = "SELECT id FROM recetas_productos 
                                WHERE producto_id = ? AND insumo_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("iiii", $producto_id, $insumo['insumo_id'], $rango_tallas_id, $tipo_produccion_id);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    
                    
                    $checkStmt->close();
                    
                    $stmt = $conn->prepare("
                        INSERT INTO recetas_productos (producto_id, insumo_id, rango_tallas_id, tipo_produccion_id, cantidad_por_unidad, costo_por_unidad, observaciones)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->bind_param("iiiidds", 
                        $producto_id, 
                        $insumo['insumo_id'], 
                        $rango_tallas_id, 
                        $tipo_produccion_id, 
                        $insumo['cantidad_por_unidad'],
                        $insumo['costo_total'],
                        $observaciones
                    );
                    $stmt->execute();
                    $stmt->close();
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Receta completa creada exitosamente con ' . count($insumos) . ' insumo(s)']);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'obtener_costo_insumo':
            $insumo_id = $_POST['insumo_id'] ?? null;
            if (!$insumo_id) {
                throw new Exception("ID de insumo requerido");
            }
            
            $sql = "SELECT costo_unitario FROM insumos WHERE id = ? AND activo = 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $insumo_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($row = $result->fetch_assoc()) {
                echo json_encode([
                    'success' => true, 
                    'costo_unitario' => $row['costo_unitario']
                ]);
            } else {
                throw new Exception("Insumo no encontrado");
            }
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de receta requerido");

            $producto_id = $_POST['producto_id'] ?? null;
            $insumo_id = $_POST['insumo_id'] ?? null;
            $rango_tallas_id = $_POST['rango_tallas_id'] ?? null;
            $tipo_produccion_id = $_POST['tipo_produccion_id'] ?? null;
            $cantidad_por_unidad = $_POST['cantidad_por_unidad'] ?? 0;
            $costo_por_unidad = $_POST['costo_por_unidad'] ?? 0;
            $observaciones = $_POST['observaciones'] ?? '';

            if (!$producto_id || !$insumo_id || !$rango_tallas_id || !$tipo_produccion_id || $cantidad_por_unidad <= 0) {
                throw new Exception("Todos los campos son obligatorios y la cantidad debe ser mayor a 0");
            }

            if ($costo_por_unidad <= 0) {
                $sqlCosto = "SELECT costo_unitario FROM insumos WHERE id = ?";
                $stmtCosto = $conn->prepare($sqlCosto);
                $stmtCosto->bind_param("i", $insumo_id);
                $stmtCosto->execute();
                $resultCosto = $stmtCosto->get_result();
                if ($rowCosto = $resultCosto->fetch_assoc()) {
                    $costo_por_unidad = $cantidad_por_unidad * $rowCosto['costo_unitario'];
                }
            }

            $checkSql = "SELECT id FROM recetas_productos 
                        WHERE producto_id = ? AND insumo_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("iiiii", $producto_id, $insumo_id, $rango_tallas_id, $tipo_produccion_id, $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otra receta con esta combinación de producto, insumo, rango de tallas y tipo de producción");
            }

            $stmt = $conn->prepare("
                UPDATE recetas_productos 
                SET producto_id = ?, 
                    insumo_id = ?, 
                    rango_tallas_id = ?, 
                    tipo_produccion_id = ?, 
                    cantidad_por_unidad = ?, 
                    costo_por_unidad = ?,
                    observaciones = ?
                WHERE id = ?
            ");
            $stmt->bind_param("iiiiddsi", $producto_id, $insumo_id, $rango_tallas_id, $tipo_produccion_id, $cantidad_por_unidad, $costo_por_unidad, $observaciones, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Receta actualizada exitosamente']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="10" class="text-center text-danger">Error al cargar recetas</td></tr>';
    }
}

$conn->close();
?>
