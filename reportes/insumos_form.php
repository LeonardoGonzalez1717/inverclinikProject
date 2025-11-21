<?php require_once('../template/header.php'); ?>

<div class="contenido-principal">
  <div class="container-wrapper">
    <div class="container-inner">
      <h1 class="main-title">Reporte de Insumos</h1>

      <form method="GET" action="insumos_view.php" target="_blank">
        <div class="mb-3">
          <label for="nombre" class="form-label">Nombre del Insumo</label>
          <input type="text" name="nombre" id="nombre" class="form-control" placeholder="Ej. Tela, Botón, Hilo">
        </div>

        <div class="mb-3">
          <label for="unidad" class="form-label">Unidad de Medida</label>
          <select name="unidad" id="unidad" class="form-control">
            <option value=""></option>
          </select>
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-primary">Generar Reporte</button>
        </div>
      </form>

    </div>
  </div>
</div>

<?php require_once('../template/footer.php'); ?>