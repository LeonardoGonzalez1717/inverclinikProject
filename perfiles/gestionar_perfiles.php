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
                
                <!-- <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Usuario</button>
                    </div>
                </div> -->

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <button class="btn btn-success" id="btn-ir-crear">
                                    <i class="fas fa-plus"></i> Crear Perfil
                                </button>
                            </div>
                        </div>
                        <!-- <h5 class="subtitle">Lista de Usuarios</h5> -->
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>N°</th>
                                        <th>Usuario</th>
                                        <th>Correo</th>
                                        <th>Rol</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="listaUsuarios">
                                </tbody>
                            </table>
                        </div>
                        <div id="paginacion-perfiles"></div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <button class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>
                        <!-- <h5 class="subtitle">Crear Usuario</h5> -->
                        <form id="form-crear">
                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Nombre de Usuario <span style="color: red;">*</span></label>
                                    <input type="text" name="username" id="username" class="form-control" required 
                                        maxlength="255" placeholder="Ej: leonardo">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Correo Electrónico <span style="color: red;">*</span></label>
                                    <input type="email" name="correo" id="correo" class="form-control" required 
                                    maxlength="255" placeholder="Ej: usuario@ejemplo.com">
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Contraseña <span style="color: red;">*</span></label>
                                    <input type="password" name="password" id="password" class="form-control" 
                                        placeholder="Dejar vacío para mantener la actual (solo al editar)">
                                    <small class="form-text text-muted">Obligatorio al crear, opcional al editar</small>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Repetir Contraseña <span style="color: red;">*</span></label>
                                    <input type="password" name="password_confirm" id="password_confirm" class="form-control" 
                                        placeholder="Repita la contraseña (obligatorio al crear)">
                                    <small class="form-text text-muted">Obligatorio al crear, opcional al editar</small>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Rol <span style="color: red;">*</span></label>
                                    <select name="role_id" id="role_id" class="form-control" required>
                                        <option value="">Seleccione un rol</option>
                                        <option value="1">Admin</option>
                                        <option value="2">Supervisor</option>
                                        <option value="4">Gerencia de producción</option>
                                        <option value="5">Gerencia comercial</option>
                                        <option value="6">Gerencia administrativa</option>
                                    </select>
                                </div>
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
    $('#btn-ir-crear').on('click', function() {
        limpiarFormulario(); 
        mostrarVista('crear'); 
    });

    $('#btn-volver-listado').on('click', function() {
        Swal.fire({
            icon: 'question',
            text: '¿Desea salir? Se perderán los cambios no guardados.',
            showCancelButton: true,
            confirmButtonText: 'Sí, salir',
            cancelButtonText: 'Cancelar'
        }).then(function(r) {
            if (r.isConfirmed) {
                mostrarVista('listado');
            }
        });
    });

    $(document).ready(function() {
        cargarListado(1);
        bindCrudPagination('#paginacion-perfiles', cargarListado);
    });

    function mostrarVista(vista) {
        $('#vista-listado, #vista-crear').addClass('hidden').hide();
        $('#vista-' + vista).removeClass('hidden').fadeIn(250);
    }

    function prepararCrear() {
        $('#editar-usuario-id').val('');
        $('#username').val('').prop('readonly', false);
        $('#correo').val('').prop('readonly', false);
        $('#password').val('').prop('readonly', false).attr('required', true);
        $('#password_confirm').val('').prop('readonly', false).attr('required', true);
        $('#role_id').val('');
        $('#action').val('crear');
        mostrarVista('crear');
    }

    function limpiarFormulario() {
        $('#form-crear')[0].reset();
        $('#editar-usuario-id').val('');
        $('#action').val('crear');
        $('#password').attr('required', true);
        $('#password_confirm').attr('required', true);

        $('#username').prop('readonly', false);
        $('#correo').prop('readonly', false);
        $('#password').prop('readonly', false);
        $('#password_confirm').prop('readonly', false);

    }

    function cargarListado(page) {
        crudPostListadoPaginado(
            'gestionar_perfiles_data.php',
            { action: 'listar_html' },
            '#listaUsuarios',
            '#paginacion-perfiles',
            page || 1
        );
    }

    // $.post('gestionar_perfiles_data.php', { action: 'eliminar', id: usuarioId }, function(response) {
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
            password_confirm: $('#password_confirm').val(),
            correo: $('#correo').val(),
            role_id: $('#role_id').val()
        };

        if (formData.action === 'editar' && !formData.password) {
            delete formData.password;
            delete formData.password_confirm;
        }

        if (formData.action === 'crear') {
            if (formData.password !== formData.password_confirm) {
                $('#resultadoUsuarios').html('<div class="alert alert-danger">Las contraseñas no coinciden.</div>');
                return;
            }
        }

        $.post('gestionar_perfiles_data.php', formData, function(resp) {
            if (resp.success) {
                $('#resultadoUsuarios').html('<div class="alert alert-success">' + resp.message + '</div>');
                setTimeout(function() {
                    $('#resultadoUsuarios').html('');
                    mostrarVista('listado');
                    cargarListado(1);
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
                $('#password').val('').prop('readonly', true).removeAttr('required');
                $('#password_confirm').val('').prop('readonly', true).removeAttr('required');
                $('#correo').val(user.correo).prop('readonly', true);
                $('#role_id').val(user.role_id || ''); // este queda editable
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
        Swal.fire({
            icon: 'question',
            text: '¿Está seguro de eliminar este usuario?',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function(r) {
            if (!r.isConfirmed) return;
            $.post('gestionar_perfiles_data.php', {
                action: 'eliminar',
                id: id
            }, function(resp) {
                if (resp.success) {
                    $('#resultadoUsuarios').html('<div class="alert alert-success">' + resp.message + '</div>');
                    setTimeout(function() {
                        $('#resultadoUsuarios').html('');
                        cargarListado(1);
                    }, 1500);
                } else {
                    $('#resultadoUsuarios').html('<div class="alert alert-danger">' + resp.message + '</div>');
                }
            }, 'json').fail(function(xhr) {
                var resp = JSON.parse(xhr.responseText);
                $('#resultadoUsuarios').html('<div class="alert alert-danger">' + (resp.message || 'Error al eliminar usuario') + '</div>');
            });
        });
    }
    </script>
</body>
</html>
