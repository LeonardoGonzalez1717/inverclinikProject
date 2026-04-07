<?php

$sin_sidebar = true;
require_once('../template/header.php');

$iduser = $_SESSION['iduser'];

$sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol
FROM users u
LEFT JOIN roles r ON r.id = u.role_id
WHERE u.id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();


$where = [];

$producto_id = isset($_GET['producto_id']) ? (int) $_GET['producto_id'] : 0;
$categoria = isset($_GET['categoria']) ? trim($_GET['categoria']) : '';
$genero = isset($_GET['genero']) ? trim($_GET['genero']) : '';

if ($producto_id > 0) {
    $where[] = 'p.id = ' . $producto_id;
}

if ($categoria !== '') {
    $categoria = $conn->real_escape_string($categoria);
    $where[] = "p.categoria = '$categoria'";
}

if (!empty($genero)) {
    $genero = $conn->real_escape_string($genero);
    $where[] = "p.tipo_genero = '$genero'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "

SELECT 
p.id,
p.nombre,
p.categoria,
p.tipo_genero,
p.descripcion,
p.fecha_creacion

FROM productos p

$condiciones

ORDER BY p.nombre ASC

";

$result = $conn->query($sql);

?>

<div class="contenido-principal">

<div class="reporte-header">

<div class="logo-info">

<img src="../assets/img/inverclinik_3.png">

<div class="empresa-fecha">

<h1>INVERCLINIK</h1>
<p><?= date('d/m/Y') ?></p>

</div>
</div>

<div class="titulo-reporte">
<h2>Reporte de Productos</h2>
</div>

</div>

<?php if ($result->num_rows > 0): ?>

<table class="table">

<thead>

<tr>

<th>#</th>
<th>Nombre</th>
<th>Categoría</th>
<th>Género</th>
<th>Descripción</th>
<th>Fecha</th>

</tr>

</thead>

<tbody>

<?php $i = 1 ?>

<?php while ($row = $result->fetch_assoc()): ?>

<tr>

<td style="text-align:right"><?= $i++ ?></td>

<td style="text-align:left">
<?= htmlspecialchars($row['nombre']) ?>
</td>

<td>
<?= htmlspecialchars($row['categoria']) ?>
</td>

<td>
<?= htmlspecialchars($row['tipo_genero']) ?>
</td>

<td>
<?= htmlspecialchars($row['descripcion']) ?>
</td>

<td style="text-align:right">
<?= htmlspecialchars($row['fecha_creacion']) ?>
</td>

</tr>

<?php endwhile; ?>

</tbody>

</table>

<?php else: ?>

<div class="no-data">
No se encontraron productos con los filtros seleccionados.
</div>

<?php endif; ?>

<div class="reporte-footer">
Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
</div>

</div>

<?php require_once('../template/footer.php'); ?>