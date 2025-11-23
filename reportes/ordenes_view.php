<?php
$sin_sidebar = true;
require_once('../template/header.php');

// Filtros
$where = [];

if (!empty($_GET['producto'])) {
    $producto = $conn->real_escape_string($_GET['producto']);
    $where[] = "p.nombre LIKE '%$producto%'";
}

if (!empty($_GET['estado'])) {
    $estado = $conn->real_escape_string($_GET['estado']);
    $where[] = "op.estado = '$estado'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// CONSULTA CORREGIDA (LEFT JOIN para permitir usuario_id NULL)
$sql = "
    SELECT 
        op.id AS orden_id,
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

    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Fecha Inicio</th>
                    <th>Fecha Fin</th>
                    <th>Estado</th>
                    <th>Responsable</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1 ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td style="text-align: right"><?= $i++ ?></td>
                        <td style="text-align: left"><?= htmlspecialchars($row['producto']) ?></td>
                        <td style="text-align: right"><?= htmlspecialchars($row['cantidad_a_producir']) ?></td>
                        <td><?= htmlspecialchars($row['fecha_inicio']) ?></td>
                        <td><?= htmlspecialchars($row['fecha_fin']) ?></td>
                        <td><?= htmlspecialchars($row['estado']) ?></td>
                        <td><?= $row['responsable'] ? htmlspecialchars($row['responsable']) : '—' ?></td>
                        <td><?= htmlspecialchars($row['observaciones']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No se encontraron órdenes con los filtros seleccionados.</div>
    <?php endif; ?>

    <div class="reporte-footer">
        Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
    </div>
</div>

<?php require_once('../template/footer.php'); ?>