<?php
$sin_sidebar = true;
require_once('../template/header.php');

$where = [];

$producto = isset($_GET['producto']) ? trim($_GET['producto']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';
$fi = isset($_GET['fecha_inicio']) ? trim($_GET['fecha_inicio']) : '';
$ff = isset($_GET['fecha_fin']) ? trim($_GET['fecha_fin']) : '';

if (!empty($producto)) {
    $producto = $conn->real_escape_string($producto);
    $where[] = "p.nombre LIKE '%$producto%'";
}

if (!empty($estado)) {
    $estado = $conn->real_escape_string($estado);
    $where[] = "op.estado = '$estado'";
}

if (!empty($fi) && !empty($ff)) {
    $fi = $conn->real_escape_string($fi);
    $ff = $conn->real_escape_string($ff);
    $where[] = "op.fecha_inicio BETWEEN '$fi' AND '$ff'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT 
op.id,
p.nombre AS producto,
op.cantidad_a_producir,
op.fecha_inicio,
op.fecha_fin,
op.estado,
u.username AS responsable,
op.observaciones
FROM ordenes_produccion op
JOIN recetas_productos rp ON rp.id = op.receta_producto_id
JOIN productos p ON p.id = rp.producto_id
LEFT JOIN users u ON u.id = op.usuario_id
$condiciones
ORDER BY op.fecha_inicio DESC
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
<h2>Reporte de Órdenes de Producción</h2>
</div>

</div>

<?php if ($result->num_rows > 0): ?>

<table class="table">

<thead>
<tr>
<th>#</th>
<th>Producto</th>
<th>Cantidad</th>
<th>Inicio</th>
<th>Fin</th>
<th>Estado</th>
<th>Responsable</th>
<th>Observaciones</th>
</tr>
</thead>

<tbody>

<?php $i = 1 ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr>

<td style="text-align:right"><?= $i++ ?></td>
<td style="text-align:left"><?= htmlspecialchars($row['producto']) ?></td>
<td style="text-align:right"><?= $row['cantidad_a_producir'] ?></td>
<td><?= $row['fecha_inicio'] ?></td>
<td><?= $row['fecha_fin'] ?></td>
<td><?= $row['estado'] ?></td>
<td><?= $row['responsable'] ?? '—' ?></td>
<td><?= htmlspecialchars($row['observaciones']) ?></td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
No se encontraron órdenes de producción con los filtros seleccionados.
</div>

<?php endif; ?>

<div class="reporte-footer">
Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
</div>

</div>

<?php require_once('../template/footer.php'); ?>