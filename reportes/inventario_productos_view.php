<?php
$sin_sidebar = true;
require_once('../template/header.php');
require_once __DIR__ . '/../lib/InventarioLotes.php';
InventarioLotes::asegurar($conn);

$where = [];

$producto_id = isset($_GET['producto_id']) ? (int) $_GET['producto_id'] : 0;
$stock_min = isset($_GET['stock_min']) ? trim($_GET['stock_min']) : '';
$stock_max = isset($_GET['stock_max']) ? trim($_GET['stock_max']) : '';

$tieneNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

if ($tieneNuevo) {
    $where[] = "inv.tipo_item = 'producto'";
    if ($producto_id > 0) {
        $where[] = 'p.id = ' . $producto_id;
    }
    if ($stock_min !== '') {
        $stock_min = $conn->real_escape_string($stock_min);
        $where[] = "inv.stock_actual >= '$stock_min'";
    }
    if ($stock_max !== '') {
        $stock_max = $conn->real_escape_string($stock_max);
        $where[] = "inv.stock_actual <= '$stock_max'";
    }
    $condiciones = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "
    SELECT
        inv.tipo_item_id AS id,
        inv.ultima_actualizacion,
        CONCAT(p.nombre, ' · ', COALESCE(rt.nombre_rango, ''), ' · ', COALESCE(tp.nombre, '')) AS producto,
        inv.stock_actual,
        (SELECT GROUP_CONCAT(l.numero_lote ORDER BY l.fecha_entrada DESC, l.id DESC SEPARATOR ', ')
         FROM inventario_lotes l
         WHERE l.tipo_item = 'producto' AND l.tipo_item_id = inv.tipo_item_id AND l.cantidad_restante > 0) AS lotes
    FROM inventario inv
    INNER JOIN recetas r ON r.id = inv.tipo_item_id
    INNER JOIN productos p ON p.id = r.producto_id
    LEFT JOIN rangos_tallas rt ON rt.id = r.rango_tallas_id
    LEFT JOIN tipos_produccion tp ON tp.id = r.tipo_produccion_id
    $condiciones
    ORDER BY p.nombre ASC
    ";
} else {
    if ($producto_id > 0) {
        $where[] = 'ip.producto_id = ' . $producto_id;
    }
    if ($stock_min !== '') {
        $stock_min = $conn->real_escape_string($stock_min);
        $where[] = "ip.stock_actual >= '$stock_min'";
    }
    if ($stock_max !== '') {
        $stock_max = $conn->real_escape_string($stock_max);
        $where[] = "ip.stock_actual <= '$stock_max'";
    }
    $condiciones = count($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "
    SELECT
        ip.producto_id AS id,
        ip.ultima_actualizacion,
        p.nombre AS producto,
        ip.stock_actual,
        NULL AS lotes
    FROM inventario_productos ip
    JOIN productos p ON p.id = ip.producto_id
    $condiciones
    ORDER BY p.nombre ASC
    ";
}

$result = $conn->query($sql);
?>

<div class="contenido-principal">

<div class="reporte-header">
    <div class="logo-info">
        <img src="../assets/img/inverclinik_3.png" alt="Logo INVERCLINIK">
        <div class="empresa-fecha">
            <h1>INVERCLINIK</h1>
            <p><?= date('d/m/Y') ?></p>
        </div>
    </div>
    <div class="titulo-reporte">
        <h2>Inventario de Productos Terminados</h2>
    </div>
</div>

<?php if ($result && $result->num_rows > 0): ?>

<table class="table">

<thead>
<tr>
<th>#</th>
<th>Última Actualización</th>
<th>Producto</th>
<th>Stock Actual</th>
<th>Lote</th>
</tr>
</thead>

<tbody>

<?php $i = 1; ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr>
<td><?= $i++ ?></td>
<td><?= htmlspecialchars($row['ultima_actualizacion'] ?? '—') ?></td>
<td><?= htmlspecialchars($row['producto']) ?></td>
<td><?= htmlspecialchars($row['stock_actual'] ?? '0') ?></td>
<td style="text-align:left; font-size:12px;"><?= htmlspecialchars($row['lotes'] ?? '—') ?></td>
</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
No existen productos terminados en inventario con esos filtros.
</div>

<?php endif; ?>

<div class="reporte-footer">
Generado el <?= date('d/m/Y \a \l\a\s h:i A') ?>
</div>

</div>

<?php require_once('../template/footer.php'); ?>
