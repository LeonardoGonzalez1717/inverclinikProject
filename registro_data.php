<?php
session_start();
include 'connection/connection.php';

header('Content-Type: application/json; charset=utf-8');

$nombre = trim($_POST['nombre'] ?? '');
$tipo_documento = trim($_POST['tipo_documento'] ?? '');
$numero_documento = trim($_POST['numero_documento'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$email = trim($_POST['email'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$password = $_POST['password'] ?? '';

// Validar campos obligatorios
if (empty($nombre) || empty($email) || empty($password)) {
    echo json_encode([
        'success' => false,
        'message' => 'Los campos Nombre, Correo Electrónico y Contraseña son obligatorios.'
    ]);
    mysqli_close($conn);
    exit;
}

// Validar formato de correo
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => 'El formato del correo electrónico no es válido.'
    ]);
    mysqli_close($conn);
    exit;
}

// Validar que el correo no exista en clientes
$sqlCheck = "SELECT id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("s", $email);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();

if ($resultCheck->num_rows > 0) {
    echo json_encode([
        'success' => false,
        'message' => 'Este correo electrónico ya está registrado.'
    ]);
    $stmtCheck->close();
    mysqli_close($conn);
    exit;
}
$stmtCheck->close();

// Documento de identidad: no duplicar (misma cédula/RIF aunque venga en tipo + número o solo número)
$tipo_documento = trim((string) $tipo_documento);
$numero_documento = trim((string) $numero_documento);
$docNormalizado = strtoupper(preg_replace('/\s+/', '', $tipo_documento . $numero_documento));
if ($docNormalizado !== '') {
    $sqlDoc = "SELECT id FROM clientes WHERE UPPER(REPLACE(CONCAT(TRIM(IFNULL(tipo_documento,'')), TRIM(IFNULL(numero_documento,''))), ' ', '')) = ? LIMIT 1";
    $stmtDoc = $conn->prepare($sqlDoc);
    if ($stmtDoc) {
        $stmtDoc->bind_param("s", $docNormalizado);
        $stmtDoc->execute();
        $resDoc = $stmtDoc->get_result();
        if ($resDoc && $resDoc->num_rows > 0) {
            echo json_encode([
                'success' => false,
                'message' => 'Ya existe un cliente registrado con el mismo documento de identidad.',
            ]);
            $stmtDoc->close();
            mysqli_close($conn);
            exit;
        }
        $stmtDoc->close();
    }
}

// Encriptar la contraseña
$hash = password_hash($password, PASSWORD_DEFAULT);

// Verificar si la tabla tiene el campo password, si no, lo agregamos
$checkColumn = $conn->query("SHOW COLUMNS FROM clientes LIKE 'password'");
if ($checkColumn->num_rows == 0) {
    $alterSql = "ALTER TABLE clientes ADD COLUMN password VARCHAR(255) NULL AFTER email";
    $conn->query($alterSql);
}

// Preparar valores NULL para campos opcionales (tras validación de duplicados)
$tipo_documento = $tipo_documento === '' ? null : $tipo_documento;
$numero_documento = $numero_documento === '' ? null : $numero_documento;
$telefono = empty($telefono) ? null : $telefono;
$direccion = empty($direccion) ? null : $direccion;

// Insertar nuevo cliente en la tabla clientes
$sql = "INSERT INTO clientes (nombre, tipo_documento, numero_documento, telefono, email, direccion, password) VALUES (?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("sssssss", $nombre, $tipo_documento, $numero_documento, $telefono, $email, $direccion, $hash);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Registro exitoso. Ya puedes iniciar sesión con tu correo.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Error al registrar el cliente: ' . $conn->error
        ]);
    }
    $stmt->close();
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error en la base de datos: ' . $conn->error
    ]);
}

mysqli_close($conn);
?>

