<?php
session_start();
include 'connection/connection.php';
require_once __DIR__ . '/lib/Auditoria.php';
header('Content-Type: application/json; charset=utf-8');

$audFalloLogin = ['nombre_actor' => '(Intento fallido)', 'id_usuario' => null, 'id_cliente' => null];

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
        
        Auditoria::registrar(
            $conn,
            'Inicio de sesión exitoso (portal cliente). Email: ' . $email . '. Cliente id: ' . (int) $row['id'],
            'Sesión'
        );

        echo json_encode(['success' => true]);
    } else {
        Auditoria::registrar(
            $conn,
            'Intento fallido: contraseña incorrecta (cliente). Email: ' . $email,
            'Sesión',
            $audFalloLogin
        );
        echo json_encode(['success' => false, 'message' => 'Contraseña de cliente incorrecta.']);
    }
} else {
    Auditoria::registrar(
        $conn,
        'Intento fallido: correo no registrado como cliente. Email: ' . $email,
        'Sesión',
        $audFalloLogin
    );
    echo json_encode(['success' => false, 'message' => 'Correo de cliente no registrado.']);
}