<?php
/**
 * Entrega el archivo de comprobante solo al cliente dueño de la cotización.
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once dirname(__DIR__) . '/connection/connection.php';

$id_cliente = isset($_SESSION['id_cliente']) ? (int) $_SESSION['id_cliente'] : 0;
$id_cot = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($id_cliente <= 0 || $id_cot <= 0) {
    http_response_code(403);
    echo 'Acceso no autorizado';
    exit;
}

$stmt = $conn->prepare(
    'SELECT comprobante_archivo FROM cotizaciones WHERE id_cotizacion = ? AND id_cliente = ?'
);
$stmt->bind_param('ii', $id_cot, $id_cliente);
$stmt->execute();
$res = $stmt->get_result();
if (!$res || $res->num_rows === 0) {
    $stmt->close();
    http_response_code(404);
    echo 'Cotización no encontrada';
    exit;
}
$row = $res->fetch_assoc();
$stmt->close();

$nombre = trim((string) ($row['comprobante_archivo'] ?? ''));
if ($nombre === '' || preg_match('/[\\\\\\/]/', $nombre)) {
    http_response_code(404);
    echo 'No hay archivo registrado';
    exit;
}

$base = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprobantes_cotizaciones' . DIRECTORY_SEPARATOR;
$ruta = $base . $nombre;
if (!is_file($ruta)) {
    http_response_code(404);
    echo 'Archivo no encontrado';
    exit;
}

$ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
$tipos = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'webp' => 'image/webp',
];
$mime = $tipos[$ext] ?? 'application/octet-stream';

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($ruta));
header('Content-Disposition: inline; filename="' . basename($nombre) . '"');
header('X-Content-Type-Options: nosniff');
readfile($ruta);
exit;
