<?php require_once('../template/header.php'); ?>

<div class="main-content">
  <div class="container-wrapper">
    <div class="container-inner">

      <h1 class="main-title">Reporte de Inventario de Productos Terminados</h1>

      <form method="GET" action="inventario_productos_view.php">

        <div class="mb-3">
          <label class="form-label">Producto</label>
          <input type="text" name="producto" class="form-control">
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