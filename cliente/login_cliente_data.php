<?php
session_start();
include '../connection/connection.php'; // Nota los dos puntos .. para salir de la carpeta
header('Content-Type: application/json; charset=utf-8');

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

// BUSCAR SOLO EN CLIENTES
$sql = "SELECT id, nombre, password FROM clientes WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($pass, $row['password'])) {
        $_SESSION['id_cliente']     = $row['id'];
        $_SESSION['nombre_cliente'] = $row['nombre'];
        $_SESSION['tipo']           = 'cliente';
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contraseña de cliente incorrecta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Correo de cliente no registrado.']);
}