<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

$sql = "
   SELECT rp.id, p.nombre AS producto_nombre, i.nombre AS insumo_nombre, rt.nombre_rango AS rango_tallas_nombre, tp.nombre AS tipo_produccion_nombre, rp.cantidad_por_unidad, rp.observaciones 
   FROM recetas_productos rp 
    INNER JOIN productos p ON rp.producto_id = p.id 
    INNER JOIN insumos i ON rp.insumo_id = i.id 
    INNER JOIN rangos_tallas rt ON rp.rango_tallas_id = rt.id 
    INNER JOIN tipos_produccion tp ON rp.tipo_produccion_id = tp.id ORDER BY rp.id;
";

$resultado = $conn->query($sql);
?>

<div id="page-wrapper">
    <div id="page-inner">
        <div class="row">
            <div class="col-md-12">
                <h2>Gestion de recetas</h2>
            </div>
        </div>
        <div class="row">
            <div class="col-md-12">
                <h5>Lista de Recetas (con nombres reales)</h5>
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Producto</th>
                            <th>Insumo</th>
                            <th>Rango de Tallas</th>
                            <th>Tipo de Producción</th>
                            <th>Cantidad por Unidad</th>
                            <th>Observaciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while ($fila = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($fila['id']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['producto_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['insumo_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['rango_tallas_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['tipo_produccion_nombre']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['cantidad_por_unidad']); ?></td>
                                    <td><?php echo htmlspecialchars($fila['observaciones'] ?? ''); ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center"></td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
$conn->close();
?>