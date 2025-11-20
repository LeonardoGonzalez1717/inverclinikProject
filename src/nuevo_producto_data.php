<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                rp.id,
                p.nombre AS producto_nombre,
                i.nombre AS insumo_nombre,
                rt.nombre_rango AS rango_tallas_nombre,
                tp.nombre AS tipo_produccion_nombre,
                rp.cantidad_por_unidad,
                i.costo_unitario AS costo_unitario_insumo,
                (rp.cantidad_por_unidad * i.costo_unitario) AS costo_calculado,
                COALESCE(rp.costo_por_unidad, (rp.cantidad_por_unidad * i.costo_unitario)) AS costo_por_unidad,
                rp.observaciones,
                rp.producto_id,
                rp.insumo_id,
                rp.rango_tallas_id,
                rp.tipo_produccion_id
            FROM recetas_productos rp
            INNER JOIN productos p ON rp.producto_id = p.id
            INNER JOIN insumos i ON rp.insumo_id = i.id
            INNER JOIN rangos_tallas rt ON rp.rango_tallas_id = rt.id
            INNER JOIN tipos_produccion tp ON rp.tipo_produccion_id = tp.id
            ORDER BY rp.id DESC
        ";

        $result = $conn->query($sql);
        $recetas = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recetas[] = $row;
            }
        }

        if (!empty($recetas)) {
            foreach ($recetas as $r) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($r['id']) . '</td>';
                echo '<td>' . htmlspecialchars($r['producto_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($r['insumo_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($r['rango_tallas_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($r['tipo_produccion_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($r['cantidad_por_unidad']) . '</td>';
                echo '<td>$' . number_format($r['costo_por_unidad'] ?? 0, 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($r['observaciones'] ?? '') . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="editarReceta(' . htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') . ')">Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="9" class="text-center">No se encontraron recetas registradas</td></tr>';
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
            $observaciones = $_POST['observaciones'] ?? '';
            
            // Manejar insumos: puede venir como array o como string JSON
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
            
            $conn->begin_transaction();
            try {
                foreach ($insumos as $insumo) {
                    $checkSql = "SELECT id FROM recetas_productos 
                                WHERE producto_id = ? AND insumo_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?";
                    $checkStmt = $conn->prepare($checkSql);
                    $checkStmt->bind_param("iiii", $producto_id, $insumo['insumo_id'], $rango_tallas_id, $tipo_produccion_id);
                    $checkStmt->execute();
                    $result = $checkStmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        throw new Exception("Ya existe una receta con el insumo: " . $insumo['insumo_nombre']);
                    }
                    
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
        echo '<tr><td colspan="9" class="text-center text-danger">Error al cargar recetas</td></tr>';
    }
}

$conn->close();
?>
