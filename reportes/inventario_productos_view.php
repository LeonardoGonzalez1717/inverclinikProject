<?php
$sin_sidebar = true;
require_once('../template/header.php');

$sql = "
SELECT
    ip.producto_id AS id,
    ip.ultima_actualizacion,
    p.nombre AS producto,
    ip.stock_actual
FROM inventario_productos ip
JOIN productos p ON p.id = ip.producto_id
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
                    <th>ID</th>
                    <th>Última Actualización</th>
                    <th>Producto</th>
                    <th>Rango de Tallas</th>
                    <th>Stock Actual</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['ultima_actualizacion'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['producto']) ?></td>
                        <td><?= htmlspecialchars($row['rango_tallas'] ?? '—') ?></td>
                        <td><?= isset($row['stock_actual']) ? htmlspecialchars($row['stock_actual']) : '0' ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No existen productos terminados en inventario.</div>
    <?php endif; ?>

    <div class="reporte-footer">
        Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
    </div>

</div>

<?php require_once('../template/footer.php'); ?>
