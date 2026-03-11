<?php
session_start();
include 'connection/connection.php';
header('Content-Type: application/json; charset=utf-8');

$usuario = trim($_POST['usuario'] ?? '');
$clave   = $_POST['clave'] ?? '';

$sql = "SELECT id, username, password, rol FROM users WHERE correo = ? OR username = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $usuario, $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Verificación flexible (Hash o Texto Plano para compatibilidad)
    if (password_verify($clave, $row['password']) || $clave == $row['password']) {
        $_SESSION['iduser']   = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['rol']      = $row['rol'];
        $_SESSION['tipo']     = 'admin';

        echo json_encode(['success' => true, 'message' => 'Acceso concedido', 'id' => $row['id']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contraseña administrativa incorrecta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'El administrador no existe.']);
}
$stmt->close();
$conn->close();