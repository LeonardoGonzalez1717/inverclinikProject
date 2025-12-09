<?php
$sin_sidebar = true;
require_once('../template/header.php');

$sql = "
SELECT
    inv.insumo_id AS id,
    inv.ultima_actualizacion,
    i.nombre AS insumo,
    i.unidad_medida,
    inv.stock_actual
FROM inventario inv
JOIN insumos i ON i.id = inv.insumo_id
ORDER BY i.nombre ASC;


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
            <h2>Inventario de Materia Prima</h2>
        </div>
    </div>

    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Última Actualización</th>
                    <th>Insumo</th>
                    <th>Unidad de Medida</th>
                    <th>Stock Actual</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['id']) ?></td>
                        <td><?= htmlspecialchars($row['ultima_actualizacion'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['insumo']) ?></td>
                        <td><?= htmlspecialchars($row['unidad_medida']) ?></td>
                        <td><?= htmlspecialchars($row['stock_actual'] ?? '0') ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No existe inventario de materia prima registrado.</div>
    <?php endif; ?>

    <div class="reporte-footer">
        Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
    </div>

</div>

<?php require_once('../template/footer.php'); ?>
