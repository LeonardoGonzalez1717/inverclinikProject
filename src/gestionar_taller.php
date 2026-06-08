<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Taller</title>
</head>
<body>

<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h2 class="main-title">Almacenes</h2>
            <div id="contenedor-vistas">
                <div id="vista-listado">
                    <div class="row form-group">
                        <div class="col-sm-12">
                            <div aria-label="Acciones de Talleres">
                                <button class="btn btn-success" id="btn-ir-crear" style="margin-bottom: 0px !important;" title="Registrar Nuevo Taller" data-toggle="tooltip">
                                    <i class="fas fa-plus"></i>
                                </button>
                                <button class="btn btn-info" id="btn-toggle-filtros" title="Filtrar Lista" data-toggle="tooltip">
                                    <i class="fas fa-filter"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                        <div class="row">
                            <div class="col-sm-6">
                                <label for="filtro-buscar">Buscar Taller</label>
                                <input type="text" id="filtro-buscar" class="form-control clase-filtro-taller" placeholder="Buscar por nombre o descripción...">
                            </div>
                            <div class="col-sm-3">
                                <label for="filtro-activo">Estado</label>
                                <select id="filtro-activo" class="form-control clase-filtro-taller">
                                    <option value="">Todos</option>
                                    <option value="1">Activos</option>
                                    <option value="0">Inactivos</option>
                                </select>
                            </div>
                            <div class="col-sm-3" style="margin-top: 25px;">
                                <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros-tal">
                                    <i class="fas fa-eraser"></i> Limpiar
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr class="bg-navy">
                                    <th style="width: 70px;">#</th>
                                    <th>Nombre del Taller</th>
                                    <th>Descripción</th>
                                    <th style="width: 120px;">Estado</th>
                                    <th style="width: 180px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                </tbody>
                        </table>
                        <div id="paginacion-talleres"></div>
                    </div>
                </div>

                <div id="vista-crear" class="hidden" style="display: none;">
                    <div class="row">
                        <div class="col-sm-12">
                            <h3 id="subtitle-form-taller" class="box-title">Registrar Nuevo Taller</h3>
                            <hr>
                        </div>
                    </div>

                    <form id="form-taller" autocomplete="off">
                        <input type="hidden" id="editar-taller-id" name="id" value="">

                        <div class="row">
                            <div class="col-sm-8 form-group">
                                <label for="nombre">Nombre del Taller <span class="text-danger">*</span></label>
                                <input type="text" id="nombre" name="nombre" class="form-control" placeholder="Ej: Taller de Costura El Centro" required maxlength="150">
                            </div>

                            <div class="col-sm-4 form-group">
                                <label for="activo">Estado del Taller</label>
                                <select id="activo" name="activo" class="form-control">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-sm-12 form-group">
                                <label for="descripcion">Descripción</label>
                                <textarea id="descripcion" name="descripcion" class="form-control" rows="4" placeholder="Especificar ubicación, especialidad de costura, capacidad de producción mensual..."></textarea>
                            </div>
                        </div>

                        <div class="row" style="margin-top: 15px;">
                            <div class="col-sm-12">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cambios</button>
                                <button type="button" class="btn btn-secondary" onclick="limpiarFormulario(); mostrarVista('listado');">Cancelar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var temporizadorBusquedaTalleres;

function mostrarVista(vista) {
    $('#vista-listado, #vista-crear').addClass('hidden').hide();
    $('#vista-' + vista).removeClass('hidden').fadeIn(200);
}

function limpiarFormulario() {
    $('#form-taller')[0].reset();
    $('#editar-taller-id').val('');
    $('#activo').val('1');
    $('#subtitle-form-taller').text('Registrar Nuevo Taller');
}

function cargarListado(page) {
    var params = { 
        action: 'listar_html',
        buscar: $('#filtro-buscar').val(),
        activo: $('#filtro-activo').val()
    };

    crudPostListadoPaginado(
        'gestionar_taller_data.php',
        params,
        '#vista-listado tbody',
        '#paginacion-talleres',
        page || 1
    );
}

function editarTaller(data) {
    $('#editar-taller-id').val(data.id);
    $('#nombre').val(data.nombre || '');
    $('#descripcion').val(data.descripcion || '');
    $('#activo').val(data.activo == 1 || data.activo === '1' ? '1' : '0');
    
    $('#subtitle-form-taller').text('Editar Taller: ' + data.nombre);
    mostrarVista('crear');
}

function eliminarTaller(id) {
    Swal.fire({
        icon: 'question',
        text: '¿Está seguro de eliminar este taller del sistema?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function (r) {
        if (!r.isConfirmed) return;
        $.ajax({
            url: 'gestionar_taller_data.php',
            type: 'POST',
            data: { action: 'eliminar', id: id },
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    cargarListado(1);
                } else {
                    Swal.fire({ icon: 'error', text: 'Error: ' + (resp ? resp.message : 'Error desconocido') });
                }
            },
            error: function () {
                Swal.fire({ icon: 'error', text: 'Error de conexión al eliminar.' });
            }
        });
    });
}

// --- INICIALIZADORES DEL DOM ---
$(function() {
    mostrarVista('listado');
    cargarListado(1);
    bindCrudPagination('#paginacion-talleres', cargarListado);

    // Botones de navegación
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
                cargarListado(1);
            }
        });
    });

    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    // Filtros dinámicos con Debounce de 350ms
    $('.clase-filtro-taller').on('change keyup', function(e) {
        if (e.type === 'change') {
            cargarListado(1);
            return;
        }
        clearTimeout(temporizadorBusquedaTalleres);
        temporizadorBusquedaTalleres = setTimeout(function() {
            cargarListado(1);
        }, 350);
    });

    $('#btn-limpiar-filtros-tal').on('click', function() {
        $('#filtro-buscar').val('');
        $('#filtro-activo').val('');
        cargarListado(1);
    });

    // --- SUBMIT DEL FORMULARIO (GUARDAR / MODIFICAR) ---
    $('#form-taller').on('submit', function (e) {
        e.preventDefault();
        
        var nombre = $('#nombre').val().trim();
        if (nombre === '') {
            Swal.fire({ icon: 'warning', text: 'El nombre del taller es obligatorio.' });
            return;
        }

        var idTaller = $('#editar-taller-id').val();
        var datos = {
            action: idTaller ? 'editar' : 'crear',
            id: idTaller || null,
            nombre: nombre,
            descripcion: $('#descripcion').val().trim(),
            activo: parseInt($('#activo').val(), 10) === 1 ? 1 : 0
        };

        $.ajax({
            url: 'gestionar_taller_data.php',
            type: 'POST',
            data: datos,
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    limpiarFormulario();
                    mostrarVista('listado');
                    cargarListado(1);
                } else {
                    Swal.fire({ icon: 'error', text: 'Error: ' + (resp ? resp.message : 'Error interno') });
                }
            },
            error: function () {
                Swal.fire({ icon: 'error', text: 'Error de comunicación con el servidor.' });
            }
        });
    });
});
</script>

<?php 
require_once "../template/footer.php"; 
?>