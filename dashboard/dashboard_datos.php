<?php
require_once "../connection/connection.php";

$response = [];

// --- 1. KPIs (TARJETAS) ---
// Órdenes activas: Las que están en 'pendiente' o 'en_proceso'
// Retrasos: Las que no están 'finalizado' ni 'cancelado' y ya pasó su fecha
$sql_kpis = "SELECT 
    (SELECT COUNT(*) FROM ordenes_produccion WHERE estado IN ('pendiente', 'en_proceso')) as activas,
    (SELECT COUNT(*) FROM ordenes_produccion WHERE fecha_fin < CURDATE() AND estado NOT IN ('finalizado', 'cancelado')) as retrasos,
    (SELECT COUNT(*) FROM ventas WHERE estado = 'Pendiente') as v_pendientes,
    (SELECT COUNT(*) FROM compras WHERE estado = 'Pendiente') as c_pagar,
    (SELECT COUNT(*) FROM inventario inner join insumos on inventario.insumo_id = insumos.id WHERE stock_actual <= 10) as bajos";

$res_kpis = $conn->query($sql_kpis);
$response['kpis'] = $res_kpis->fetch_assoc();

// --- 2. GRÁFICO DE TORTA (PRODUCTOS) ---
$sql_pie = "SELECT p.nombre, COUNT(op.id) as total 
            FROM ordenes_produccion op 
            INNER JOIN recetas_productos rp ON op.receta_producto_id = rp.id
            INNER JOIN productos p ON rp.producto_id = p.id
            WHERE op.fecha_fin < CURDATE()
            GROUP BY p.nombre
            liMIT 5";
$res_pie = $conn->query($sql_pie);
$response['pie_chart'] = $res_pie->fetch_all(MYSQLI_ASSOC);

// --- 3. GRÁFICO DE BARRAS (ESTADOS REALES) ---
$sql_bar = "SELECT estado, COUNT(*) as total 
            FROM ordenes_produccion 
            GROUP BY estado";
$res_bar = $conn->query($sql_bar);
$response['bar_chart'] = $res_bar->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($response);