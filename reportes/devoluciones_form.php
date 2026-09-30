<?php require_once('../template/header.php'); ?>
<?php
require_once __DIR__ . '/../lib/DevolucionesSchema.php';
DevolucionesSchema::asegurarTablas($conn);

$iduser = $_SESSION['iduser'] ?? 0;

$clientes = [];
$r = $conn->query('SELECT id, nombre, numero_documento FROM clientes ORDER BY nombre ASC');
if ($r) {
    while ($cl = $r->fetch_assoc()) {
        $clientes[] = $cl;
    }
}

$productos = [];
$r = $conn->query('SELECT id, nombre FROM productos ORDER BY nombre ASC');
if ($r) {
    while ($pr = $r->fetch_assoc()) {
        $productos[] = $pr;
    }
}

require_once __DIR__ . '/../lib/orden_numero.php';
$ordenes = [];
$r = $conn->query('
    SELECT op.id, op.creado_en, p.nombre AS producto_nombre, COALESCE(t.nombre, "Única") AS talla_nombre
    FROM ordenes_produccion op
    INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
    INNER JOIN productos p ON p.id = rp.producto_id
    LEFT JOIN tallas t ON t.id = op.talla_id
    ORDER BY op.id DESC
    LIMIT 100
');
if ($r) {
    while ($op = $r->fetch_assoc()) {
        $op['numero_orden'] = numero_orden_produccion((int)$op['id'], $op['creado_en']);
        $ordenes[] = $op;
    }
}
?>

<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h1 class="main-title">Reporte de Devoluciones de Producto Terminado</h1>
            <p class="subtitle" style="margin-bottom: 25px; color: #64748b;">Filtre los parámetros para generar el reporte de devoluciones y reingresos a stock</p>

            <form method="GET" action="devoluciones_view.php" target="_blank">
                <div class="row form-group">
                    <div class="col-sm-6">
                        <label class="form-label">Cliente</label>
                        <select name="cliente_id" id="cliente_id" class="form-control">
                            <option value="">Todos los clientes</option>
                            <?php foreach ($clientes as $c): ?>
                                <option value="<?php echo (int)$c['id']; ?>">
                                    <?php echo htmlspecialchars($c['nombre'] . ($c['numero_documento'] ? ' (' . $c['numero_documento'] . ')' : ''), ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-sm-6">
                        <label class="form-label">Producto</label>
                        <select name="producto_id" id="producto_id" class="form-control">
                            <option value="">Todos los productos</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?php echo (int)$p['id']; ?>">
                                    <?php echo htmlspecialchars($p['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row form-group">
                    <div class="col-sm-6">
                        <label class="form-label">Orden de Producción</label>
                        <select name="orden_produccion_id" id="orden_produccion_id" class="form-control">
                            <option value="">Todas las órdenes</option>
                            <?php foreach ($ordenes as $o): ?>
                                <option value="<?php echo (int)$o['id']; ?>">
                                    <?php echo htmlspecialchars($o['numero_orden'] . ' — ' . $o['producto_nombre'] . ' (' . $o['talla_nombre'] . ')', ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-sm-3">
                        <label class="form-label">Fecha Desde</label>
                        <input type="date" name="fecha_desde" id="fecha_desde" class="form-control">
                    </div>

                    <div class="col-sm-3">
                        <label class="form-label">Fecha Hasta</label>
                        <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control">
                    </div>
                </div>

                <div class="text-center" style="margin-top: 30px;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 24px; font-weight: bold;">
                        <i class="fas fa-file-pdf"></i> Generar Reporte
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once('../template/footer.php'); ?>
