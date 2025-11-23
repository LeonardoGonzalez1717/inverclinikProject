<?php
$sin_sidebar = true;
require_once('../template/header.php');

// Filtros
$where = [];

if (!empty($_GET['nombre'])) {
    $nombre = $conn->real_escape_string($_GET['nombre']);
    $where[] = "p.nombre LIKE '%$nombre%'";
}

if (!empty($_GET['categoria'])) {
    $categoria = $conn->real_escape_string($_GET['categoria']);
    $where[] = "p.categoria LIKE '%$categoria%'";
}

if (!empty($_GET['genero'])) {
    $genero = $conn->real_escape_string($_GET['genero']);
    $where[] = "p.tipo_genero = '$genero'";
}

$condiciones = count($where) ? "WHERE " . implode(" AND ", $where) : "";

// Consulta de productos
$sql = "
    SELECT 
        p.id,
        p.nombre,
        p.categoria,
        p.tipo_genero,
        p.descripcion,
        p.fecha_creacion
    FROM productos p
    $condiciones
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
            <h2>Reporte de Productos</h2>
        </div>
    </div>

    <!-- Formulario de filtros -->
    <form method="get" class="filtros-reporte">
        <label>Nombre:
            <input type="text" name="nombre" value="<?= htmlspecialchars($_GET['nombre'] ?? '') ?>">
        </label>

        <label>Categoría:
            <input type="text" name="categoria" value="<?= htmlspecialchars($_GET['categoria'] ?? '') ?>">
        </label>

        <label>Género:
            <select name="genero">
                <option value="">Todos</option>
                <option value="Masculino" <?= (($_GET['genero'] ?? '') == 'Masculino') ? 'selected' : '' ?>>Masculino</option>
                <option value="Femenino" <?= (($_GET['genero'] ?? '') == 'Femenino') ? 'selected' : '' ?>>Femenino</option>
                <option value="Unisex" <?= (($_GET['genero'] ?? '') == 'Unisex') ? 'selected' : '' ?>>Unisex</option>
            </select>
        </label>

        <button type="submit">Filtrar</button>
    </form>

    <?php if ($result && $result->num_rows > 0): ?>
        <table class="table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nombre</th>
                    <th>Categoría</th>
                    <th>Género</th>
                    <th>Descripción</th>
                    <th>Fecha Creación</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1 ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?= $i++ ?></td>
                        <td><?= htmlspecialchars($row['nombre']) ?></td>
                        <td><?= htmlspecialchars($row['categoria']) ?></td>
                        <td><?= htmlspecialchars($row['tipo_genero']) ?></td>
                        <td><?= htmlspecialchars($row['descripcion']) ?></td>
                        <td><?= htmlspecialchars($row['fecha_creacion']) ?></td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">No se encontraron productos con los filtros seleccionados.</div>
    <?php endif; ?>

    <div class="reporte-footer">
        Generado el <?= date('d/m/Y \a \l\a\s H:i') ?>
    </div>
</div>

<?php require_once('../template/footer.php'); ?>
