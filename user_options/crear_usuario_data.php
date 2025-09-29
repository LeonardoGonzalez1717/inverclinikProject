<?php
require_once '../connection/connection.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}


$username = trim($_POST['username'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';


if (empty($username) || empty($correo) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
    exit;
}


if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR correo = ?");
$stmt->bind_param("ss", $username, $correo);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'El nombre de usuario o correo ya están en uso']);
    exit;
}

// $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

$stmt = $conn->prepare("INSERT INTO users (username, correo, password) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $username, $correo, $password);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Usuario creado exitosamente',
        'id' => $conn->insert_id
    ]);
} else {
    error_log("Error al crear usuario: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Error al guardar el usuario. Inténtalo más tarde.']);
}

$stmt->close();
$conn->close();
?>