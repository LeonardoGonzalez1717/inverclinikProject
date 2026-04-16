<?php

declare(strict_types=1);

require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../lib/TasaCambiariaService.php';
require_once __DIR__ . '/../lib/Auditoria.php';

date_default_timezone_set('America/Caracas');

$resultado = TasaCambiariaService::actualizarProgramadaVz($conn, null);

$marca = date('Y-m-d H:i:s');
$salida = sprintf(
    "[%s] success=%s updated=%s mensaje=%s",
    $marca,
    $resultado['success'] ? '1' : '0',
    $resultado['updated'] ? '1' : '0',
    $resultado['message'] ?? ''
);

echo $salida . PHP_EOL;

if (isset($resultado['tasa'])) {
    echo "Tasa registrada: " . $resultado['tasa'] . PHP_EOL;
}

// Auditoria tecnica en archivo local
$logsDir = __DIR__ . '/../logs';
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0777, true);
}
$logFile = $logsDir . '/tasa_programada.log';
@file_put_contents($logFile, $salida . PHP_EOL, FILE_APPEND);

// Auditoria funcional en base de datos (tabla auditoria)
$mensajeAuditoria = 'Ejecucion programada BCV. ' . ($resultado['message'] ?? '');
if (isset($resultado['tasa'])) {
    $mensajeAuditoria .= ' Tasa=' . $resultado['tasa'];
}
Auditoria::registrar(
    $conn,
    $mensajeAuditoria,
    'Tasas cambiarias',
    [
        'nombre_actor' => 'Tarea programada',
        'id_usuario' => null,
        'id_cliente' => null
    ]
);

$conn->close();
