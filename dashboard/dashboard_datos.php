<?php
require_once "../connection/connection.php";

$response = [];

$raw_desde = isset($_GET['fecha_desde']) ? trim((string) $_GET['fecha_desde']) : '';
$raw_hasta = isset($_GET['fecha_hasta']) ? trim((string) $_GET['fecha_hasta']) : '';

function fecha_valida(?string $s): ?string
{
    if ($s === null || $s === '') {
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $s);
    if ($d && $d->format('Y-m-d') === $s) {
        return $s;
    }
    return null;
}

$v_desde = fecha_valida($raw_desde !== '' ? $raw_desde : null);
$v_hasta = fecha_valida($raw_hasta !== '' ? $raw_hasta : null);

$modo = 'ninguno';
$fd = null;
$fh = null;

if ($v_desde !== null && $v_hasta !== null) {
    $modo = 'rango';
    $d1 = DateTime::createFromFormat('Y-m-d', $v_desde);
    $d2 = DateTime::createFromFormat('Y-m-d', $v_hasta);
    if ($d1 > $d2) {
        $fd = $v_hasta;
        $fh = $v_desde;
    } else {
        $fd = $v_desde;
        $fh = $v_hasta;
    }
} elseif ($v_desde !== null) {
    $modo = 'desde';
    $fd = $v_desde;
} elseif ($v_hasta !== null) {
    $modo = 'hasta';
    $fh = $v_hasta;
}

$filtrar = $modo !== 'ninguno';

$response['filtro'] = [
    'modo_fecha' => $modo,
    'aplicar_filtro_fecha' => $filtrar,
    'fecha_desde' => $fd ?? '',
    'fecha_hasta' => $fh ?? '',
];

// --- 1. KPIs (TARJETAS) ---
if (!$filtrar) {
    $sql_kpis = "SELECT 
        (SELECT COUNT(*) FROM ordenes_produccion ) as activas,
        (SELECT COUNT(*) FROM ordenes_produccion WHERE fecha_fin < CURDATE() AND estado NOT IN ('finalizado', 'cancelado')) as retrasos,

        (SELECT COUNT(*) FROM cotizaciones WHERE status = 1) as coti_pendientes_cant,
        (SELECT IFNULL(SUM(total), 0) FROM cotizaciones WHERE status = 1) as coti_pendientes_monto,
        (SELECT COUNT(*) FROM ventas WHERE estado IN ('pendiente', 'por_pagar')) as v_pendientes,
        (SELECT IFNULL(SUM(total), 0) FROM ventas WHERE estado IN ('pendiente', 'por_pagar')) as ventas_pendientes_monto,

        (SELECT COUNT(*) FROM inventario inner join insumos on inventario.tipo_item_id = insumos.id 
            WHERE tipo_item = 'insumo' AND stock_actual <= stock_minimo) as insumos_bajos,
        (SELECT COUNT(*) FROM inventario inner join recetas on inventario.tipo_item_id = recetas.id 
            WHERE tipo_item = 'producto' AND stock_actual <= stock_minimo) as productos_bajos";

    $res_kpis = $conn->query($sql_kpis);
    $response['kpis'] = $res_kpis->fetch_assoc();
} elseif ($modo === 'rango') {
    $sql_kpis = "SELECT 
        (SELECT COUNT(*) FROM ordenes_produccion ) as activas,
        (SELECT COUNT(*) FROM ordenes_produccion WHERE fecha_fin < CURDATE() AND estado NOT IN ('finalizado', 'cancelado')) as retrasos,

        (SELECT COUNT(*) FROM cotizaciones WHERE status = 1 AND DATE(fecha_registro) BETWEEN ? AND ?) as coti_pendientes_cant,
        (SELECT IFNULL(SUM(total), 0) FROM cotizaciones WHERE status = 1 AND DATE(fecha_registro) BETWEEN ? AND ?) as coti_pendientes_monto,
        (SELECT COUNT(*) FROM ventas WHERE estado IN ('pendiente', 'por_pagar') AND fecha BETWEEN ? AND ?) as v_pendientes,
        (SELECT IFNULL(SUM(total), 0) FROM ventas WHERE estado IN ('pendiente', 'por_pagar') AND fecha BETWEEN ? AND ?) as ventas_pendientes_monto,

        (SELECT COUNT(*) FROM inventario inner join insumos on inventario.tipo_item_id = insumos.id 
            WHERE tipo_item = 'insumo' AND stock_actual <= stock_minimo) as insumos_bajos,
        (SELECT COUNT(*) FROM inventario inner join recetas on inventario.tipo_item_id = recetas.id 
            WHERE tipo_item = 'producto' AND stock_actual <= stock_minimo) as productos_bajos";

    $stmt_kpis = $conn->prepare($sql_kpis);
    $stmt_kpis->bind_param('ssssssss', $fd, $fh, $fd, $fh, $fd, $fh, $fd, $fh);
    $stmt_kpis->execute();
    $response['kpis'] = $stmt_kpis->get_result()->fetch_assoc();
    $stmt_kpis->close();
} elseif ($modo === 'desde') {
    $sql_kpis = "SELECT 
        (SELECT COUNT(*) FROM ordenes_produccion ) as activas,
        (SELECT COUNT(*) FROM ordenes_produccion WHERE fecha_fin < CURDATE() AND estado NOT IN ('finalizado', 'cancelado')) as retrasos,

        (SELECT COUNT(*) FROM cotizaciones WHERE status = 1 AND DATE(fecha_registro) >= ?) as coti_pendientes_cant,
        (SELECT IFNULL(SUM(total), 0) FROM cotizaciones WHERE status = 1 AND DATE(fecha_registro) >= ?) as coti_pendientes_monto,
        (SELECT COUNT(*) FROM ventas WHERE estado IN ('pendiente', 'por_pagar') AND fecha >= ?) as v_pendientes,
        (SELECT IFNULL(SUM(total), 0) FROM ventas WHERE estado IN ('pendiente', 'por_pagar') AND fecha >= ?) as ventas_pendientes_monto,

        (SELECT COUNT(*) FROM inventario inner join insumos on inventario.tipo_item_id = insumos.id 
            WHERE tipo_item = 'insumo' AND stock_actual <= stock_minimo) as insumos_bajos,
        (SELECT COUNT(*) FROM inventario inner join recetas on inventario.tipo_item_id = recetas.id 
            WHERE tipo_item = 'producto' AND stock_actual <= stock_minimo) as productos_bajos";

    $stmt_kpis = $conn->prepare($sql_kpis);
    $stmt_kpis->bind_param('ssss', $fd, $fd, $fd, $fd);
    $stmt_kpis->execute();
    $response['kpis'] = $stmt_kpis->get_result()->fetch_assoc();
    $stmt_kpis->close();
} else {
    /* hasta */
    $sql_kpis = "SELECT 
        (SELECT COUNT(*) FROM ordenes_produccion ) as activas,
        (SELECT COUNT(*) FROM ordenes_produccion WHERE fecha_fin < CURDATE() AND estado NOT IN ('finalizado', 'cancelado')) as retrasos,

        (SELECT COUNT(*) FROM cotizaciones WHERE status = 1 AND DATE(fecha_registro) <= ?) as coti_pendientes_cant,
        (SELECT IFNULL(SUM(total), 0) FROM cotizaciones WHERE status = 1 AND DATE(fecha_registro) <= ?) as coti_pendientes_monto,
        (SELECT COUNT(*) FROM ventas WHERE estado IN ('pendiente', 'por_pagar') AND fecha <= ?) as v_pendientes,
        (SELECT IFNULL(SUM(total), 0) FROM ventas WHERE estado IN ('pendiente', 'por_pagar') AND fecha <= ?) as ventas_pendientes_monto,

        (SELECT COUNT(*) FROM inventario inner join insumos on inventario.tipo_item_id = insumos.id 
            WHERE tipo_item = 'insumo' AND stock_actual <= stock_minimo) as insumos_bajos,
        (SELECT COUNT(*) FROM inventario inner join recetas on inventario.tipo_item_id = recetas.id 
            WHERE tipo_item = 'producto' AND stock_actual <= stock_minimo) as productos_bajos";

    $stmt_kpis = $conn->prepare($sql_kpis);
    $stmt_kpis->bind_param('ssss', $fh, $fh, $fh, $fh);
    $stmt_kpis->execute();
    $response['kpis'] = $stmt_kpis->get_result()->fetch_assoc();
    $stmt_kpis->close();
}

// --- 2. PRODUCTOS MÁS VENDIDOS ---
$sql_top_base = "SELECT p.nombre, IFNULL(SUM(pd.cantidad), 0) as total_vendido
            FROM productos p
            INNER JOIN recetas r ON p.id = r.producto_id
            INNER JOIN cotizacion_detalles pd ON r.id = pd.id_receta
            INNER JOIN cotizaciones c ON pd.id_cotizacion = c.id_cotizacion ";

if (!$filtrar) {
    $sql_top = $sql_top_base . "GROUP BY p.id, p.nombre ORDER BY total_vendido DESC LIMIT 5";
    $response['productos_top'] = $conn->query($sql_top)->fetch_all(MYSQLI_ASSOC);
} elseif ($modo === 'rango') {
    $sql_top = $sql_top_base . "WHERE DATE(c.fecha_registro) BETWEEN ? AND ? GROUP BY p.id, p.nombre ORDER BY total_vendido DESC LIMIT 5";
    $st = $conn->prepare($sql_top);
    $st->bind_param('ss', $fd, $fh);
    $st->execute();
    $response['productos_top'] = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
} elseif ($modo === 'desde') {
    $sql_top = $sql_top_base . "WHERE DATE(c.fecha_registro) >= ? GROUP BY p.id, p.nombre ORDER BY total_vendido DESC LIMIT 5";
    $st = $conn->prepare($sql_top);
    $st->bind_param('s', $fd);
    $st->execute();
    $response['productos_top'] = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
} else {
    $sql_top = $sql_top_base . "WHERE DATE(c.fecha_registro) <= ? GROUP BY p.id, p.nombre ORDER BY total_vendido DESC LIMIT 5";
    $st = $conn->prepare($sql_top);
    $st->bind_param('s', $fh);
    $st->execute();
    $response['productos_top'] = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

// --- 3. PRESUPUESTOS POR MES ---
$sql_ventas_sel = "SELECT 
                CONCAT(
                    CASE MONTH(fecha_creacion)
                        WHEN 1 THEN 'Enero' WHEN 2 THEN 'Febrero' WHEN 3 THEN 'Marzo'
                        WHEN 4 THEN 'Abril' WHEN 5 THEN 'Mayo' WHEN 6 THEN 'Junio'
                        WHEN 7 THEN 'Julio' WHEN 8 THEN 'Agosto' WHEN 9 THEN 'Septiembre'
                        WHEN 10 THEN 'Octubre' WHEN 11 THEN 'Noviembre' WHEN 12 THEN 'Diciembre'
                    END,
                    ' ',
                    YEAR(fecha_creacion)
                ) as mes, 
                SUM(total) as monto_total 
               FROM presupuestos ";

if (!$filtrar) {
    $sql_ventas = $sql_ventas_sel . "GROUP BY YEAR(fecha_creacion), MONTH(fecha_creacion)
               ORDER BY YEAR(fecha_creacion), MONTH(fecha_creacion)";
    $response['ventas_mes'] = $conn->query($sql_ventas)->fetch_all(MYSQLI_ASSOC);
} elseif ($modo === 'rango') {
    $sql_ventas = $sql_ventas_sel . "WHERE DATE(fecha_creacion) BETWEEN ? AND ? GROUP BY YEAR(fecha_creacion), MONTH(fecha_creacion)
               ORDER BY YEAR(fecha_creacion), MONTH(fecha_creacion)";
    $st = $conn->prepare($sql_ventas);
    $st->bind_param('ss', $fd, $fh);
    $st->execute();
    $response['ventas_mes'] = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
} elseif ($modo === 'desde') {
    $sql_ventas = $sql_ventas_sel . "WHERE DATE(fecha_creacion) >= ? GROUP BY YEAR(fecha_creacion), MONTH(fecha_creacion)
               ORDER BY YEAR(fecha_creacion), MONTH(fecha_creacion)";
    $st = $conn->prepare($sql_ventas);
    $st->bind_param('s', $fd);
    $st->execute();
    $response['ventas_mes'] = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
} else {
    $sql_ventas = $sql_ventas_sel . "WHERE DATE(fecha_creacion) <= ? GROUP BY YEAR(fecha_creacion), MONTH(fecha_creacion)
               ORDER BY YEAR(fecha_creacion), MONTH(fecha_creacion)";
    $st = $conn->prepare($sql_ventas);
    $st->bind_param('s', $fh);
    $st->execute();
    $response['ventas_mes'] = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
}

header('Content-Type: application/json');
echo json_encode($response);
