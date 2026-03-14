<?php
session_start();
include 'connection/connection.php';
header('Content-Type: application/json; charset=utf-8');

$email = trim($_POST['email'] ?? '');
$pass  = $_POST['password'] ?? '';

// BUSCAR SOLO EN CLIENTES
$sql = "SELECT id, nombre, password, role_id FROM clientes WHERE email = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (password_verify($pass, $row['password'])) {
        $_SESSION['id_cliente']      = $row['id']; 
        $_SESSION['nombre_cliente'] = $row['nombre'];
        $_SESSION['role_id']        = (int)$row['role_id'];  
        $_SESSION['tipo']           = 'cliente';
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Contraseña de cliente incorrecta.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Correo de cliente no registrado.']);
}