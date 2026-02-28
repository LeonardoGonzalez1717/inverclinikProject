<?php
session_start();
require_once('connection/connection.php');

$usuario = $_POST['usuario'] ?? '';
$clave = $_POST['clave'] ?? '';

$stmt = $conn->prepare("SELECT id, username, password, rol FROM users WHERE username = ?");
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($clave, $row['password'])) {
        
        $_SESSION['iduser'] = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['rol'] = $row['rol'];

        header("Location: dashboard/dashboard.php?iduser=" . $row['id']);
        exit;
        
    } else {
        error_login();
    }
} else {
    error_login();
}

function error_login() {
    echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href='index.php';</script>";
    exit;
}
?>