<?php

declare(strict_types=1);

/**
 * Ejecución por consola (Programador de tareas de Windows o cron en Linux).
 * No usar desde el navegador: use la pantalla Respaldos o respaldos_bd_data.php.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Este script solo puede ejecutarse por línea de comandos.';
    exit(1);
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../lib/RespaldoBdService.php';
require_once __DIR__ . '/../lib/Auditoria.php';

date_default_timezone_set('America/Caracas');

$resultado = RespaldoBdService::crearRespaldo($conn, $host, $user, $pass, $db, 'automatico', null);

$marca = date('Y-m-d H:i:s');
$salida = sprintf(
    "[%s] success=%s skipped=%s mensaje=%s",
    $marca,
    $resultado['success'] ? '1' : '0',
    !empty($resultado['skipped']) ? '1' : '0',
    $resultado['message'] ?? ''
);

if (!empty($resultado['filename'])) {
    $salida .= sprintf(' archivo=%s tamaño=%d', $resultado['filename'], (int) ($resultado['size'] ?? 0));
}

echo $salida . PHP_EOL;

$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0777, true);
}
$logFile = $logsDir . '/respaldos_bd_diario.log';
@file_put_contents($logFile, $salida . PHP_EOL, FILE_APPEND);

if (empty($resultado['skipped'])) {
    $mensajeAuditoria = 'Respaldo automático diario. ' . ($resultado['message'] ?? '');
    if (!empty($resultado['filename'])) {
        $mensajeAuditoria .= ' Archivo=' . $resultado['filename'];
    }
    Auditoria::registrar(
        $conn,
        $mensajeAuditoria,
        'Respaldos BD',
        [
            'nombre_actor' => 'Tarea programada',
            'id_usuario' => null,
            'id_cliente' => null,
        ]
    );
}

$conn->close();

exit($resultado['success'] ? 0 : 1);
