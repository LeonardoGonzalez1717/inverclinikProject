<?php require_once('../template/header.php'); ?>
<?php
$iduser = $_SESSION['iduser'];

$sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();

$proveedores = [];
$r = $conn->query('SELECT id, nombre FROM proveedores ORDER BY nombre ASC');
if ($r) {
    while ($p = $r->fetch_assoc()) {
        $proveedores[] = $p;
    }
}
$lista_insumos = [];
$r = $conn->query('SELECT id, nombre FROM insumos ORDER BY nombre ASC');
if ($r) {
    while ($p = $r->fetch_assoc()) {
        $lista_insumos[] = $p;
    }
}
$unidades_medida = [];
$r = $conn->query('SELECT codigo, nombre FROM unidad_medida ORDER BY nombre ASC');
if ($r) {
    while ($p = $r->fetch_assoc()) {
        $unidades_medida[] = $p;
    }
}
?>

<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h1 class="main-title">Reporte de Insumos</h1>

            <form method="GET" action="insumos_view.php" target="_blank">
                <div class="row form-group">
                    <div class="col-sm-8">
                        <label for="insumo_id" class="form-label">Insumo</label>
                        <select name="insumo_id" id="insumo_id" class="form-control">
                            <option value="">Todos los insumos</option>
                            <?php foreach ($lista_insumos as $ins): ?>
                                <option value="<?php echo (int) $ins['id']; ?>"><?php echo htmlspecialchars($ins['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-sm-4">
                        <label class="form-label">Unidad de Medida</label>
                        <select name="unidad_medida" id="unidad_medida" class="form-control">
                            <option value="">-- Seleccione una unidad --</option>
                            <?php foreach ($unidades_medida as $um): ?>
                                <option value="<?php echo htmlspecialchars($um['codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($um['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row form-group">
                    <div class="col-sm-8">
                        <label class="form-label">Proveedor</label>
                        <select name="proveedor_id" class="form-control">
                            <option value="">Todos los proveedores</option>
                            <?php foreach ($proveedores as $prov): ?>
                            <option value="<?php echo (int) $prov['id']; ?>">
                                <?php echo htmlspecialchars($prov['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-4">
                        <label class="form-label">Estado</label>
                        <select name="estado" class="form-control">
                            <option value=""></option>
                            <option value="pendiente">Pendiente</option>
                            <option value="recibido">Recibido</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                <div class="text-center">
                    <button type="submit" class="btn btn-primary">Generar Reporte</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php require_once('../template/footer.php'); ?>