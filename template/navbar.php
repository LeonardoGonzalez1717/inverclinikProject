<link rel="stylesheet" href="../css/navbar.css" />
<aside class="sidebar">
    <div class="logo"><a href="../dashboard/dashboard.php" title="Inicio"> INVERCLINIK</a></div>
    <nav>
        <ul>
            <li class="collapsible">
              <button class="collapsible-toggle">Procesos</button>
              <ul class="collapsible-content">
                <li><a href="../src/orden_produccion.php">Orden de Producción</a></li>
                <li><a href="../src/movimientos_inventario.php">Movimientos de Inventario</a></li>
              </ul>
            </li>
        </ul>
        <ul>
            <li class="collapsible">
              <button class="collapsible-toggle">Registros</button>
              <ul class="collapsible-content">
                <li><a href="../src/gestionar_productos.php">Gestionar Productos</a></li>
                <li><a href="../src/gestionar_insumos.php">Gestionar Insumos</a></li>
                <li><a href="../src/nuevo_producto.php">Recetas</a></li>
              </ul>
            </li>
        </ul>
        <ul>
          <li class="collapsible">
            <button class="collapsible-toggle">Reportes</button>
            <ul class="collapsible-content">
              <li><a href="../reportes/insumos_view.php" target="_blank">Reporte de Insumos</a></li>
              <li><a href="../reportes/ordenes_view.php" target="_blank">Reporte de Ordenes</a></li>
              <li><a href="../reportes/productos_view.php" target="_blank">Reporte de Productos</a></li>
            </ul>
          </li>
        </ul>
    </nav>
    <div class="perfil">
        <button onclick="window.location.href='../perfiles/perfil.php'">Perfil</button>
        <button onclick="window.location.href='../perfiles/gestionar_perfiles.php'">Gestionar Perfiles</button>
        <button onclick="window.location.href='../template/logout.php'">Cerrar sesión</button>
    </div>
</aside>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.collapsible-toggle').forEach(btn => {
      btn.addEventListener('click', () => {
        const parent = btn.closest('.collapsible');
        parent.classList.toggle('open');
      });
    });
  });
</script>