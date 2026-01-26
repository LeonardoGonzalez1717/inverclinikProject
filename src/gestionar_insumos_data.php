<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                i.id,
                i.nombre,
                i.unidad_medida,
                i.costo_unitario,
                i.proveedor_id,
                p.nombre AS proveedor_nombre
            FROM insumos i
            LEFT JOIN proveedores p ON i.proveedor_id = p.id
            ORDER BY i.nombre ASC
        ";

        $result = $conn->query($sql);
        $insumos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $insumos[] = $row;
            }
        }
        $orden = 0; 
        if (!empty($insumos)) {
            foreach ($insumos as $i) {
                $orden++;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($orden) . '</td>';
                echo '<td>' . htmlspecialchars($i['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($i['unidad_medida'] ?? '-') . '</td>';
                echo '<td>$' . number_format($i['costo_unitario'] ?? 0, 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($i['proveedor_nombre'] ?? '-') . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="editarInsumo(' . htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8') . ')">Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6" class="text-center">No se encontraron insumos registrados</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $unidad_medida = trim($_POST['unidad_medida'] ?? '');
            $costo_unitario = $_POST['costo_unitario'] ?? 0;
            $proveedor_id = $_POST['proveedor_id'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del insumo es obligatorio");
            }

            if (empty($unidad_medida)) {
                throw new Exception("La unidad de medida es obligatoria");
            }

            if ($costo_unitario < 0) {
                throw new Exception("El costo unitario debe ser mayor o igual a 0");
            }

            $checkSql = "SELECT id FROM insumos WHERE nombre = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $nombre);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe un insumo con el nombre: " . $nombre);
            }

            $stmt = $conn->prepare("
                INSERT INTO insumos (nombre, unidad_medida, costo_unitario, proveedor_id)
                VALUES (?, ?, ?, ?)
            ");

            $proveedor_id = empty($proveedor_id) ? null : $proveedor_id;
            $stmt->bind_param("ssdi", $nombre, $unidad_medida, $costo_unitario, $proveedor_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Insumo creado exitosamente', 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de insumo requerido");

            $nombre = trim($_POST['nombre'] ?? '');
            $unidad_medida = trim($_POST['unidad_medida'] ?? '');
            $costo_unitario = $_POST['costo_unitario'] ?? 0;
            $proveedor_id = $_POST['proveedor_id'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del insumo es obligatorio");
            }

            if (empty($unidad_medida)) {
                throw new Exception("La unidad de medida es obligatoria");
            }

            if ($costo_unitario < 0) {
                throw new Exception("El costo unitario debe ser mayor o igual a 0");
            }

            $checkSql = "SELECT id FROM insumos WHERE nombre = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $nombre, $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otro insumo con el nombre: " . $nombre);
            }

            $stmt = $conn->prepare("
                UPDATE insumos 
                SET nombre = ?, 
                    unidad_medida = ?, 
                    costo_unitario = ?, 
                    proveedor_id = ?
                WHERE id = ?
            ");

            $proveedor_id = empty($proveedor_id) ? null : $proveedor_id;
            $stmt->bind_param("ssdii", $nombre, $unidad_medida, $costo_unitario, $proveedor_id, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Insumo actualizado exitosamente']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="6" class="text-center text-danger">Error al cargar insumos</td></tr>';
    }
}

$conn->close();
?>
