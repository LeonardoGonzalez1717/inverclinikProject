<?php $role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0; ?>
<link rel="stylesheet" href="../css/navbar.css" />
<aside class="sidebar">
    <div class="logo">
        <a href="<?php echo ($_SESSION['role_id'] === 3) ? '../dashboard/dashboard_cliente.php' : '../dashboard/dashboard.php'; ?>" title="Inicio"> 
            INVERCLINIK
        </a>
    </div>
    <nav>
        <?php if ($_SESSION['role_id'] === 1) { ?>
            <ul>
                <li class="collapsible">
                <button class="collapsible-toggle">Procesos</button>
                <ul class="collapsible-content">
                    <li><a href="../cliente/cotizacion.php">Cotización</a></li>
                    <li><a href="../src/orden_produccion.php">Orden de Producción</a></li>
                    <li><a href="../src/movimientos_inventario.php">Movimientos de Inventario</a></li>
                    <li><a href="../src/historial_movimientos.php">Historial de movimientos</a></li>
                    <li><a href="../src/registrar_compra.php">Registrar Compra</a></li>
                    <li><a href="../src/registrar_venta.php">Registrar Venta</a></li>
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
                    <li><a href="../src/gestionar_proveedores.php">Proveedores</a></li>
                    <li><a href="../src/gestionar_clientes.php">Clientes</a></li>
                </ul>
                </li>
            </ul>
            <ul>
            <li class="collapsible">
                <button class="collapsible-toggle">Reportes</button>
                <ul class="collapsible-content">
                <li><a href="../reportes/insumos_form.php">Reporte de Insumos</a></li>
                <li><a href="../reportes/ordenes_view.php" target="_blank">Reporte de Ordenes</a></li>
                <li><a href="../reportes/productos_view.php" target="_blank">Reporte de Productos</a></li>
                <li><a href="../reportes/ordenes_produccion_view.php" target="_blank">Reporte de Ordenes P.</a></li>
                <li><a href="../reportes/inventario_materia_prima_view.php" target="_blank">Reporte de Materia Prima</a></li>
                <li><a href="../reportes/inventario_productos_view.php" target="_blank">Reporte de Stock</a></li>
                </ul>
            </li>
            </ul>
        <?php } ?>
        <?php if ($_SESSION['role_id'] === 3) { ?>
            <ul>
                <li class="collapsible"> <button class="collapsible-toggle">Mis Servicios</button>
                  <ul class="collapsible-content">
                    <li><a href="../cliente/catalogo/catalogo.php">Ver Catálogo</a></li>
                    <li><a href="../cliente/mis_pedidos.php">Estado de mis Pedidos</a></li>
                  </ul>
                </li>
            </ul>
        <?php } ?>
        
    </nav>
    <div class="perfil">
        <ul>
            <li class="collapsible">
              <button class="collapsible-toggle">Herramientas</button>
              <ul class="collapsible-content">
                <?php if ($_SESSION['role_id'] === 1) { ?>
                    <li><a href="../importaciones/importar_insumos_form.php">Importar</a></li>
                    <li><a href="../src">Auditoria</a></li>
                    <li><a href="../cliente/catalogo/catalogo.php">Catálogo</a></li>
                    <li><a href="../src/tasas_cambiarias.php">Tasas cambiarias</a></li>
                <?php } ?>
                <li><a href="../src">Manuales</a></li>
              </ul>
            </li>
        </ul>
        <button onclick="window.location.href='../perfiles/perfil.php'">Perfil</button>
        <?php if ($_SESSION['role_id'] === 1) { ?>
            <button onclick="window.location.href='../perfiles/gestionar_perfiles.php'">Gestionar Perfiles</button>
        <?php } ?>
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