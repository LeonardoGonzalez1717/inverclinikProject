<?php
$sin_sidebar = true;
require_once('../template/header.php');

// Filtros
$where = [];

if (!empty($_GET['nombre'])) {
  $nombre = $conn->real_escape_string($_GET['nombre']);
  $where[] = "i.nombre LIKE '%$nombre%'";
}

if (!empty($_GET['unidad'])) {
  $unidad = $conn->real_escape_string($_GET['unidad']);
  $where[] = "i.unidad_medida = '$unidad'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
  SELECT i.id, i.nombre, i.unidad_medida, i.costo_unitario
  FROM insumos i
  $condiciones
  ORDER BY i.nombre ASC
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
      <h2>Reporte de Insumos</h2>
    </div>
  </div>

  <?php if ($result->num_rows > 0): ?>
    <table class="table">
      <thead>
      <tr>
        <th>#</th>
        <th>Nombre</th>
        <th>Unidad de Medida</th>
        <th>Costo</th>
      </tr>
    </thead>
      <tbody>
        <?php $i = 1 ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td style="text-align: right"><?= $i++ ?></td>
          <td style="text-align: left"><?= htmlspecialchars($row['nombre']) ?></td>
          <td><?= htmlspecialchars($row['unidad_medida']) ?></td>
          <td style="text-align: right"><?= htmlspecialchars($row['costo_unitario']) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="no-data">No se encontraron insumos con los filtros seleccionados.</div>
  <?php endif; ?>

  <div class="reporte-footer">
    Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
  </div>
</div>

<?php require_once('../template/footer.php'); ?>