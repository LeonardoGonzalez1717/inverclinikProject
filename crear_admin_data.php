<?php
/**
 * Crea el primer usuario administrador si no existe ninguno con rol admin.
 * Recibe username, correo y password del formulario.
 */
header('Content-Type: application/json; charset=utf-8');

include __DIR__ . '/connection/connection.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'crear_admin') {
    echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$correo   = trim($_POST['correo'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '') {
    echo json_encode(['success' => false, 'message' => 'El usuario es obligatorio.']);
    exit;
}
if ($correo === '') {
    echo json_encode(['success' => false, 'message' => 'El correo es obligatorio.']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres.']);
    exit;
}

// Verificar que no exista ya un usuario con rol admin (role_id = 1)
$stmt = $conn->prepare("SELECT id FROM users WHERE role_id = 1 LIMIT 1");
$stmt->execute();
$r = $stmt->get_result();
$stmt->close();

if ($r && $r->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Ya existe un usuario administrador.']);
    $conn->close();
    exit;
}

// Verificar que el usuario o correo no estén ya registrados
$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR correo = ? LIMIT 1");
$stmt->bind_param('ss', $username, $correo);
$stmt->execute();
$r = $stmt->get_result();
$stmt->close();
if ($r && $r->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Ese usuario o correo ya está registrado.']);
    $conn->close();
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$role_id = 1; // admin
$stmt = $conn->prepare("INSERT INTO users (username, password, correo, role_id) VALUES (?, ?, ?, ?)");
$stmt->bind_param('sssi', $username, $hash, $correo, $role_id);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode([
        'success' => true,
        'message' => 'Usuario administrador creado correctamente. Ya puedes iniciar sesión.'
    ]);
} else {
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Error al crear el usuario.']);
}
