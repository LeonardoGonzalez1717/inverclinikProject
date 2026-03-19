<?php
$sin_sidebar = true;
require_once('../template/header.php');

$where = [];

$producto = isset($_GET['producto']) ? trim($_GET['producto']) : '';
$stock_min = isset($_GET['stock_min']) ? trim($_GET['stock_min']) : '';
$stock_max = isset($_GET['stock_max']) ? trim($_GET['stock_max']) : '';

if (!empty($producto)) {
    $producto = $conn->real_escape_string($producto);
    $where[] = "p.nombre LIKE '%$producto%'";
}

if ($stock_min !== '') {
    $stock_min = $conn->real_escape_string($stock_min);
    $where[] = "ip.stock_actual >= '$stock_min'";
}

if ($stock_max !== '') {
    $stock_max = $conn->real_escape_string($stock_max);
    $where[] = "ip.stock_actual <= '$stock_max'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT
    ip.producto_id AS id,
    ip.ultima_actualizacion,
    p.nombre AS producto,
    ip.stock_actual
FROM inventario_productos ip
JOIN productos p ON p.id = ip.producto_id
$condiciones
ORDER BY p.nombre ASC
";

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
Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
</div>

</div>

<?php require_once('../template/footer.php'); ?>