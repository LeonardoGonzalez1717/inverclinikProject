<?php
session_start();
require_once "../connection/connection.php";

$id_cliente = $_SESSION['id_cliente'];

if (!$id_cliente) {
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

$response = [];

try {
    $sql_kpis = "SELECT 
        (SELECT COUNT(*) FROM ventas WHERE cliente_id = $id_cliente AND estado NOT IN ('entregado')) as activos,
        
        (SELECT IFNULL(SUM(total), 0) FROM ventas WHERE cliente_id = $id_cliente AND estado = 'pendiente') as saldo_usd,
        
        (SELECT tasa FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1) as tasa_actual,
        
        -- 4. Historial (Entregados)
        (SELECT COUNT(*) FROM ventas WHERE cliente_id = $id_cliente AND estado = 'entregado') as total_finalizados";

    $res_kpis = $conn->query($sql_kpis);
    $response['kpis'] = $res_kpis->fetch_assoc();

    echo json_encode($response);

} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}