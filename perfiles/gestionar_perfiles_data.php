<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $sql = "SELECT id, username, correo, login, rol, createdAt FROM users ORDER BY id DESC";
        $result = $conn->query($sql);
        $usuarios = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $usuarios[] = $row;
            }
        }

        if (empty($usuarios)) {
            echo '<tr><td colspan="7" class="text-center">No hay usuarios registrados</td></tr>';
        } else {        
            $i = 0;
            foreach ($usuarios as $user) {
                // $user['password'] = password_verify($user['password'], $user['password']);
                $i++;
                $fecha = date('d/m/Y H:i', strtotime($user['createdAt']));
                $rol = $user['rol'] ?? 'No asignado';
                $rolDisplay = ucfirst($rol);
                echo '<tr>';
                echo '<td>' . $i . '</td>';
                echo '<td>' . htmlspecialchars($user['username']) . '</td>';
                echo '<td>' . htmlspecialchars($user['correo']) . '</td>';
                echo '<td>' . htmlspecialchars($user['login']) . '</td>';
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
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $login    = $_POST['login'] ?? '';
        $correo   = $_POST['correo'] ?? '';
        $rol      = $_POST['rol'] ?? '';

        if (empty($username) || empty($password) || empty($login) || empty($correo) || empty($rol)) {
            throw new Exception("Todos los campos son obligatorios");
        }

        $rolesPermitidos = ['superadmin', 'administrador', 'cliente'];
        if (!in_array($rol, $rolesPermitidos)) {
            throw new Exception("Rol no válido");
        }

        // Validar duplicados
        $sqlCheck = "SELECT id FROM users WHERE username = ? OR login = ? OR correo = ?";
        $stmtCheck = $conn->prepare($sqlCheck);
        $stmtCheck->bind_param("sss", $username, $login, $correo);
        $stmtCheck->execute();
        $resultCheck = $stmtCheck->get_result();
        
        if ($resultCheck->num_rows > 0) {
            throw new Exception("El usuario, login o correo ya existe");
        }
        $stmtCheck->close();

        // Encriptar la contraseña antes de guardar
        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, password, login, correo, rol) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $hash, $login, $correo, $rol);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'id' => $conn->insert_id
        ]);
        $stmt->close();
    } elseif ($action === 'editar') {
        $id  = $_POST['id'] ?? null;
        $rol = $_POST['rol'] ?? '';

        if (!$id) {
            throw new Exception("ID de usuario requerido");
        }

        $rolesPermitidos = ['superadmin', 'administrador', 'cliente'];
        if (!in_array($rol, $rolesPermitidos)) {
            throw new Exception("Rol no válido");
        }

        // Solo actualizamos el rol
        $stmt = $conn->prepare("UPDATE users SET rol = ? WHERE id = ?");
        $stmt->bind_param("si", $rol, $id);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Rol actualizado exitosamente'
        ]);
        $stmt->close();
    } elseif ($action === 'obtener') {
        $id = $_POST['id'] ?? null;
        if (!$id) {
            throw new Exception("ID de usuario requerido");
        }

        $stmt = $conn->prepare("SELECT id, username, password, login, correo, rol FROM users WHERE id = ?");
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
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
        $stmt->close();
    } else {
        throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        } else {
            echo '<tr><td colspan="7" class="text-center text-danger">Error al cargar usuarios</td></tr>';
        }
}

$conn->close();
?>

