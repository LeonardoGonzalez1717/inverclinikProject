<?php
$sin_sidebar = true;
require_once('../template/header.php');
require_once __DIR__ . '/../lib/orden_numero.php';
require_once __DIR__ . '/../lib/DevolucionesSchema.php';

DevolucionesSchema::asegurarTablas($conn);

$iduser = $_SESSION['iduser'] ?? 0;

$sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

// Filtros
$where = [];

$producto_id = isset($_GET['producto_id']) ? (int) $_GET['producto_id'] : 0;
$orden_id = isset($_GET['orden_produccion_id']) ? (int) $_GET['orden_produccion_id'] : 0;
$estado = trim($_GET['estado'] ?? '');
$fecha_desde = trim($_GET['fecha_desde'] ?? '');
$fecha_hasta = trim($_GET['fecha_hasta'] ?? '');

$tipo_origen = trim($_GET['tipo_origen'] ?? '');

if (!empty($tipo_origen)) {
    $tipoEsc = $conn->real_escape_string($tipo_origen);
    $where[] = "COALESCE(d.tipo_origen, 'orden_produccion') = '$tipoEsc'";
}

if ($producto_id > 0) {
    $where[] = "(p_op.id = $producto_id OR p_v.id = $producto_id OR p_dd.id = $producto_id)";
}

if ($orden_id > 0) {
    $where[] = "op.id = " . $orden_id;
}

if (!empty($estado)) {
    $estadoEsc = $conn->real_escape_string($estado);
    $where[] = "COALESCE(d.estado, 'pendiente') = '$estadoEsc'";
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
           d.accion_inventario, COALESCE(d.estado, 'pendiente') AS estado,
           COALESCE(d.tipo_origen, 'orden_produccion') AS tipo_origen,
           d.motivo_inspeccion, d.observaciones,
           dd.orden_produccion_id, dd.venta_id, COALESCE(dd.cantidad, 1.00) AS cantidad,
           COALESCE(p_op.nombre, p_v.nombre, p_dd.nombre, '—') AS producto_nombre,
           COALESCE(t_op.nombre, rt_v.nombre_rango, 'Única') AS talla_nombre,
           op.creado_en AS orden_creado_en,
           v.numero_factura
    FROM devoluciones d
    LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
    LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
    LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
    LEFT JOIN productos p_op ON p_op.id = rp.producto_id
    LEFT JOIN tallas t_op ON t_op.id = op.talla_id
    LEFT JOIN ventas v ON v.id = COALESCE(d.venta_id, dd.venta_id)
    LEFT JOIN recetas r_v ON r_v.id = dd.receta_id
    LEFT JOIN productos p_v ON p_v.id = r_v.producto_id
    LEFT JOIN rangos_tallas rt_v ON rt_v.id = r_v.rango_tallas_id
    LEFT JOIN productos p_dd ON p_dd.id = dd.producto_id
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
      <h2>Reporte de Devoluciones y Control de Calidad</h2>
    </div>
  </div>

  <?php if ($result && $result->num_rows > 0): ?>
    <table class="table">
      <thead>
        <tr>
          <th>#</th>
          <th>Código</th>
          <th>Fecha</th>
          <th>Origen (Doc.)</th>
          <th>Producto</th>
          <th>Talla</th>
          <th>Cantidad</th>
          <th>Motivo</th>
          <th>Estado Calidad</th>
        </tr>
      </thead>
      <tbody>
        <?php $i = 1; ?>
        <?php while ($row = $result->fetch_assoc()): ?>
        <?php
          $est = $row['estado'] ?? 'pendiente';
          $estLabel = 'Pendiente';
          if ($est === 'aprobado') {
              $estLabel = 'Buen Estado (En Stock)';
          } elseif ($est === 'rechazado') {
              $estLabel = 'Rechazado (Descartado)';
          }

          $origenDoc = '—';
          if (($row['tipo_origen'] ?? '') === 'venta' || !empty($row['venta_id'])) {
              $origenDoc = 'Factura: ' . (!empty($row['numero_factura']) ? $row['numero_factura'] : ('Venta #' . ($row['venta_id'] ?? '')));
          } elseif (!empty($row['orden_produccion_id'])) {
              $origenDoc = 'OP: ' . numero_orden_produccion((int)$row['orden_produccion_id'], $row['orden_creado_en']);
          }
        ?>
        <tr>
          <td style="text-align: right"><?= $i++ ?></td>
          <td><?= htmlspecialchars($row['codigo_devolucion']) ?></td>
          <td><?= !empty($row['fecha']) ? date('d/m/Y h:i A', strtotime($row['fecha'])) : '—' ?></td>
          <td><?= htmlspecialchars($origenDoc) ?></td>
          <td style="text-align: left"><?= htmlspecialchars($row['producto_nombre'] ?? '—') ?></td>
          <td><?= htmlspecialchars($row['talla_nombre'] ?? 'Única') ?></td>
          <td style="text-align: right"><?= number_format((float)($row['cantidad'] ?? 1), 0) ?></td>
          <td style="text-align: left"><?= htmlspecialchars($row['motivo'] ?? '—') ?></td>
          <td style="text-align: center; font-weight: bold;"><?= htmlspecialchars($estLabel) ?></td>
        </tr>
      <?php endwhile; ?>
      </tbody>
    </table>
  <?php else: ?>
    <div class="no-data">No se encontraron devoluciones con los filtros seleccionados.</div>
  <?php endif; ?>

  <div class="reporte-footer">
    Generado el <?= date('d/m/Y \a \l\a\s h:i A') ?>
  </div>
</div>

<?php require_once('../template/footer.php'); ?>
