<!-- template/navbar.php -->
<aside class="sidebar">
    <div class="logo"><a href="../dashboard/dashboard.php" title="Inicio"> INVERCLINIK</a></div>
    <nav>
        <ul>
            <li><a href="ventas.php">Ventas</a></li>
            <li><a href="../src/nuevo_producto.php">Producción</a></li>
            <li><a href="../src/orden_produccion.php">Inventario</a></li>
            <li><a href="compras.php">Compras</a></li>
            <li><a href="pendientes.php">Pendientes</a></li>
            <li><a href="reportes.php">Reportes</a></li>
        </ul>
    </nav>
    <div class="perfil">
        <button onclick="window.location.href='perfil.php'">Perfil</button>
        <button onclick="window.location.href='../perfiles/gestionar_perfiles.php'">Gestionar Perfiles</button>
        <button onclick="window.location.href='../template/logout.php'">Cerrar sesión</button>
    </div>
</aside>