<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['iduser']) || (int) ($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.0 403 Forbidden');
    exit;
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../connection/connection.php';

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$f = isset($_GET['f']) ? basename((string) $_GET['f']) : '';

$nombreArchivo = '';

if ($id > 0) {
    $stmt = $conn->prepare('SELECT nombre_archivo FROM respaldos_bd WHERE id = ? LIMIT 1');
    if ($stmt === false) {
        header('HTTP/1.0 500 Internal Server Error');
        exit;
    }
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row && isset($row['nombre_archivo'])) {
        $nombreArchivo = (string) $row['nombre_archivo'];
    }
} elseif ($f !== '' && preg_match('/^[a-zA-Z0-9._-]+\.sql$/', $f)) {
    $stmt = $conn->prepare('SELECT nombre_archivo FROM respaldos_bd WHERE nombre_archivo = ? LIMIT 1');
    if ($stmt === false) {
        header('HTTP/1.0 500 Internal Server Error');
        exit;
    }
    $stmt->bind_param('s', $f);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if ($row && isset($row['nombre_archivo'])) {
        $nombreArchivo = (string) $row['nombre_archivo'];
    }
}

if ($nombreArchivo === '') {
    header('HTTP/1.0 404 Not Found');
    $conn->close();
    exit;
}

$f = basename($nombreArchivo);
if (!preg_match('/^[a-zA-Z0-9._-]+\.sql$/', $f)) {
    header('HTTP/1.0 400 Bad Request');
    $conn->close();
    exit;
}

$path = ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'respaldos_bd' . DIRECTORY_SEPARATOR . $f;
if (!is_file($path) || !is_readable($path)) {
    header('HTTP/1.0 404 Not Found');
    $conn->close();
    exit;
}

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . str_replace('"', '', $f) . '"');
header('Content-Length: ' . (string) filesize($path));
readfile($path);
$conn->close();
exit;
