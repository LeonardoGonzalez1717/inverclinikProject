<?php
require_once "../connection/connection.php";

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Acceso no permitido.']);
    exit;
}

$nombre = trim((string) ($_POST['nombre'] ?? ''));
$tipo_doc = trim((string) ($_POST['tipo_doc'] ?? ''));
$nro_doc = trim((string) ($_POST['nro_doc'] ?? ''));
$codigoTel = preg_replace('/\D/', '', trim((string) ($_POST['telefono_codigo'] ?? '')));
$numeroTel = preg_replace('/\D/', '', trim((string) ($_POST['telefono_numero'] ?? '')));
$combinedTel = $codigoTel . $numeroTel;

if ($nombre === '') {
    echo json_encode(['success' => false, 'message' => 'El nombre es obligatorio.']);
    exit;
}

if ($codigoTel === '' || $numeroTel === '') {
    echo json_encode([
        'success' => false,
        'message' => 'Indique el código y el número de teléfono.',
    ]);
    exit;
}

if (strlen($combinedTel) > 20) {
    echo json_encode([
        'success' => false,
        'message' => 'El teléfono combinado supera el máximo permitido.',
    ]);
    exit;
}

$email = trim((string) ($_POST['email_reg'] ?? ''));
$pass_raw = (string) ($_POST['pass_reg'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Indique un correo electrónico válido.']);
    exit;
}

if ($pass_raw === '') {
    echo json_encode(['success' => false, 'message' => 'La contraseña es obligatoria.']);
    exit;
}

$docNormalizado = strtoupper(preg_replace('/\s+/', '', $tipo_doc . $nro_doc));

if ($docNormalizado !== '') {
    $stmtDup = $conn->prepare(
        "SELECT id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(?) OR UPPER(REPLACE(CONCAT(TRIM(IFNULL(tipo_documento,'')), TRIM(IFNULL(numero_documento,''))), ' ', '')) = ? LIMIT 1"
    );
    if (!$stmtDup) {
        echo json_encode(['success' => false, 'message' => 'Error al validar datos: ' . $conn->error]);
        exit;
    }
    $stmtDup->bind_param('ss', $email, $docNormalizado);
} else {
    $stmtDup = $conn->prepare("SELECT id FROM clientes WHERE LOWER(TRIM(email)) = LOWER(?) LIMIT 1");
    if (!$stmtDup) {
        echo json_encode(['success' => false, 'message' => 'Error al validar datos: ' . $conn->error]);
        exit;
    }
    $stmtDup->bind_param('s', $email);
}

$stmtDup->execute();
$resDup = $stmtDup->get_result();
if ($resDup && $resDup->num_rows > 0) {
    $stmtDup->close();
    echo json_encode([
        'success' => false,
        'message' => 'El correo o el documento de identidad ya está registrado.',
    ]);
    exit;
}
$stmtDup->close();

$tipo_db = $tipo_doc === '' ? null : $tipo_doc;
$nro_db = $nro_doc === '' ? null : $nro_doc;
$password_segura = password_hash($pass_raw, PASSWORD_DEFAULT);

$sql = "INSERT INTO clientes (nombre, tipo_documento, numero_documento, telefono, email, password) VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $conn->error]);
    exit;
}

$stmt->bind_param('ssssss', $nombre, $tipo_db, $nro_db, $combinedTel, $email, $password_segura);

if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => 'Registro completado. ¡Bienvenido a INVERCLINIK!',
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Error en el servidor: ' . $stmt->error,
    ]);
}
$stmt->close();
$conn->close();
