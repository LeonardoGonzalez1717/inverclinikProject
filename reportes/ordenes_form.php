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

if (!empty($_GET['fecha_inicio']) && !empty($_GET['fecha_fin'])) {
    $fecha_inicio = $conn->real_escape_string($_GET['fecha_inicio']);
    $fecha_fin = $conn->real_escape_string($_GET['fecha_fin']);
    $where[] = "op.fecha_inicio BETWEEN '$fecha_inicio' AND '$fecha_fin'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// Consulta principal
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
    JOIN users u ON u.id = op.usuario_id
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
            <h2>Reporte de Ordenes de Producción</h2>
        </div>
    </div>

    <!-- Formulario de filtros -->
    <form method="get" class="filtros-reporte">
        <label>Producto:
            <input type="text" name="producto" value="<?= htmlspecialchars($_GET['producto'] ?? '') ?>">
        </label>
        <label>Estado:
            <select name="estado">
                <option value="">Todos</option>
                <option value="Pendiente" <?= (($_GET['estado'] ?? '') == 'Pendiente') ? 'selected' : '' ?>>Pendiente</option>
                <option value="En Proceso" <?= (($_GET['estado'] ?? '') == 'En Proceso') ? 'selected' : '' ?>>En Proceso</option>
                <option value="Finalizada" <?= (($_GET['estado'] ?? '') == 'Finalizada') ? 'selected' : '' ?>>Finalizada</option>
            </select>
        </label>
        <label>Fecha Inicio:
            <input type="date" name="fecha_inicio" value="<?= htmlspecialchars($_GET['fecha_inicio'] ?? '') ?>">
        </label>
        <label>Fecha Fin:
            <input type="date" name="fecha_fin" value="<?= htmlspecialchars($_GET['fecha_fin'] ?? '') ?>">
        </label>
        <button type="submit">Filtrar</button>
    </form>

    <?php if ($result->num_rows > 0): ?>
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
                    <td><?= htmlspecialchars($row['responsable']) ?></td>
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