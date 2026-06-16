<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Pagination.php';

function html_origen_movimiento_detalle($codigo)
{
    $c = ($codigo === null || $codigo === '') ? 'manual' : $codigo;
    switch ($c) {
        case 'compra':
            return '<span class="badge" style="background-color: #e3f2fd; color: #0d47a1; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;"> Compra</span>';
        case 'orden_produccion':
            return '<span class="badge" style="background-color: #e8f5e9; color: #1b5e20; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;"> Prod. Inverclinik</span>';
        case 'ajuste':
            return '<span class="badge" style="background-color: #fff3e0; color: #e65100; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;"> Ajuste</span>';
        default:
            return '<span class="badge" style="background-color: #f5f5f5; color: #424242; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; display: inline-block;"> Manual</span>';
    }
}

$action = $_POST['action'] ?? '';

if ($action !== 'listar_html') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

$buscar_item = isset($_POST['buscar_item']) ? trim($_POST['buscar_item']) : '';
$tipo_item = isset($_POST['tipo_item']) ? trim($_POST['tipo_item']) : '';
$tipo_movimiento = isset($_POST['tipo_movimiento']) ? trim($_POST['tipo_movimiento']) : '';
$fecha_desde = isset($_POST['fecha_desde']) ? trim($_POST['fecha_desde']) : '';
$fecha_hasta = isset($_POST['fecha_hasta']) ? trim($_POST['fecha_hasta']) : '';

$where = [];

if ($buscar_item !== '') {
    $search = $conn->real_escape_string($buscar_item);
    $where[] = "(i.nombre LIKE '%$search%' OR p.nombre LIKE '%$search%')";
}

if ($tipo_item !== '') {
    $tItem = $conn->real_escape_string($tipo_item);
    $where[] = "inv.tipo_item = '$tItem'";
}

if ($tipo_movimiento !== '') {
    $tMov = $conn->real_escape_string($tipo_movimiento);
    $where[] = "inv.tipo = '$tMov'";
}

if ($fecha_desde !== '') {
    $fDesde = $conn->real_escape_string($fecha_desde);
    $where[] = "inv.fecha_movimiento >= '$fDesde 00:00:00'";
}

if ($fecha_hasta !== '') {
    $fHasta = $conn->real_escape_string($fecha_hasta);
    $where[] = "inv.fecha_movimiento <= '$fHasta 23:59:59'";
}

$fil = "";
if (count($where) > 0) {
    $fil = " WHERE " . implode(" AND ", $where);
}


$tieneRecetaId = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'receta_id'")->num_rows > 0;
if ($tieneRecetaId) {
    $sqlBody = "
        SELECT 
            inv.id,
            inv.tipo_item,
            inv.insumo_id,
            inv.receta_id,
            inv.tipo,
            inv.cantidad,
            inv.origen,
            inv.observaciones,
            inv.fecha_movimiento,
            inv.orden_produccion_id,
            i.nombre AS insumo_nombre,
            p.nombre AS producto_nombre,
            rt.nombre_rango AS rango_tallas_nombre,
            tp.nombre AS tipo_produccion_nombre
        FROM inventario_detalle inv
        LEFT JOIN insumos i ON inv.tipo_item = 'insumo' AND inv.insumo_id = i.id
        LEFT JOIN recetas rec ON inv.tipo_item = 'producto' AND inv.receta_id = rec.id
        LEFT JOIN productos p ON rec.producto_id = p.id
        LEFT JOIN rangos_tallas rt ON rec.rango_tallas_id = rt.id
        LEFT JOIN tipos_produccion tp ON rec.tipo_produccion_id = tp.id
    " . $fil; 
} else {
    $sqlBody = "
        SELECT 
            inv.id,
            inv.tipo_item,
            inv.insumo_id,
            inv.producto_id,
            inv.rango_tallas_id,
            inv.tipo_produccion_id,
            inv.tipo,
            inv.cantidad,
            inv.origen,
            inv.observaciones,
            inv.fecha_movimiento,
            inv.orden_produccion_id,
            i.nombre AS insumo_nombre,
            p.nombre AS producto_nombre,
            rt.nombre_rango AS rango_tallas_nombre,
            tp.nombre AS tipo_produccion_nombre
        FROM inventario_detalle inv
        LEFT JOIN insumos i ON inv.tipo_item = 'insumo' AND inv.insumo_id = i.id
        LEFT JOIN productos p ON inv.tipo_item = 'producto' AND inv.producto_id = p.id
        LEFT JOIN rangos_tallas rt ON inv.tipo_item = 'producto' AND inv.rango_tallas_id = rt.id
        LEFT JOIN tipos_produccion tp ON inv.tipo_item = 'producto' AND inv.tipo_produccion_id = tp.id
    " . $fil; 
}

$total = Pagination::countFromSubquery($conn, $sqlBody);
$pg = Pagination::fromInput($total, $_POST);

$sql = $sqlBody . '
    ORDER BY inv.fecha_movimiento DESC, inv.id DESC
' . $pg->limitClause();

$result = $conn->query($sql);
$filas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $filas[] = $row;
    }
}

ob_start();
$i = $pg->rowNumberStart() - 1;
if (!empty($filas)) {
    foreach ($filas as $r) {
        $i++;
        $fecha = $r['fecha_movimiento'] ? date('d/m/Y h:i A', strtotime($r['fecha_movimiento'])) : '—';
        $tipoItem = $r['tipo_item'] === 'insumo' ? '<strong>Insumo</strong>' : '<strong>Producto</strong>';
        
        if ($r['tipo_item'] === 'insumo') {
            $itemNombre = htmlspecialchars($r['insumo_nombre'] ?? '—');
        } else {
            $partes = array_filter([
                $r['producto_nombre'] ?? '',
                $r['rango_tallas_nombre'] ?? '',
                $r['tipo_produccion_nombre'] ?? ''
            ]);
            $itemNombre = htmlspecialchars(implode(' · ', $partes) ?: '—');
        }

        if ($r['tipo'] === 'entrada') {
            $movimientoHtml = '<span  text-center" style="background-color: #198754; color: #ffffff; padding: 4px 8px; border-radius: 6px; font-weight: bold; display: inline-block; width: 95px; text-align: center;"><i class="fas fa-arrow-alt-circle-down"></i> Entrada</span>';
        } else {
            $movimientoHtml = '<span  text-center" style="background-color: #dc3545; color: #ffffff; padding: 4px 8px; border-radius: 6px; font-weight: bold; display: inline-block; width: 95px; text-align: center;"><i class="fas fa-arrow-alt-circle-up"></i> Salida</span>';
        }

        $origenHtml = html_origen_movimiento_detalle($r['origen'] ?? null);
        $observaciones = htmlspecialchars(substr($r['observaciones'] ?? '', 0, 80));
        if (strlen($r['observaciones'] ?? '') > 80) {
            $observaciones .= '…';
        }

        echo '<tr>';
        echo '<td>' . $i . '</td>';
        echo '<td>' . $fecha . '</td>';
        echo '<td>' . $tipoItem . '</td>';
        echo '<td>' . $itemNombre . '</td>';
        echo '<td style="text-align: center; vertical-align: middle;">' . $movimientoHtml . '</td>';
        echo '<td style="text-align: center; vertical-align: middle;">' . $origenHtml . '</td>';
        echo '<td style="text-align: right;">' . number_format((float) $r['cantidad'], 2, '.', ',') . '</td>';
        echo '<td class="text-muted" style="font-size: 12px;">' . $observaciones . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="8" class="text-center text-muted" style="padding: 25px;">No se encontraron movimientos con los filtros aplicados.</td></tr>';
}

$rowsHtml = ob_get_clean();
Pagination::sendJsonList($rowsHtml, $pg);
$conn->close();