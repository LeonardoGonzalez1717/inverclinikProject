<?php
session_start();
include 'connection/connection.php';
require_once __DIR__ . '/lib/Auditoria.php';
header('Content-Type: application/json; charset=utf-8');

$audFalloLogin = ['nombre_actor' => '(Intento fallido)', 'id_usuario' => null, 'id_cliente' => null];

// Limpiamos entradas
$usuario = trim($_POST['usuario'] ?? '');
$clave   = $_POST['clave'] ?? '';

if (empty($usuario) || empty($clave)) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario y contraseña son obligatorios.'
    ]);
    exit;
}

/**
 * BUSCAR SOLO EN LA TABLA DE USUARIOS INTERNOS (users)
 * Filtramos por u.correo y nos aseguramos de traer el rol.
 */
$sql = "SELECT u.id, u.username, u.password, u.correo, u.role_id, r.nombre AS rol
        FROM users u
        LEFT JOIN roles r ON r.id = u.role_id
        WHERE u.correo = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $usuario);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    // Verificación de contraseña (soporta hash y texto plano temporalmente)
    if (password_verify($clave, $row['password']) || $clave == $row['password']) {
        
        // IMPORTANTE: Limpiamos cualquier rastro de sesión de cliente para evitar choques
        unset($_SESSION['idcliente']);
        unset($_SESSION['nombre_cliente']);

        // Seteamos variables de sesión Administrativas
        $_SESSION['iduser']   = $row['id'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role_id']  = (int) $row['role_id'];
        $_SESSION['rol']      = $row['rol'] ?? 'Sin Rol';
        $_SESSION['tipo']     = 'usuario'; // Identificador para el sistema

        Auditoria::registrar(
            $conn,
            'Inicio de sesión exitoso (usuario interno). Correo: ' . $usuario . '. Rol: ' . ($row['rol'] ?? ''),
            'Sesión'
        );

        echo json_encode([
            'success' => true, 
            'message' => 'Acceso administrativo concedido',
            'tipo'    => 'usuario'
        ]);
    } else {
        Auditoria::registrar(
            $conn,
            'Intento fallido: contraseña incorrecta. Correo: ' . $usuario,
            'Sesión',
            $audFalloLogin
        );
        echo json_encode(['success' => false, 'message' => 'Contraseña incorrecta.']);
    }
} else {
    Auditoria::registrar(
        $conn,
        'Intento fallido: correo no registrado como usuario interno. Correo: ' . $usuario,
        'Sesión',
        $audFalloLogin
    );
    echo json_encode(['success' => false, 'message' => 'El usuario administrativo no existe.']);
}

$stmt->close();
$conn->close();