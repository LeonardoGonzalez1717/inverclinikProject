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

// Filtros
$where = [];

$cliente_id = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;
$fecha_inicio = $_GET['fecha_inicio'] ?? '';
$fecha_fin = $_GET['fecha_fin'] ?? '';
$estado = $_GET['estado'] ?? '';

if ($cliente_id > 0) {
    $where[] = 'v.cliente_id = ' . $cliente_id;
}

if(!empty($fecha_inicio)){
    $where[] = "v.fecha >= '$fecha_inicio'";
}

if(!empty($fecha_fin)){
    $where[] = "v.fecha <= '$fecha_fin'";
}

if(!empty($estado)){
    $where[] = "v.estado = '$estado'";
}

$condiciones = count($where) ? "WHERE ".implode(" AND ",$where) : "";

$sql = "
SELECT 
    v.id,
    v.fecha,
    v.numero_factura,
    cl.nombre AS cliente,
    v.estado,
    v.total
FROM ventas v
LEFT JOIN clientes cl ON cl.id = v.cliente_id
$condiciones
ORDER BY v.fecha DESC
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
    <h2>Reporte de Ventas</h2>
  </div>
</div>

<?php if($result->num_rows > 0): ?>
<table class="table">
  <thead>
    <tr>
      <th>#</th>
      <th>Fecha</th>
      <th>Factura</th>
      <th>Cliente</th>
      <th>Estado</th>
      <th>Total</th>
    </tr>
  </thead>
  <tbody>
    <?php $i = 1; ?>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
      <td style="text-align:right"><?= $i++ ?></td>
      <td><?= htmlspecialchars($row['fecha']) ?></td>
      <td><?= htmlspecialchars($row['numero_factura']) ?></td>
      <td><?= htmlspecialchars($row['cliente']) ?></td>
      <td><?php
        $etqEst = ['pendiente'=>'Pendiente','por_pagar'=>'Por pagar','aprobado'=>'Aprobado','entregado'=>'Entregado','cancelado'=>'Cancelado'];
        echo htmlspecialchars($etqEst[$row['estado']] ?? $row['estado']);
      ?></td>
      <td style="text-align:right"><?= htmlspecialchars($row['total']) ?></td>
    </tr>
    <?php endwhile; ?>
  </tbody>
</table>
<?php else: ?>
<div class="no-data">
  No se encontraron ventas con los filtros seleccionados.
</div>
<?php endif; ?>

<div class="reporte-footer">
  Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
</div>

</div>

<?php require_once('../template/footer.php'); ?>