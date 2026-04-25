<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../lib/RespaldoBdService.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['iduser']) || (int) ($_SESSION['role_id'] ?? 0) !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'listar') {
        if (RespaldoBdService::tablaRespaldosExiste($conn)) {
            $rows = RespaldoBdService::listarRegistros($conn);
        } else {
            $rows = RespaldoBdService::listarDesdeDisco();
        }
        echo json_encode(['success' => true, 'files' => $rows]);
        $conn->close();
        exit;
    }

    if ($action === 'crear') {
        $uid = isset($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
        if ($uid <= 0) {
            $uid = null;
        }
        $result = RespaldoBdService::crearRespaldo($conn, $host, $user, $pass, $db, 'manual', $uid);
        echo json_encode($result);
        $conn->close();
        exit;
    }

    if ($action === 'restaurar') {
        $respaldoId = isset($_POST['respaldo_id']) ? (int) $_POST['respaldo_id'] : 0;
        $respaldoFile = isset($_POST['respaldo_file']) ? (string) $_POST['respaldo_file'] : '';
        if ($respaldoId > 0 && RespaldoBdService::tablaRespaldosExiste($conn)) {
            $result = RespaldoBdService::restaurarDesdeRegistro($conn, $host, $user, $pass, $db, $respaldoId);
        } else {
            $result = RespaldoBdService::restaurarDesdeArchivo($host, $user, $pass, $db, $respaldoFile);
        }
        echo json_encode($result);
        $conn->close();
        exit;
    }

    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no reconocida.']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
