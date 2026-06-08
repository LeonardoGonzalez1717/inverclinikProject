<?php
$role_id = isset($_SESSION['role_id']) ? (int) $_SESSION['role_id'] : 0;
$isCliente = ($role_id === 3);
$menuCompleto = in_array($role_id, [1, 2], true);
$gerProd = ($role_id === 4);
$gerCom = ($role_id === 5);
$gerAdm = ($role_id === 6);
$tieneMenuGerencia = $gerProd || $gerCom || $gerAdm;
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" />
<link rel="stylesheet" href="../css/navbar.css" />

<aside class="sidebar">
    <div class="logo">
        <a href="<?php echo $isCliente ? '../dashboard/dashboard_cliente.php' : '../dashboard/dashboard.php'; ?>" title="Inicio">
            <i class="fas fa-layer-group"></i> <span>INVERCLINIK</span>
        </a>
    </div>
    <nav>
        <?php if ($menuCompleto) { ?>
            <ul>
                <li class="collapsible">
                    <button class="collapsible-toggle"><i class="fas fa-cogs"></i> <span>Procesos</span></button>
                    <ul class="collapsible-content">
                        <li><a href="../cliente/cotizacion.php"><i class="fas fa-file-invoice-dollar"></i> <span>Cotización</span></a></li>
                        <li><a href="../src/orden_produccion.php"><i class="fas fa-industry"></i> <span>Orden de Producción</span></a></li>
                        <li><a href="../src/movimientos_inventario.php"><i class="fas fa-boxes"></i> <span>Movimientos Inv.</span></a></li>
                        <li><a href="../src/historial_movimientos.php"><i class="fas fa-history"></i> <span>Historial Mov.</span></a></li>
                        <li><a href="../src/registrar_compra.php"><i class="fas fa-shopping-cart"></i> <span>Registrar Compra</span></a></li>
                        <li><a href="../src/registrar_venta.php"><i class="fas fa-cash-register"></i> <span>Registrar Venta</span></a></li>
                        <li><a href="../src/cuentas_por_cobrar.php">Cuentas por cobrar</a></li>
                    </ul>
                </li>
            </ul>
            <ul>
                <li class="collapsible">
                    <button class="collapsible-toggle"><i class="fas fa-folder-open"></i> <span>Registros</span></button>
                    <ul class="collapsible-content">
                        <li><a href="../src/gestionar_productos.php"><i class="fas fa-tshirt"></i> <span>Gestionar Prod.</span></a></li>
                        <li><a href="../src/gestionar_insumos.php"><i class="fas fa-dolly-flatbed"></i> <span>Gestionar Insumos</span></a></li>
                        <li><a href="../src/nuevo_producto.php"><i class="fas fa-cut"></i> <span>Guia de Corte</span></a></li>
                        <li><a href="../src/registrar_categoria.php"><i class="fas fa-tags"></i> <span>Registrar Categoria</span></a></li>
                        <li><a href="../src/registrar_almacen.php"><i class="fas fa-warehouse"></i> <span>Registrar Almacén</span></a></li>
                        <li><a href="../src/gestionar_taller.php"><i class="fas fa-tools"></i> <span>Registrar Talleres</span></a></li>
                        <li><a href="../src/gestionar_unidades_medida.php"><i class="fas fa-tape"></i> <span>Unidades de Medida</span></a></li>
                        <li><a href="../src/gestionar_proveedores.php"><i class="fas fa-truck"></i> <span>Proveedores</span></a></li>
                        <li><a href="../src/gestionar_clientes.php"><i class="fas fa-users"></i> <span>Clientes</span></a></li>
                      <li><a href="../src/registrar_rangos_tallas.php">Rangos de Tallas</a></li>
                    </ul>
                </li>
            </ul>
            <ul>
                <li class="collapsible">
                    <button class="collapsible-toggle"><i class="fas fa-chart-pie"></i> <span>Reportes</span></button>
                    <ul class="collapsible-content">
                        <li><a href="../reportes/compras_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Compras</span></a></li>
                        <li><a href="../reportes/insumos_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Insumos</span></a></li>
                        <li><a href="../reportes/ordenes_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Ordenes</span></a></li>
                        <li><a href="../reportes/productos_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Productos</span></a></li>
                        <li><a href="../reportes/ordenes_produccion_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Ordenes P.</span></a></li>
                        <li><a href="../reportes/inventario_materia_prima_form_v2.php"><i class="fas fa-file-alt"></i> <span>Rep. Materia Prima</span></a></li>
                        <li><a href="../reportes/inventario_productos_form_v2.php"><i class="fas fa-file-alt"></i> <span>Rep. Stock</span></a></li>
                        <li><a href="../reportes/ventas_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Ventas</span></a></li>
                    </ul>
                </li>
            </ul>
        <?php } elseif ($tieneMenuGerencia) { ?>
            <ul>
                <li class="collapsible">
                    <button class="collapsible-toggle"><i class="fas fa-cogs"></i> <span>Procesos</span></button>
                    <ul class="collapsible-content">
                        <?php if ($gerCom) { ?>
                            <li><a href="../cliente/cotizacion.php"><i class="fas fa-file-invoice-dollar"></i> <span>Cotización</span></a></li>
                        <?php } ?>
                        <?php if ($gerProd) { ?>
                            <li><a href="../src/orden_produccion.php"><i class="fas fa-industry"></i> <span>Orden de Producción</span></a></li>
                            <li><a href="../src/movimientos_inventario.php"><i class="fas fa-boxes"></i> <span>Movimientos Inv.</span></a></li>
                            <li><a href="../src/historial_movimientos.php"><i class="fas fa-history"></i> <span>Historial Mov.</span></a></li>
                        <?php } ?>
                        <?php if ($gerCom) { ?>
                            <li><a href="../src/registrar_venta.php"><i class="fas fa-cash-register"></i>Registrar Venta</a></li>
                            <li><a href="../src/cuentas_por_cobrar.php">Cuentas por cobrar</a></li>
                        <?php } ?>
                        <?php if ($gerAdm) { ?>
                            <li><a href="../src/registrar_compra.php"><i class="fas fa-shopping-cart"></i> <span>Registrar Compra</span></a></li>
                        <?php } ?>
                    </ul>
                </li>
            </ul>
            <?php if ($gerCom || $gerAdm) { ?>
            <ul>
                <li class="collapsible">
                    <button class="collapsible-toggle"><i class="fas fa-folder-open"></i> <span>Registros</span></button>
                    <ul class="collapsible-content">
                        <?php if ($gerCom) { ?>
                            <li><a href="../src/gestionar_clientes.php"><i class="fas fa-users"></i> <span>Clientes</span></a></li>
                        <?php } ?>
                        <?php if ($gerAdm) { ?>
                            <li><a href="../src/gestionar_proveedores.php"><i class="fas fa-truck"></i> <span>Proveedores</span></a></li>
                            <li><a href="../src/gestionar_unidades_medida.php"><i class="fas fa-balance-scale"></i> <span>Unidades de Medida</span></a></li>
                            <li><a href="../src/gestionar_insumos.php"><i class="fas fa-dolly-flatbed"></i> <span>Gestionar Insumos</span></a></li>
                        <?php } ?>
                    </ul>
                </li>
            </ul>
            <?php } ?>
            <ul>
                <li class="collapsible">
                    <button class="collapsible-toggle"><i class="fas fa-chart-pie"></i> <span>Reportes</span></button>
                    <ul class="collapsible-content">
                        <?php if ($gerProd) { ?>
                            <li><a href="../reportes/ordenes_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Ordenes</span></a></li>
                            <li><a href="../reportes/ordenes_produccion_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Ordenes P.</span></a></li>
                            <li><a href="../reportes/inventario_materia_prima_form_v2.php"><i class="fas fa-file-alt"></i> <span>Rep. Materia Prima</span></a></li>
                            <li><a href="../reportes/inventario_productos_form_v2.php"><i class="fas fa-file-alt"></i> <span>Rep. Stock</span></a></li>
                        <?php } ?>
                        <?php if ($gerCom) { ?>
                            <li><a href="../reportes/ventas_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Ventas</span></a></li>
                            <li><a href="../reportes/productos_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Productos</span></a></li>
                        <?php } ?>
                        <?php if ($gerAdm) { ?>
                            <li><a href="../reportes/compras_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Compras</span></a></li>
                            <li><a href="../reportes/insumos_form.php"><i class="fas fa-file-alt"></i> <span>Rep. Insumos</span></a></li>
                            <li><a href="../reportes/inventario_materia_prima_form_v2.php"><i class="fas fa-file-alt"></i> <span>Rep. Materia Prima</span></a></li>
                        <?php } ?>
                    </ul>
                </li>
            </ul>
        <?php } ?>
        <?php if ($isCliente) { ?>
            <ul>
                <li class="collapsible"> 
                    <button class="collapsible-toggle"><i class="fas fa-concierge-bell"></i> <span>Mis Servicios</span></button>
                    <ul class="collapsible-content">
                        <li><a href="../cliente/catalogo/catalogo.php"><i class="fas fa-images"></i> <span>Ver Catálogo</span></a></li>
                        <li><a href="../cliente/mis_presupuestos.php"><i class="fas fa-wallet"></i> <span>Mis Presupuestos</span></a></li>
                        <li><a href="../cliente/mis_cotizaciones.php"><i class="fas fa-file-contract"></i> <span>Mis Cotizaciones</span></a></li>
                      <li><a href="../cliente/cuentas_por_pagar.php">Cuentas por pagar</a></li>
                    </ul>
                </li>
            </ul>
        <?php } ?>
    </nav>

    <div class="perfil">
        <ul>
            <li class="collapsible">
                <button class="collapsible-toggle"><i class="fas fa-wrench"></i> <span>Herramientas</span></button>
                <ul class="collapsible-content">
                    <?php if ($role_id === 1) { ?>
                        <li><a href="../importaciones/importar_insumos_form.php"><i class="fas fa-upload"></i> <span>Importar</span></a></li>
                        <li><a href="../src/auditoria.php"><i class="fas fa-shield-alt"></i> <span>Auditoría</span></a></li>
                        <li><a href="../cliente/catalogo/catalogo.php"><i class="fas fa-images"></i> <span>Catálogo</span></a></li>
                        <li><a href="../src/tasas_cambiarias.php"><i class="fas fa-dollar-sign"></i> <span>Tasas Cambiarias</span></a></li>
                        <li><a href="../src/respaldos_bd.php"><i class="fas fa-database"></i> <span>Respaldos BD</span></a></li>
                        <li><a href="../src/restauracion_bd.php"><i class="fas fa-window-restore"></i> <span>Restauración BD</span></a></li>
                    <?php } ?>
                    <li><a href="../src"><i class="fas fa-book"></i> <span>Manuales</span></a></li>
                </ul>
            </li>
        </ul>
        <ul>
            <li class="collapsible">
                <button class="collapsible-toggle"><i class="fas fa-user-cog"></i> <span>Configuración</span></button>
                <ul class="collapsible-content">
                    <?php if ($role_id === 1) { ?>
                        <li><a href="../perfiles/gestionar_perfiles.php"><i class="fas fa-users-cog"></i> <span>Gestionar Perfiles</span></a></li>
                    <?php } ?>
                    <li><a href="../perfiles/perfil.php"><i class="fas fa-user"></i> <span>Perfil</span></a></li>
                </ul>
            </li>
        </ul>
        <button class="logout-btn" onclick="window.location.href='../template/logout.php'">
            <i class="fas fa-sign-out-alt"></i> <span>Cerrar sesión</span>
        </button>
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