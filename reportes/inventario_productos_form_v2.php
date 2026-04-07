<?php require_once('../template/header.php'); ?>

<?php
$productos = [];
$r = $conn->query('SELECT id, nombre FROM productos ORDER BY nombre ASC');
if ($r) {
    while ($row = $r->fetch_assoc()) {
        $productos[] = $row;
    }
}
?>

<div class="main-content">
  <div class="container-wrapper">
    <div class="container-inner">

      <h1 class="main-title">Reporte de Inventario de Productos Terminados</h1>

      <form method="GET" action="inventario_productos_view.php">

        <div class="mb-3">
          <label class="form-label">Producto</label>
          <select name="producto_id" class="form-control">
            <option value="">Todos los productos</option>
            <?php foreach ($productos as $pr): ?>
              <option value="<?php echo (int) $pr['id']; ?>"><?php echo htmlspecialchars($pr['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
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