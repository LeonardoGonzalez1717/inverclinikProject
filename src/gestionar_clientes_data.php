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
                $docMostrar = trim((string) ($c['tipo_documento'] ?? '')) . trim((string) ($c['numero_documento'] ?? ''));
                if ($docMostrar === '' && !empty($c['numero_documento'])) {
                    $docMostrar = (string) $c['numero_documento'];
                }
                echo '<td>' . htmlspecialchars($docMostrar !== '' ? $docMostrar : '-') . '</td>';
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
            if ($tipo_doc === '') {
                throw new Exception("Debe indicar el tipo de documento (V, J o E)");
            }

            $docNormalizado = strtoupper(preg_replace('/\s+/', '', $tipo_doc . $num_doc));

            $checkSql = "SELECT id FROM clientes WHERE UPPER(REPLACE(CONCAT(TRIM(IFNULL(tipo_documento,'')), TRIM(IFNULL(numero_documento,''))), ' ', '')) = ? LIMIT 1";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $docNormalizado);
            $checkStmt->execute();
            if ($checkStmt->get_result()->num_rows > 0) {
                throw new Exception("Ya existe un cliente con el mismo documento de identidad.");
            }
            $checkStmt->close();

            if ($email !== '') {
                $checkEmail = $conn->prepare("SELECT id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1");
                $checkEmail->bind_param("s", $email);
                $checkEmail->execute();
                if ($checkEmail->get_result()->num_rows > 0) {
                    throw new Exception("Ya existe un cliente con el correo: " . $email);
                }
                $checkEmail->close();
            }

            // Usamos el documento como clave inicial
            $password_hash = password_hash($num_doc, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("
                INSERT INTO clientes (nombre, tipo_documento, numero_documento, telefono, email, direccion, password)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->bind_param("sssssss", $nombre, $tipo_doc, $num_doc, $telefono, $email, $direccion, $password_hash);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Cliente registrado.']);
            } else {
                throw new Exception("Error al insertar: " . $stmt->error);
            }
            break;
        
        case 'editar':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception("ID de cliente requerido");
            }

            $nombre = trim($_POST['nombre'] ?? '');
            $tipo_documento = trim($_POST['tipo_documento'] ?? '');
            $numero_documento = trim($_POST['documento'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del cliente es obligatorio");
            }
            if ($tipo_documento === '' || $numero_documento === '') {
                throw new Exception("El tipo y el número de documento son obligatorios");
            }

            $docNormalizado = strtoupper(preg_replace('/\s+/', '', $tipo_documento . $numero_documento));

            if ($docNormalizado !== '') {
                $checkSql = "SELECT id FROM clientes WHERE id != ? AND UPPER(REPLACE(CONCAT(TRIM(IFNULL(tipo_documento,'')), TRIM(IFNULL(numero_documento,''))), ' ', '')) = ? LIMIT 1";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->bind_param("is", $id, $docNormalizado);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    throw new Exception("Ya existe otro cliente con el mismo documento de identidad.");
                }
                $checkStmt->close();
            }

            if ($email !== '') {
                $checkEmail = $conn->prepare("SELECT id FROM clientes WHERE id != ? AND LOWER(TRIM(email)) = LOWER(?) LIMIT 1");
                $checkEmail->bind_param("is", $id, $email);
                $checkEmail->execute();
                if ($checkEmail->get_result()->num_rows > 0) {
                    throw new Exception("Ya existe otro cliente con el correo indicado.");
                }
                $checkEmail->close();
            }

            $stmt = $conn->prepare("
                UPDATE clientes 
                SET nombre = ?, 
                    tipo_documento = ?,
                    numero_documento = ?, 
                    telefono = ?, 
                    email = ?, 
                    direccion = ?
                WHERE id = ?
            ");

            $telefono = empty($telefono) ? null : $telefono;
            $email = empty($email) ? null : $email;
            $direccion = empty($direccion) ? null : $direccion;

            $stmt->bind_param("ssssssi", $nombre, $tipo_documento, $numero_documento, $telefono, $email, $direccion, $id);
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

