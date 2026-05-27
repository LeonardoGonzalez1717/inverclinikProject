<?php require_once('../template/header.php'); ?>

<?php
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

      <h1 class="main-title">Reporte de Inventario - Materia Prima</h1>

      <form method="GET" action="inventario_materia_prima_view.php" target="_blank">

        <div class="mb-3">
          <label class="form-label">Insumo</label>
          <input type="text" name="insumo" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label">Unidad de Medida</label>
          <select name="unidad_medida" class="form-control">
            <option value="">-- Seleccione una unidad --</option>
            <?php foreach ($unidades_medida as $um): ?>
              <option value="<?php echo htmlspecialchars($um['codigo'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo htmlspecialchars($um['nombre'], ENT_QUOTES, 'UTF-8'); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Stock mínimo</label>
          <input type="number" name="stock_min" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label">Stock máximo</label>
          <input type="number" name="stock_max" class="form-control">
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-primary">
            Generar Reporte
          </button>
        </div>

      </form>

    </div>
  </div>
</div>

<?php require_once('../template/footer.php'); ?>