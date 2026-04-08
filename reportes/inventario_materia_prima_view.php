<?php
$sin_sidebar = true;
require_once('../template/header.php');

$where = [];

/* FILTROS */

$insumo = isset($_GET['insumo']) ? trim($_GET['insumo']) : '';
$unidad = isset($_GET['unidad_medida']) ? trim($_GET['unidad_medida']) : '';
$stock_min = isset($_GET['stock_min']) ? trim($_GET['stock_min']) : '';
$stock_max = isset($_GET['stock_max']) ? trim($_GET['stock_max']) : '';

if (!empty($insumo)) {
    $insumo = $conn->real_escape_string($insumo);
    $where[] = "i.nombre LIKE '%$insumo%'";
}

if (!empty($unidad)) {
    $unidad = $conn->real_escape_string($unidad);
    $where[] = "i.unidad_medida = '$unidad'";
}

if ($stock_min !== '') {
    $stock_min = (float)$stock_min;
    $where[] = "inv.stock_actual >= $stock_min";
}

if ($stock_max !== '') {
    $stock_max = (float)$stock_max;
    $where[] = "inv.stock_actual <= $stock_max";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT
    inv.insumo_id AS id,
    inv.ultima_actualizacion,
    i.nombre AS insumo,
    i.unidad_medida,
    inv.stock_actual
FROM inventario inv
JOIN insumos i ON i.id = inv.insumo_id
$condiciones
ORDER BY i.nombre ASC
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
        <h2>Reporte de Inventario – Materia Prima</h2>
    </div>
</div>

    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Última Actualización</th>
                    <th>Insumo</th>
                    <th>Unidad</th>
                    <th>Stock Actual</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['ultima_actualizacion'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['insumo']) ?></td>
                        <td><?= htmlspecialchars($row['unidad_medida']) ?></td>
                        <td><?= htmlspecialchars($row['stock_actual'] ?? '0') ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No hay registros de materia prima.</div>
    <?php endif; ?>

<div class="reporte-footer">
Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
</div>

</div>

<?php require_once('../template/footer.php'); ?>