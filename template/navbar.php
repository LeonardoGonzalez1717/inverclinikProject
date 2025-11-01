<!-- ../template/navbar.php -->
<div class="navbar navbar-inverse navbar-fixed-top">
    <div class="adjust-nav">
        <div class="navbar-header">
            <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".sidebar-collapse">
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
                <span class="icon-bar"></span>
            </button>
            <a class="navbar-brand" href="#"><i class="fa fa-square-o "></i>&nbsp;TWO PAGE</a>
        </div>
        <div class="navbar-collapse collapse">
            <ul class="nav navbar-nav navbar-right">
                <li><a href="#">See Website</a></li>
                <li><a href="#">Open Ticket</a></li>
                <li><a href="#">Report Bug</a></li>
            </ul>
        </div>
    </div>
</div>
<!-- /. NAV TOP  -->
<nav class="navbar-default navbar-side" style="top:50px" role="navigation">
    <div class="sidebar-collapse">
        <ul class="nav" id="main-menu">
            <li class="text-center user-image-back" style="display:flex; align-items:end; cursor: pointer;" onclick="userManagement()">
                <img src="assets/img/find_user.png" class="img-responsive" />
                <span style="font-size:20px; font-weight:400; color:#fff">
                    Hola, <?= htmlspecialchars($_SESSION['username'] ?? 'Usuario') ?>
                </span>
            </li>

            <li>
                <a href="index.html"><i class="fa fa-desktop "></i>Dashboard</a>
            </li>
            <li>
                <a href="#"><i class="fa fa-edit "></i>UI Elements<span class="fa arrow"></span></a>
                <ul class="nav nav-second-level">
                    <li>
                        <a href="src/nuevo_producto.php">Registrar recetas</a>
                    </li>
                    <li>
                        <a href="src/orden_produccion.php">Ordenes de produccion</a>
                    </li>
                    <li>
                        <a href="#">Free Link</a>
                    </li>
                </ul>
            </li>

            <li>
                <a href="#"><i class="fa fa-table "></i>Table Examples</a>
            </li>
            <li>
                <a href="#"><i class="fa fa-edit "></i>Forms </a>
            </li>

            <li>
                <a href="#"><i class="fa fa-sitemap "></i>Multi-Level Dropdown<span class="fa arrow"></span></a>
                <ul class="nav nav-second-level">
                    <li>
                        <a href="#">Second Level Link</a>
                    </li>
                    <li>
                        <a href="#">Second Level Link</a>
                    </li>
                    <li>
                        <a href="#">Second Level Link<span class="fa arrow"></span></a>
                        <ul class="nav nav-third-level">
                            <li>
                                <a href="#">Third Level Link</a>
                            </li>
                            <li>
                                <a href="#">Third Level Link</a>
                            </li>
                            <li>
                                <a href="#">Third Level Link</a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </li>
            <li>
                <a href="#"><i class="fa fa-qrcode "></i>Tabs & Panels</a>
            </li>
            <li>
                <a href="#"><i class="fa fa-bar-chart-o"></i>Mettis Charts</a>
            </li>

            <li>
                <a href="#"><i class="fa fa-edit "></i>Last Link </a>
            </li>
            <li>
                <a href="blank.html"><i class="fa fa-table "></i>Blank Page</a>
            </li>
        </ul>
    </div>
</nav>