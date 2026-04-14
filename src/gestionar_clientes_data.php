<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                id,
                nombre,
                tipo_documento,
                numero_documento,
                telefono,
                email,
                direccion
            FROM clientes
            ORDER BY nombre ASC
        ";

        $result = $conn->query($sql);
        $clientes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $clientes[] = $row;
            }
        }
        $i = 0; 
        if (!empty($clientes)) {
            foreach ($clientes as $c) {
                $i++;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($c['numero_documento'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($c['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($c['telefono'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($c['email'] ?? '-') . '</td>';
                echo '<td style="white-space: nowrap;">';
                echo '<button class="btn btn-sm btn-primary" onclick="editarCliente(' . htmlspecialchars(json_encode($c), ENT_QUOTES, 'UTF-8') . ')" style="margin-right: 5px;">Editar</button>';
                echo '<button class="btn btn-sm btn-danger" onclick="eliminarCliente(' . $c['id'] . ')">Eliminar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8" class="text-center">No se encontraron clientes registrados</td></tr>';
        }
        $conn->close();
        exit;
    }

    restringirEscritura();

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $num_doc = trim($_POST['documento'] ?? '');
            $tipo_doc = trim($_POST['tipo_documento'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');

            if (empty($nombre) || empty($num_doc)) {
                throw new Exception("El nombre y el documento son obligatorios");
            }

            $checkSql = "SELECT id FROM clientes WHERE numero_documento = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $num_doc);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception("Ya existe un cliente con el documento: " . $num_doc);
            }

            // Usamos el documento como clave inicial 27123456
            $password_hash = password_hash($num_doc, PASSWORD_DEFAULT);

            $doc= $tipo_doc . $num_doc;

            $stmt = $conn->prepare("
                INSERT INTO clientes (nombre, numero_documento, telefono, email, direccion, password)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param("ssssss", $nombre, $doc, $telefono, $email, $direccion, $password_hash);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cliente registrado.']);
            } else {
                throw new Exception("Error al insertar: " . $stmt->error);
            }
            break;
        
        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de cliente requerido");

            $nombre = trim($_POST['nombre'] ?? '');
            $tipo_documento = trim($_POST['tipo_documento'] ?? '');
            $numero_documento = trim($_POST['documento'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del cliente es obligatorio");
            }

            $checkSql = "SELECT id FROM clientes WHERE numero_documento = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $numero_documento, $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otro cliente con el mismo documento: " . $nombre);
            }

            $stmt = $conn->prepare("
                UPDATE clientes 
                SET nombre = ?, 
                    numero_documento = ?, 
                    telefono = ?, 
                    email = ?, 
                    direccion = ?
                WHERE id = ?
            ");

            $tipo_documento = empty($tipo_documento) ? null : $tipo_documento;
            $numero_documento = empty($numero_documento) ? null : $numero_documento;
            $telefono = empty($telefono) ? null : $telefono;
            $email = empty($email) ? null : $email;
            $direccion = empty($direccion) ? null : $direccion;
            $doc= $tipo_tipo_documentodoc . $numero_documento;

            $stmt->bind_param("sssssi", $nombre, $doc, $telefono, $email, $direccion, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Cliente actualizado exitosamente']);
            break;

        case 'eliminar':
            $id = $_POST['id'] ?? null;
            if (!$id) {
                throw new Exception("ID de cliente requerido");
            }

            $stmt = $conn->prepare("DELETE FROM clientes WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Cliente eliminado exitosamente']);
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
        echo '<tr><td colspan="8" class="text-center text-danger">Error al cargar clientes</td></tr>';
    }
}

$conn->close();
?>

