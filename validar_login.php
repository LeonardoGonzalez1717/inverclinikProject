<?php
session_start();
require_once('connection/connection.php'); // tu archivo de conexión

$usuario = $_POST['usuario'] ?? '';
$clave = $_POST['clave'] ?? '';

$sql = "SELECT * FROM users WHERE username = '$usuario' AND password = '$clave'";
$result = mysqli_query($conn, $sql);

if ($row = mysqli_fetch_assoc($result)) {
    $_SESSION['iduser'] = $row['id'];
    $_SESSION['username'] = $row['username'];
    $_SESSION['rol'] = $row['rol'];

    header("Location: dashboard/dashboard.php?iduser=" . $row['id']);
    exit;
} else {
    echo "<script>alert('Usuario o contraseña incorrectos'); window.location.href='index.php';</script>";
}
?>