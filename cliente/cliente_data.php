<?php
require_once "../connection/connection.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nombre    = mysqli_real_escape_string($conn, $_POST['nombre']);
    $tipo_doc  = mysqli_real_escape_string($conn, $_POST['tipo_doc']);
    $nro_doc   = mysqli_real_escape_string($conn, $_POST['nro_doc']);
    $email     = mysqli_real_escape_string($conn, $_POST['email_reg']);
    $pass_raw  = $_POST['pass_reg'];

    $queryCheck = "SELECT id FROM clientes WHERE email = '$email' OR numero_documento = '$nro_doc' LIMIT 1";
    $resultCheck = mysqli_query($conn, $queryCheck);

    if (mysqli_num_rows($resultCheck) > 0) {
        echo json_encode([
            'success' => false, 
            'message' => 'El correo o número de documento ya está registrado.'
        ]);
        exit;
    }

    $password_segura = password_hash($pass_raw, PASSWORD_DEFAULT);

    $sql = "INSERT INTO clientes (nombre, tipo_documento, numero_documento, email, password) 
            VALUES ('$nombre', '$tipo_doc', '$nro_doc', '$email', '$password_segura')";

    if (mysqli_query($conn, $sql)) {
        echo json_encode([
            'success' => true, 
            'message' => 'Registro completado. ¡Bienvenido a INVERCLINIK!'
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'Error en el servidor: ' . mysqli_error($conn)
        ]);
    }

} else {
    echo json_encode(['success' => false, 'message' => 'Acceso no permitido.']);
}
?>