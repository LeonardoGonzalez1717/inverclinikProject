<?php
session_start();
include 'connection/connection.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = trim($_POST['usuario'] ?? '');
$clave   = $_POST['clave'] ?? '';

if (empty($usuario) || empty($clave)) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario y contraseña son obligatorios.'
    ]);
    mysqli_close($conn);
    exit;
}

// Primero verificar si es un cliente (por email)
$sqlCliente = "SELECT * FROM clientes WHERE email = ?";
$stmtCliente = $conn->prepare($sqlCliente);
$stmtCliente->bind_param("s", $usuario);
$stmtCliente->execute();
$resultCliente = $stmtCliente->get_result();

if ($rowCliente = $resultCliente->fetch_assoc()) {
    // Verificar si tiene password (puede ser NULL si es cliente antiguo)
    if (!empty($rowCliente['password'])) {
        // Validar contraseña encriptada
        if (password_verify($clave, $rowCliente['password'])) {
            $_SESSION['idcliente'] = $rowCliente['id'];
            $_SESSION['nombre_cliente'] = $rowCliente['nombre'];
            $_SESSION['email_cliente'] = $rowCliente['email'];
            $_SESSION['tipo'] = 'cliente';

            echo json_encode([
                'success' => true,
                'message' => 'Login exitoso',
                'tipo'    => 'cliente',
                'id'      => $rowCliente['id']
            ]);
            $stmtCliente->close();
            mysqli_close($conn);
            exit;
        } else {
            $stmtCliente->close();
            echo json_encode([
                'success' => false,
                'message' => 'Contraseña incorrecta.'
            ]);
            mysqli_close($conn);
            exit;
        }
    } else {
        // Cliente sin password configurado
        $stmtCliente->close();
        echo json_encode([
            'success' => false,
            'message' => 'Este cliente no tiene contraseña configurada. Contacta al administrador.'
        ]);
        mysqli_close($conn);
        exit;
    }
}
$stmtCliente->close();

// Si no es cliente, verificar si es usuario (por correo)
$sql = "SELECT u.id, u.username, u.password, u.correo, u.role_id, r.nombre AS rol
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.correo = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Validar contraseña (puede estar encriptada o no)
    $passwordValid = false;
    
    // Intentar verificar con password_verify (si está encriptada)
    if (!empty($row['password']) && password_verify($clave, $row['password'])) {
        $passwordValid = true;
    } 
    // Si no funciona, verificar directamente (para compatibilidad con passwords antiguos)
    elseif ($clave == $row['password']) {
        $passwordValid = true;
    }
    
    if ($passwordValid) {
        $_SESSION['iduser']   = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role_id']  = (int) $row['role_id'];
        $_SESSION['rol']      = $row['rol'] ?? '';
        $_SESSION['tipo']    = 'usuario';

        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
            'tipo'    => 'usuario',
            'user'    => $row['username'],
            'id'      => $row['id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Contraseña incorrecta.'
        ]);
    }
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Correo electrónico no encontrado.'
    ]);
}

$stmt->close();
mysqli_close($conn);
?>