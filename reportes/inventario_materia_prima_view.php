<?php
$sin_sidebar = true;
require_once('../template/header.php');
require_once __DIR__ . '/../lib/InventarioLotes.php';
InventarioLotes::asegurar($conn);

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
    $where[] = "um.codigo = '$unidad'";
}

if ($stock_min !== '') {
    $stock_min = (float)$stock_min;
    $where[] = "inv.stock_actual >= $stock_min";
}

if ($stock_max !== '') {
    $stock_max = (float)$stock_max;
    $where[] = "inv.stock_actual <= $stock_max";
}

$tieneTipoItem = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;
if ($tieneTipoItem) {
    $where[] = "inv.tipo_item = 'insumo'";
    $idCol = 'inv.tipo_item_id AS id';
    $joinInsumo = 'JOIN insumos i ON i.id = inv.tipo_item_id';
} else {
    $idCol = 'inv.insumo_id AS id';
    $joinInsumo = 'JOIN insumos i ON i.id = inv.insumo_id';
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

$sql = "
SELECT
    $idCol,
    inv.ultima_actualizacion,
    i.nombre AS insumo,
    COALESCE(um.nombre, um.codigo, '') AS unidad_medida,
    inv.stock_actual,
    (SELECT GROUP_CONCAT(l.numero_lote ORDER BY l.fecha_entrada DESC, l.id DESC SEPARATOR ', ')
     FROM inventario_lotes l
     WHERE l.tipo_item = 'insumo' AND l.tipo_item_id = i.id AND l.cantidad_restante > 0) AS lotes
FROM inventario inv
$joinInsumo
LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
$condiciones
ORDER BY i.nombre ASC
";

$result = $conn->query($sql);
$i = 1;
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
                    <th>Lote</th>
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
                        <td style="text-align:left; font-size:12px;"><?= htmlspecialchars($row['lotes'] ?? '—') ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No hay registros de materia prima.</div>
    <?php endif; ?>

<div class="reporte-footer">
Generado el <?= date('d/m/Y \a \l\a\s h:i A') ?>
</div>

</div>

<?php require_once('../template/footer.php'); ?>