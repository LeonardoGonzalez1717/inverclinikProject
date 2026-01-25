<?php
require_once('../template/header.php');
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Perfiles</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Perfiles</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Usuario</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Usuarios</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Usuario</th>
                                        <th>Correo</th>
                                        <th>Login</th>
                                        <th>Rol</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="listaUsuarios">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Crear Usuario</h5>
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre de Usuario <span style="color: red;">*</span></label>
                                <input type="text" name="username" id="username" class="form-control" required 
                                       maxlength="255" placeholder="Ej: leonardo">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Contraseña <span style="color: red;">*</span></label>
                                <input type="password" name="password" id="password" class="form-control" 
                                       placeholder="Dejar vacío para mantener la actual (solo al editar)">
                                <small class="form-text text-muted">Obligatorio al crear, opcional al editar</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Login <span style="color: red;">*</span></label>
                                <input type="text" name="login" id="login" class="form-control" required 
                                       maxlength="255" placeholder="Ej: leo1">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Correo Electrónico <span style="color: red;">*</span></label>
                                <input type="email" name="correo" id="correo" class="form-control" required 
                                       maxlength="255" placeholder="Ej: usuario@ejemplo.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rol <span style="color: red;">*</span></label>
                                <select name="rol" id="rol" class="form-control" required>
                                    <option value="">Seleccione un rol</option>
                                    <option value="superadmin">Superadmin</option>
                                    <option value="administrador">Administrador</option>
                                    <option value="cliente">Cliente</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Usuario</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-usuario-id" name="id" value="">
                            <input type="hidden" id="action" value="">
                        </form>
                    </div>
                </div>

                <div id="resultadoUsuarios" class="mt-3"></div>
            </div>
        </div>
    </div>

    <script>
    $(document).ready(function() {
        cargarListado();
    });

    function mostrarVista(vista) {
        if (vista === 'crear') {
            $('#vista-listado').addClass('hidden');
            $('#vista-crear').removeClass('hidden');
            $('#vista-botones').hide();
        } else {
            $('#vista-crear').addClass('hidden');
            $('#vista-listado').removeClass('hidden');
            $('#vista-botones').show();
        }
    }

    function prepararCrear() {
        $('#editar-usuario-id').val('');
        $('#username').val('').prop('readonly', false);
        $('#login').val('').prop('readonly', false);
        $('#correo').val('').prop('readonly', false);
        $('#password').val('').prop('readonly', false).attr('required', true);
        $('#rol').val('');
        $('#action').val('crear');
        mostrarVista('crear');
    }

    function limpiarFormulario() {
        $('#form-crear')[0].reset();
        $('#editar-usuario-id').val('');
        $('#action').val('crear');
        $('#password').attr('required', true);

        $('#username').prop('readonly', false);
        $('#login').prop('readonly', false);
        $('#correo').prop('readonly', false);
        $('#password').prop('readonly', false);

    }

    function cargarListado() {
        $.post('gestionar_perfiles_data.php', {
            action: 'listar_html'
        }, function(html) {
            $('#listaUsuarios').html(html);
        }).fail(function() {
            $('#listaUsuarios').html('<tr><td colspan="6" class="text-center text-danger">Error al cargar usuarios</td></tr>');
        });
    }

    // $.post('gestionar_perfiles_data.php', { action: 'eliminar', id: usuarioId }, function(response) {
    //     alert(response.message);
    //     if (response.success) {
    //         cargarListado();
    //     }
    // }, 'json');

    $('#form-crear').on('submit', function(e) {
        e.preventDefault();
        
        var formData = {
            action: $('#editar-usuario-id').val() ? 'editar' : 'crear',
            id: $('#editar-usuario-id').val() || null,
            username: $('#username').val(),
            password: $('#password').val(),
            login: $('#login').val(),
            correo: $('#correo').val(),
            rol: $('#rol').val()
        };

        if (formData.action === 'editar' && !formData.password) {
            delete formData.password;
        }

        $.post('gestionar_perfiles_data.php', formData, function(resp) {
            if (resp.success) {
                $('#resultadoUsuarios').html('<div class="alert alert-success">' + resp.message + '</div>');
                setTimeout(function() {
                    $('#resultadoUsuarios').html('');
                    mostrarVista('listado');
                    cargarListado();
                }, 1500);
            } else {
                $('#resultadoUsuarios').html('<div class="alert alert-danger">' + resp.message + '</div>');
            }
        }, 'json').fail(function(xhr) {
            var resp = JSON.parse(xhr.responseText);
            $('#resultadoUsuarios').html('<div class="alert alert-danger">' + (resp.message || 'Error al procesar la solicitud') + '</div>');
        });
    });

    function editarUsuario(id) {
        $.post('gestionar_perfiles_data.php', {
            action: 'obtener',
            id: id
        }, function(resp) {
            if (resp.success) {
                var user = resp.usuario;

                $('#editar-usuario-id').val(user.id);
                $('#username').val(user.username).prop('readonly', true);
                $('#password').val('').prop('readonly', true);
                $('#login').val(user.login).prop('readonly', true);
                $('#correo').val(user.correo).prop('readonly', true);
                $('#rol').val(user.rol || ''); // este queda editable
                $('#action').val('editar');

                mostrarVista('crear');
            } else {
                $('#resultadoUsuarios').html('<div class="alert alert-danger">' + resp.message + '</div>');
            }
        }, 'json').fail(function(xhr) {
            var resp = JSON.parse(xhr.responseText);
            $('#resultadoUsuarios').html('<div class="alert alert-danger">' + (resp.message || 'Error al cargar usuario') + '</div>');
        });
    }

    function eliminarUsuario(id) {
        if (!confirm('¿Está seguro de eliminar este usuario?')) {
            return;
        }

        $.post('gestionar_perfiles_data.php', {
            action: 'eliminar',
            id: id
        }, function(resp) {
            if (resp.success) {
                $('#resultadoUsuarios').html('<div class="alert alert-success">' + resp.message + '</div>');
                setTimeout(function() {
                    $('#resultadoUsuarios').html('');
                    cargarListado();
                }, 1500);
            } else {
                $('#resultadoUsuarios').html('<div class="alert alert-danger">' + resp.message + '</div>');
            }
        }, 'json').fail(function(xhr) {
            var resp = JSON.parse(xhr.responseText);
            $('#resultadoUsuarios').html('<div class="alert alert-danger">' + (resp.message || 'Error al eliminar usuario') + '</div>');
        });
    }
    </script>
</body>
</html>
