<?php
$sin_sidebar = true;
require_once('../template/header.php');
$iduser = $_SESSION['iduser'];

$sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
// Filtros
$where = [];

$insumo = isset($_GET['nombre']) ? trim($_GET['nombre']) : '';
$unidad = isset($_GET['unidad_medida']) ? trim($_GET['unidad_medida']) : '';
$estado = isset($_GET['estado']) ? trim($_GET['estado']) : '';

if (!empty($insumo)) {
  $nombre = $conn->real_escape_string($insumo);
  $where[] = "i.nombre LIKE '%$nombre%'";
}

if (!empty($unidad)) {
  $unidad = $conn->real_escape_string($unidad);
  $where[] = "i.unidad_medida = '$unidad'";
}

if (!empty($estado)) {
  $estado = $conn->real_escape_string($estado);
  $where[] = "i.activo = '$estado'";
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