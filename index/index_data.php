<?php
include '../connection/connection.php';

header('Content-Type: application/json; charset=utf-8');


$usuario = $_POST['usuario'] ?? '';
$clave   = $_POST['clave'] ?? '';


if (empty($usuario) || empty($clave)) {
    echo json_encode([
        'success' => false,
        'message' => 'Usuario y contraseña son obligatorios.'
    ]);
    mysqli_close($conn);
    exit;
}

$usuario = mysqli_real_escape_string($conn, $usuario);
$clave   = mysqli_real_escape_string($conn, $clave);


$sql = "SELECT * FROM users WHERE username = '$usuario' AND password = '$clave'";
$result = mysqli_query($conn, $sql);

if (!$result) {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la consulta: ' . mysqli_error($conn)
    ]);
} else {
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_assoc($result);
        echo json_encode([
            'success' => true,
            'message' => 'Login exitoso',
            'user' => $row['username'],
            'id' => $row['id']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Usuario o contraseña incorrectos.'
        ]);
    }
}

mysqli_close($conn);
?>