<?php
session_start();
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol
                FROM users u
                LEFT JOIN roles r ON r.id = u.role_id
                ORDER BY u.id DESC";
        $result = $conn->query($sql);
        $usuarios = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $usuarios[] = $row;
            }
        }

        if (empty($usuarios)) {
            echo '<tr><td colspan="6" class="text-center">No hay usuarios registrados</td></tr>';
        } else {        
            $i = 0;
            foreach ($usuarios as $user) {
                $i++;
                $fecha = date('d/m/Y H:i', strtotime($user['createdAt']));
                $rol = $user['rol'] ?? 'No asignado';
                $rolDisplay = ucfirst($rol);
                echo '<tr>';
                echo '<td>' . $i . '</td>';
                echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                echo '<td>' . htmlspecialchars($user['correo']) . '</td>';
                echo '<td>' . htmlspecialchars($rolDisplay) . '</td>';
                echo '<td>' . $fecha . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-warning" onclick="editarUsuario(' . $user['id'] . ')">Editar</button> ';
                echo '<button class="btn btn-sm btn-danger" onclick="eliminarUsuario(' . $user['id'] . ')">Eliminar</button>';
                echo '</td>';
                echo '</tr>';
            }
        }
    } elseif ($action === 'crear') {
        restringirEscritura();

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $correo   = $_POST['correo'] ?? '';
        $role_id  = (int) ($_POST['role_id'] ?? 0);

        if (empty($username) || empty($password) || empty($correo) || $role_id < 1) {
            throw new Exception("Todos los campos son obligatorios");
        }

        if (!in_array($role_id, [1, 2])) {
            throw new Exception("Rol no válido (1=admin, 2=supervisor)");
        }

        // Validar duplicados
        $sqlCheck = "SELECT id FROM users WHERE username = ? OR correo = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("ss", $username, $correo);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        
        if ($resultCheck->num_rows > 0) {
            throw new Exception("El usuario o correo ya existe");
        }
        $stmtCheck->close();

        // Encriptar la contraseña antes de guardar
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, correo, role_id) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $username, $hash, $correo, $role_id);
        $stmt->execute();
        $nuevoId = (int) $conn->insert_id;
        $stmt->close();

        Auditoria::registrar(
            $conn,
            'Usuario interno creado: id ' . $nuevoId . ', username ' . $username . ', correo ' . $correo . ', role_id ' . $role_id,
            'Usuarios'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'id' => $nuevoId
        ]);
    } elseif ($action === 'editar') {
        restringirEscritura();

        $id      = $_POST['id'] ?? null;
        $role_id = (int) ($_POST['role_id'] ?? 0);

        if (!$id) {
            throw new Exception("ID de usuario requerido");
        }

        if (!in_array($role_id, [1, 2])) {
            throw new Exception("Rol no válido (1=admin, 2=supervisor)");
        }

        // Solo actualizamos el rol
        $stmt = $conn->prepare("UPDATE users SET role_id = ? WHERE id = ?");
        $stmt->bind_param("ii", $role_id, $id);
        $stmt->execute();
        $stmt->close();

        Auditoria::registrar(
            $conn,
            'Rol de usuario actualizado: user id ' . (int) $id . ', nuevo role_id ' . $role_id,
            'Usuarios'
        );

        echo json_encode([
            'success' => true,
            'message' => 'Rol actualizado exitosamente'
        ]);
    } elseif ($action === 'obtener') {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new Exception("ID de usuario requerido");
        }

        $stmt = $conn->prepare("SELECT u.id, u.username, u.password, u.correo, u.role_id, r.nombre AS rol FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            echo json_encode(['success' => true, 'usuario' => $row]);
        } else {
            throw new Exception("Usuario no encontrado");
        }
        $stmt->close();
    } elseif ($action === 'eliminar') {
        restringirEscritura();

        $id = $_POST['id'] ?? null;
        $idActivo = $_SESSION['iduser'] ?? null;

        if (!$id) {
            throw new Exception("ID de usuario requerido");
        }

        if ($id == $idActivo) {
            echo json_encode([
                'success' => false,
                'message' => 'No puedes eliminar tu propio usuario mientras estás logueado.'
            ]);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        Auditoria::registrar($conn, 'Usuario interno eliminado: id ' . (int) $id, 'Usuarios');

        echo json_encode([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    } else {
        throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } else {
            echo '<tr><td colspan="6" class="text-center text-danger">Error al cargar usuarios</td></tr>';
        }
}

$conn->close();
?>

