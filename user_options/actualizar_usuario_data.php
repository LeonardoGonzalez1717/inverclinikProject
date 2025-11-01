<?php
require_once '../connection/connection.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$correo = trim($_POST['correo'] ?? '');

if (!$id || !$username || !$correo) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$sql_check = "SELECT id FROM users WHERE (username = ? OR correo = ?) AND id != ?";
$stmt_check = $conn->prepare($sql_check);
$stmt_check->bind_param("ssi", $username, $correo, $id);
$stmt_check->execute();
$result = $stmt_check->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Nombre de usuario o correo ya en uso']);
    exit;
}

// Actualizar
$sql = "UPDATE users SET username = ?, correo = ? WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssi", $username, $correo, $id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error al actualizar']);
}
?>