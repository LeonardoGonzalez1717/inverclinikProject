<?php
session_start();
include 'connection/connection.php';

header('Content-Type: application/json; charset=utf-8');

$usuario = $_POST['usuario'] ?? '';
$clave   = $_POST['clave'] ?? '';

if (empty($usuario) || empty($clave)) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario y contraseña son obligatorios.'
    ]);
    mysqli_close($conn);
    exit;
}

// Usar consulta solo por username
$sql = "SELECT * FROM users WHERE username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Validar contraseña encriptada
    if (password_verify($clave, $row['password'])) {
        $_SESSION['iduser']   = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['rol']      = $row['rol'];

        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
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
        'message' => 'Usuario no encontrado.'
    ]);
}

mysqli_close($conn);
?>