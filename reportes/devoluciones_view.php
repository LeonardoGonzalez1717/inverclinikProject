<?php
$sin_sidebar = true;
require_once('../template/header.php');
require_once __DIR__ . '/../lib/orden_numero.php';

$iduser = $_SESSION['iduser'] ?? 0;

$sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// Filtros
$where = [];

$cliente_id = isset($_GET['cliente_id']) ? (int) $_GET['cliente_id'] : 0;
$producto_id = isset($_GET['producto_id']) ? (int) $_GET['producto_id'] : 0;
$orden_id = isset($_GET['orden_produccion_id']) ? (int) $_GET['orden_produccion_id'] : 0;
$fecha_desde = trim($_GET['fecha_desde'] ?? '');
$fecha_hasta = trim($_GET['fecha_hasta'] ?? '');

if ($cliente_id > 0) {
    $where[] = "d.cliente_id = " . $cliente_id;
}

if ($producto_id > 0) {
    $where[] = "p.id = " . $producto_id;
}

if ($orden_id > 0) {
    $where[] = "op.id = " . $orden_id;
}

if (!empty($fecha_desde)) {
    $fecha_desde = $conn->real_escape_string($fecha_desde);
    $where[] = "DATE(d.fecha) >= '$fecha_desde'";
}

if (!empty($fecha_hasta)) {
    $fecha_hasta = $conn->real_escape_string($fecha_hasta);
    $where[] = "DATE(d.fecha) <= '$fecha_hasta'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
    SELECT d.id, d.codigo_devolucion, d.fecha, d.motivo, d.descripcion_motivo,
           d.accion_inventario, d.observaciones,
           c.nombre AS cliente_nombre,
           dd.orden_produccion_id, COALESCE(dd.cantidad, 1.00) AS cantidad,
           p.nombre AS producto_nombre,
           COALESCE(t.nombre, 'Única') AS talla_nombre,
           op.creado_en AS orden_creado_en
    FROM devoluciones d
    LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
    LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
    LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
    LEFT JOIN productos p ON p.id = rp.producto_id
    LEFT JOIN tallas t ON t.id = op.talla_id
    LEFT JOIN clientes c ON c.id = d.cliente_id
    $condiciones
    ORDER BY d.fecha DESC, d.id DESC
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
      <h2>Reporte de Devoluciones</h2>
    </div>
  </div>

  <?php if ($result && $result->num_rows > 0): ?>
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Código</th>
          <th>Fecha</th>
          <th>Orden de Prod.</th>
          <th>Producto</th>
          <th>Talla</th>
          <th>Cantidad</th>
          <th>Cliente</th>
          <th>Motivo</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 1; ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
          <td style="text-align: right"><?= $i++ ?></td>
          <td><?= htmlspecialchars($row['codigo_devolucion']) ?></td>
          <td><?= !empty($row['fecha']) ? date('d/m/Y', strtotime($row['fecha'])) : '—' ?></td>
          <td><?= htmlspecialchars(!empty($row['orden_produccion_id']) ? numero_orden_produccion((int)$row['orden_produccion_id'], $row['orden_creado_en']) : '—') ?></td>
          <td style="text-align: left"><?= htmlspecialchars($row['producto_nombre'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['talla_nombre'] ?? 'Única') ?></td>
          <td style="text-align: right"><?= number_format((float)($row['cantidad'] ?? 1), 0) ?></td>
          <td style="text-align: left"><?= htmlspecialchars($row['cliente_nombre'] ?? 'Anónimo') ?></td>
          <td style="text-align: left"><?= htmlspecialchars($row['motivo'] ?? '—') ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="no-data">No se encontraron devoluciones con los filtros seleccionados.</div>
  <?php endif; ?>

  <div class="reporte-footer">
    Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
  </div>
</div>

<?php require_once('../template/footer.php'); ?>
