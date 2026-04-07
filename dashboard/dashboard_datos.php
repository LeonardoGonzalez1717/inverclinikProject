<?php
require_once "../connection/connection.php";

$response = [];

// --- 1. KPIs (TARJETAS) ---
// Órdenes activas: Las que están en 'pendiente' o 'en_proceso'
// Retrasos: Las que no están 'finalizado' ni 'cancelado' y ya pasó su fecha
$sql_kpis = "SELECT 
    (SELECT COUNT(*) FROM ordenes_produccion ) as activas,
    (SELECT COUNT(*) FROM ordenes_produccion WHERE fecha_fin < CURDATE() AND estado NOT IN ('finalizado', 'cancelado')) as retrasos,

    (SELECT COUNT(*) FROM cotizaciones WHERE status = 1) as coti_pendientes_cant,
    (SELECT IFNULL(SUM(total), 0) FROM cotizaciones WHERE status = 1) as coti_pendientes_monto,
    (SELECT COUNT(*) FROM ventas WHERE estado = 'pendiente') as v_pendientes,
    (SELECT IFNULL(SUM(total), 0) FROM ventas WHERE estado = 'pendiente') as ventas_pendientes_monto,

    (SELECT COUNT(*) FROM inventario inner join insumos on inventario.tipo_item_id = insumos.id 
        WHERE tipo_item = 'insumo' AND stock_actual <= stock_minimo) as insumos_bajos,
    (SELECT COUNT(*) FROM inventario inner join recetas on inventario.tipo_item_id = recetas.id 
        WHERE tipo_item = 'producto' AND stock_actual <= stock_minimo) as productos_bajos";

$res_kpis = $conn->query($sql_kpis);
$response['kpis'] = $res_kpis->fetch_assoc();

// --- 2. PRODUCTOS MÁS VENDIDOS (PARA EL GRÁFICO DE DONA) ---
// Consultamos el detalle de los presupuestos/ventas para ver qué producto se mueve más
$sql_top = "SELECT p.nombre, IFNULL(SUM(pd.cantidad), 0) as total_vendido
            FROM productos p
            INNER JOIN recetas r ON p.id = r.producto_id
            LEFT JOIN cotizacion_detalles pd ON r.id = pd.id_receta
            GROUP BY p.id
            ORDER BY total_vendido DESC
            LIMIT 5";
$res_top = $conn->query($sql_top);
$response['productos_top'] = $res_top->fetch_all(MYSQLI_ASSOC);

// --- 3. VENTAS AL MES (PARA EL GRÁFICO DE BARRAS) ---
// Agrupamos por mes para ver la evolución financiera
$sql_ventas = "SELECT 
                CASE MONTH(fecha_creacion)
                    WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo'
                    WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio'
                    WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre'
                    WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre'
                END as mes, 
                SUM(total) as monto_total 
               FROM presupuestos 
               WHERE fecha_creacion >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
               GROUP BY MONTH(fecha_creacion)
               ORDER BY fecha_creacion ASC";
$res_ventas = $conn->query($sql_ventas);
$response['ventas_mes'] = $res_ventas->fetch_all(MYSQLI_ASSOC);

header('Content-Type: application/json');
echo json_encode($response);