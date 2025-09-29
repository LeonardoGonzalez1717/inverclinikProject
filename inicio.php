<?php 
require_once "template/header.php";
require_once 'helpers/Modal.php';

$id = $_GET['iduser'] ?? 1;
$sql = "SELECT * from users where id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

$formularioCrearUsuario = '
    <form id="formCrearUsuario">
        <div class="form-group">
            <label for="username">Nombre de usuario</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="correo">Correo electrónico</label>
            <input type="email" class="form-control" id="correo" name="correo" required>
        </div>
        <div class="form-group">
            <label for="password">Contraseña</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
    </form>
';

$footerCrearUsuario = '
    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
    <button type="button" class="btn btn-primary" id="guardarUsuario">Guardar</button>
';


$botonesUsuario = [
    ['texto' => 'Ver Perfil', 'icono' => 'fa-user', 'clase' => '', 'onclick' => 'verPerfil()'],
    ['texto' => 'Gestionar Usuarios', 'icono' => 'fa-cog', 'clase' => '', 'onclick' => 'gestionarUsuario()'],
    ['texto' => 'Editar Perfil', 'icono' => 'fa-pencil', 'clase' => '', 'onclick' => 'abrirEditarPerfil(usuarioActual)'],
    ['texto' => 'Cambiar Contraseña', 'icono' => 'fa-lock', 'clase' => '', 'onclick' => 'cambiarPass()'],
    ['texto' => 'Crear nuevo usuario', 'icono' => 'fa-plus', 'clase' => 'text-danger', 'data_toggle' => 'modal', 'data_target' => '#crearUsuarioModal'],
    ['texto' => 'Cerrar Sesión', 'icono' => 'fa-sign-out', 'clase' => 'text-danger', 'onclick' => 'cerrarSesion()']
];

echo Modal::crear([
    'id' => 'userManagementModal',
    'titulo' => 'Gestión de Usuario',
    'mensaje' => '¿Qué deseas hacer?',
    'botones' => $botonesUsuario
]);
echo Modal::crear([
    'id' => 'crearUsuarioModal',
    'titulo' => 'Crear Nuevo Usuario',
    'contenido' => $formularioCrearUsuario,
    'footer' => $footerCrearUsuario
]);
?>
<body>
    <div id="wrapper">
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
                        <span style="font-size:20px; font-weight:400; color:#fff">Hola, <?=$row['username']?></span>
                    </li>


                    <li>
                        <a href="index.html"><i class="fa fa-desktop "></i>Dashboard</a>
                    </li>
                    <li>
                        <a href="#"><i class="fa fa-edit "></i>UI Elements<span class="fa arrow"></span></a>
                        <ul class="nav nav-second-level">
                            <li>
                                <a href="#">Notifications</a>
                            </li>
                            <li>
                                <a href="#">Elements</a>
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

        <div id="page-wrapper">
            <div id="page-inner" style="position: absolute; top: 41px">
                <div class="row">
                    <div class="col-md-12">
                        <h2>Admin Dashboard</h2>
                    </div>
                </div>
                <!-- /. ROW  -->
                <hr />
                <div class="row">
                    <div class="col-md-3 col-sm-3 col-xs-6">
                        <h5>Widget Box One</h5>
                        <div class="panel panel-primary text-center no-boder bg-color-blue">
                            <div class="panel-body">
                                <i class="fa fa-bar-chart-o fa-5x"></i>
                                <h3>120 GB </h3>
                            </div>
                            <div class="panel-footer back-footer-blue">
                                Disk Space Available
                            
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 col-sm-3 col-xs-6">
                        <h5>Widget Box Two</h5>
                        <div class="alert alert-info text-center">
                            <i class="fa fa-desktop fa-5x"></i>
                            <h3>100$ </h3>
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit.
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h5>Buttons Samples</h5>
                        <a href="#" class="btn btn-default">default</a>
                        <a href="#" class="btn btn-primary">primary</a>
                        <a href="#" class="btn btn-danger">danger</a>
                        <a href="#" class="btn btn-success">success</a>
                        <a href="#" class="btn btn-info">info</a>
                        <a href="#" class="btn btn-warning">warning</a>
                        <br />
                        <br />
                        <h5>Progressbar Samples</h5>
                        <div class="progress progress-striped">
                            <div class="progress-bar progress-bar-success" role="progressbar" aria-valuenow="40" aria-valuemin="0" aria-valuemax="100" style="width: 40%">
                                <span class="sr-only">40% Complete (success)</span>
                            </div>
                        </div>
                        <div class="progress progress-striped active">
                            <div class="progress-bar progress-bar-primary" role="progressbar" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100" style="width: 20%">
                                <span class="sr-only">20% Complete</span>
                            </div>
                        </div>


                    </div>

                </div>

                <hr />
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Text Input Example</label>
                            <input class="form-control" />
                            <p class="help-block">Help text here.</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label>Click to see blank page</label>
                        <a href="blank.html" target="_blank" class="btn btn-danger btn-lg btn-block">BLANK PAGE</a>
                    </div>
                    <div class="col-md-4">
                        For More Examples Please visit official bootstrap website <a href="http://getbootstrap.com" target="_blank">getbootstrap.com</a>
                    </div>
                </div>
                <hr />
                <div class="row">
                    <div class="col-md-6">
                        <h5>Table  Sample One</h5>
                        <table class="table table-striped table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>First Name</th>
                                    <th>Last Name</th>
                                    <th>Username</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>1</td>
                                    <td>Mark</td>
                                    <td>Otto</td>
                                    <td>@mdo</td>
                                </tr>
                                <tr>
                                    <td>2</td>
                                    <td>Jacob</td>
                                    <td>Thornton</td>
                                    <td>@fat</td>
                                </tr>
                                <tr>
                                    <td>1</td>
                                    <td>Mark</td>
                                    <td>Otto</td>
                                    <td>@mdo</td>
                                </tr>
                                <tr>
                                    <td>3</td>
                                    <td>Larry</td>
                                    <td>the Bird</td>
                                    <td>@twitter</td>
                                </tr>
                            </tbody>
                        </table>

                    </div>
                    <div class="col-md-6">
                        <h5>Table  Sample Two</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>First Name</th>
                                        <th>Last Name</th>
                                        <th>Username</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr class="success">
                                        <td>1</td>
                                        <td>Mark</td>
                                        <td>Otto</td>
                                        <td>@mdo</td>
                                    </tr>
                                    <tr class="info">
                                        <td>2</td>
                                        <td>Jacob</td>
                                        <td>Thornton</td>
                                        <td>@fat</td>
                                    </tr>
                                    <tr class="warning">
                                        <td>3</td>
                                        <td>Larry</td>
                                        <td>the Bird</td>
                                        <td>@twitter</td>
                                    </tr>
                                    <tr class="danger">
                                        <td>4</td>
                                        <td>John</td>
                                        <td>Smith</td>
                                        <td>@jsmith</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- /. ROW  -->
                <hr />


                <div class="row">
                    <div class="col-md-4">
                        <h5>Panel Sample</h5>
                        <div class="panel panel-primary">
                            <div class="panel-heading">
                                Default Panel
                            </div>
                            <div class="panel-body">
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Vestibulum tincidunt est vitae ultrices accumsan. Aliquam ornare lacus adipiscing, posuere lectus et, fringilla augue.</p>
                            </div>
                            <div class="panel-footer">
                                Panel Footer
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h5>Accordion Sample</h5>
                        <div class="panel-group" id="accordion">
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne" class="collapsed">Collapsible Group Item #1</a>
                                    </h4>
                                </div>
                                <div id="collapseOne" class="panel-collapse collapse" style="height: 0px;">
                                    <div class="panel-body">
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt.
                                    </div>
                                </div>
                            </div>
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" data-parent="#accordion" href="#collapseTwo">Collapsible Group Item #2</a>
                                    </h4>
                                </div>
                                <div id="collapseTwo" class="panel-collapse in" style="height: auto;">
                                    <div class="panel-body">
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt.

                                    </div>
                                </div>
                            </div>
                            <div class="panel panel-default">
                                <div class="panel-heading">
                                    <h4 class="panel-title">
                                        <a data-toggle="collapse" data-parent="#accordion" href="#collapseThree" class="collapsed">Collapsible Group Item #3</a>
                                    </h4>
                                </div>
                                <div id="collapseThree" class="panel-collapse collapse">
                                  

                                        <div class="panel-body">
                                             Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod tempor incididunt.
                                        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <h5>Tabs Sample</h5>
                        <ul class="nav nav-tabs">
                            <li class="active"><a href="#home" data-toggle="tab">Home</a>
                            </li>
                            <li class=""><a href="#profile" data-toggle="tab">Profile</a>
                            </li>
                            <li class=""><a href="#messages" data-toggle="tab">Messages</a>
                            </li>

                        </ul>
                        <div class="tab-content">
                            <div class="tab-pane fade active in" id="home">
                                <h4>Home Tab</h4>
                                <p>
                                    Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                </p>
                            </div>
                            <div class="tab-pane fade" id="profile">
                                <h4>Profile Tab</h4>
                                <p>
                                    Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                </p>

                            </div>
                            <div class="tab-pane fade" id="messages">
                                <h4>Messages Tab</h4>
                                <p >
                                    Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                        Lorem ipsum dolor sit amet, consectetur adipisicing elit eserunt mollit anim id est laborum.
                                </p>

                            </div>

                        </div>
                    </div>

                </div>
                <!-- /. ROW  -->
                <hr />
                  <div class="row">
                    <div class="col-md-12">
                        <h5>Information</h5>
                            This is a type of bare admin that means you can customize your own admin using this admin structured  template . For More Examples of bootstrap elements or components please visit official bootstrap website <a href="http://getbootstrap.com" target="_blank">getbootstrap.com</a>
                        . And if you want full template please download <a href="http://www.binarytheme.com/bootstrap-free-admin-dashboard-template/" target="_blank" >FREE BCORE ADMIN </a>&nbsp;,&nbsp;  <a href="http://www.binarytheme.com/free-bootstrap-admin-template-siminta/" target="_blank" >FREE SIMINTA ADMIN</a> and <a href="http://binarycart.com/" target="_blank" >FREE BINARY ADMIN</a>.

                    </div>
                </div>

            </div>
        </div>
        
    </div>
    <?php require 'template/footer.php' ?>

    <script src="assets/js/jquery-1.10.2.js"></script>

    <script src="assets/js/bootstrap.min.js"></script>

    <script src="assets/js/jquery.metisMenu.js"></script>

    <script src="assets/js/custom.js"></script>


</body>
</html>
<script>
    // Datos del usuario actual (para edición)
    const usuarioActual = {
        id: <?= (int)$row['id'] ?>,
        username: <?= json_encode($row['username']) ?>,
        correo: <?= json_encode($row['email'] ?? $row['correo']) ?>
    };
</script>
<script>
    // Modo actual del modal: 'crear' o 'editar'
    let modoModal = 'crear';

    function userManagement() {
        $('#userManagementModal').modal('show');
    }

    // Abrir modal en modo CREAR
    $('#crearUsuarioModal').on('show.bs.modal', function () {
        if (modoModal === 'crear') {
            $('#userManagementModal').modal('hide');
            $('#crearUsuarioModalLabel').text('Crear Nuevo Usuario');
            $('#guardarUsuario').text('Guardar').removeClass('btn-warning').addClass('btn-primary');
            $('#password').closest('.form-group').show(); // Mostrar campo de contraseña
            $('#formCrearUsuario')[0].reset();
        }
    });

    // Limpiar al cerrar
    $('#crearUsuarioModal').on('hidden.bs.modal', function () {
        modoModal = 'crear';
        $('#password').closest('.form-group').show();
    });

    // ✅ NUEVA FUNCIÓN: Abrir modal en modo EDICIÓN
    function abrirEditarPerfil(usuario) {
        modoModal = 'editar';
        
        // Llenar los campos
        $('#username').val(usuario.username);
        $('#correo').val(usuario.correo);
        
        // Ocultar campo de contraseña (opcional, o puedes dejarlo para cambiarla)
        $('#password').closest('.form-group').hide();
        
        // Cambiar título y botón
        $('#crearUsuarioModalLabel').text('Editar Perfil');
        $('#guardarUsuario')
            .text('Actualizar')
            .removeClass('btn-primary')
            .addClass('btn-warning');
        
        // Mostrar el modal
        $('#crearUsuarioModal').modal('show');
    }

    // Manejar el clic en "Guardar/Actualizar"
    $("#guardarUsuario").click(function() {
        const username = $("#username").val();
        const correo = $("#correo").val();
        const password = $("#password").val();

        if (!username || !correo) {
            alert("Completa todos los campos obligatorios");
            return;
        }

        if (modoModal === 'crear') {
            if (!password) {
                alert("La contraseña es obligatoria al crear un usuario");
                return;
            }
            // Crear nuevo usuario
            $.post("user_options/crear_usuario_data.php", {
                username: username,
                correo: correo,
                password: password
            }, function(resp) {
                if (resp.success) {
                    alert("Usuario creado");
                    $("#crearUsuarioModal").modal("hide");
                } else {
                    alert("Error: " + resp.message);
                }
            }, "json");
        } else if (modoModal === 'editar') {
            // Actualizar usuario actual
            $.post("user_options/actualizar_usuario_data.php", {
                id: usuarioActual.id,
                username: username,
                correo: correo
                // Si quieres permitir cambiar contraseña, descomenta:
                // password: password || null
            }, function(resp) {
                if (resp.success) {
                    alert("Perfil actualizado");
                    $("#crearUsuarioModal").modal("hide");
                    // Opcional: actualizar el nombre en la barra lateral
                    $('.user-image-back span').text('Hola, ' + username);
                } else {
                    alert("Error: " + resp.message);
                }
            }, "json");
        }
    });

    function gestionarUsuario(){
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'users_management.php';
        document.body.appendChild(form);
        form.submit();
    }
</script>