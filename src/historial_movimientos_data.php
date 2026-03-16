<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

if ($action !== 'listar_html') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

$tieneRecetaId = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'receta_id'")->num_rows > 0;
if ($tieneRecetaId) {
    $sql = "
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
        ORDER BY inv.fecha_movimiento DESC, inv.id DESC
    ";
} else {
    $sql = "
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
        ORDER BY inv.fecha_movimiento DESC, inv.id DESC
    ";
}

$result = $conn->query($sql);
$filas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $filas[] = $row;
    }
}

$i = 0;
if (!empty($filas)) {
    foreach ($filas as $r) {
        $i++;
        $fecha = $r['fecha_movimiento'] ? date('d/m/Y H:i', strtotime($r['fecha_movimiento'])) : '—';
        $tipoItem = $r['tipo_item'] === 'insumo' ? 'Insumo' : 'Producto';
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
        $movimiento = $r['tipo'] === 'entrada' ? 'Entrada' : 'Salida';
        $badgeClass = $r['tipo'] === 'entrada' ? 'badge-entrada' : 'badge-salida';
        $origen = htmlspecialchars($r['origen'] ?? 'manual');
        $observaciones = htmlspecialchars(substr($r['observaciones'] ?? '', 0, 80));
        if (strlen($r['observaciones'] ?? '') > 80) {
            $observaciones .= '…';
        }
        $ordenProd = $r['orden_produccion_id'] ? (int)$r['orden_produccion_id'] : '—';

        echo '<tr>';
        echo '<td>' . $i . '</td>';
        echo '<td>' . $fecha . '</td>';
        echo '<td>' . $tipoItem . '</td>';
        echo '<td>' . $itemNombre . '</td>';
        echo '<td><span class="' . $badgeClass . '">' . $movimiento . '</span></td>';
        echo '<td>' . number_format((float)$r['cantidad'], 2, '.', ',') . '</td>';
        echo '<td>' . $origen . '</td>';
        echo '<td>' . $observaciones . '</td>';
        echo '<td>' . $ordenProd . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="9" class="text-center">No hay movimientos registrados.</td></tr>';
}

$conn->close();
