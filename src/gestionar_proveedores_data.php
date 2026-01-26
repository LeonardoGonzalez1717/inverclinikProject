<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                id,
                nombre,
                telefono,
                email,
                direccion
            FROM proveedores
            ORDER BY nombre ASC
        ";

        $result = $conn->query($sql);
        $proveedores = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $proveedores[] = $row;
            }
        }
        $i = 0; 
        if (!empty($proveedores)) {
            foreach ($proveedores as $p) {
                $i++;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($p['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($p['telefono'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($p['email'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars(substr($p['direccion'] ?? '', 0, 50)) . (strlen($p['direccion'] ?? '') > 50 ? '...' : '') . '</td>';
                echo '<td style="white-space: nowrap;">';
                echo '<button class="btn btn-sm btn-primary" onclick="editarProveedor(' . htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') . ')" style="margin-right: 5px;">Editar</button>';
                echo '<button class="btn btn-sm btn-danger" onclick="eliminarProveedor(' . $p['id'] . ')">Eliminar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6" class="text-center">No se encontraron proveedores registrados</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del proveedor es obligatorio");
            }

            $checkSql = "SELECT id FROM proveedores WHERE nombre = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $nombre);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe un proveedor con el nombre: " . $nombre);
            }

            $stmt = $conn->prepare("
                INSERT INTO proveedores (nombre, telefono, email, direccion)
                VALUES (?, ?, ?, ?)
            ");

            $telefono = empty($telefono) ? null : $telefono;
            $email = empty($email) ? null : $email;
            $direccion = empty($direccion) ? null : $direccion;

            $stmt->bind_param("ssss", $nombre, $telefono, $email, $direccion);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Proveedor creado exitosamente', 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de proveedor requerido");

            $nombre = trim($_POST['nombre'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del proveedor es obligatorio");
            }

            $checkSql = "SELECT id FROM proveedores WHERE nombre = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $nombre, $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otro proveedor con el nombre: " . $nombre);
            }

            $stmt = $conn->prepare("
                UPDATE proveedores 
                SET nombre = ?, 
                    telefono = ?, 
                    email = ?, 
                    direccion = ?
                WHERE id = ?
            ");

            $telefono = empty($telefono) ? null : $telefono;
            $email = empty($email) ? null : $email;
            $direccion = empty($direccion) ? null : $direccion;

            $stmt->bind_param("ssssi", $nombre, $telefono, $email, $direccion, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Proveedor actualizado exitosamente']);
            break;

        case 'eliminar':
            $id = $_POST['id'] ?? null;
            if (!$id) {
                throw new Exception("ID de proveedor requerido");
            }

            $stmt = $conn->prepare("DELETE FROM proveedores WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado exitosamente']);
            $stmt->close();
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="6" class="text-center text-danger">Error al cargar proveedores</td></tr>';
    }
}

$conn->close();
?>

