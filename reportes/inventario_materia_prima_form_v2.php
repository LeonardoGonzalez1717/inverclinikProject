<?php require_once('../template/header.php'); ?>

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
            <option value="metro">Metro</option>
            <option value="unidad">Unidad</option>
            <option value="kilogramo">Kilogramo</option>
            <option value="litro">Litro</option>
            <option value="metro_cuadrado">Metro Cuadrado</option>
            <option value="carrete">Carrete</option>
            <option value="rollo">Rollo</option>
            <option value="pieza">Pieza</option>
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