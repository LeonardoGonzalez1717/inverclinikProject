<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rangos de Tallas</title>
</head>
<body>
<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h2 class="main-title">Rangos de Tallas</h2>
            <p class="text-muted" style="margin-bottom: 20px;">
                Use solo dos rangos: <strong>Niños</strong> (tallas 2, 4, 6…) y <strong>Adultos</strong> (XS, S, M, L, XL…). Las letras de talla van dentro del rango, no como rangos separados.
            </p>

            <div id="contenedor-vistas">
                <div id="vista-listado">
                    <div class="row form-group">
                        <div class="col-sm-4">
                            <button class="btn btn-success" id="btn-ir-crear">
                                <i class="fas fa-plus"></i> Crear Rango de Tallas
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Nombre del rango</th>
                                    <th>Tallas</th>
                                    <th>Cant.</th>
                                    <th>Descripción</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div id="paginacion-rangos-tallas"></div>
                </div>

                <div id="vista-crear" class="hidden">
                    <div class="row form-group">
                        <div class="col-sm-4">
                            <button class="btn-volver" id="btn-volver-listado">
                                <i class="fas fa-arrow-left"></i> Volver al Listado
                            </button>
                        </div>
                    </div>
                    <form id="form-rango-tallas">
                        <input type="hidden" id="editar-rango-id" value="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre del rango <span style="color:red">*</span></label>
                                <input type="text" name="nombre_rango" id="nombre_rango" class="form-control" required maxlength="50" placeholder="Ej: Niños o Adultos">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Descripción</label>
                                <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="100" placeholder="Opcional">
                            </div>
                        </div>

                        <hr style="margin: 20px 0; border-color: #dee2e6;">

                        <div class="col-sm-12">
                            <h5 style="color: #0056b3; margin-bottom: 15px;">Tallas del rango</h5>

                            <div class="card" style="padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">
                                <div class="row align-items-end">
                                    <div class="col-md-8 mb-2">
                                        <label class="form-label">Nombre de la talla</label>
                                        <input type="text" id="nueva-talla-nombre" class="form-control" maxlength="20" placeholder="Niños: 2, 4, 6… — Adultos: XS, S, M, L, XL">
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <button type="button" class="btn btn-success" id="btn-agregar-talla" style="width: 100%;">
                                            <i class="fa fa-plus"></i> Agregar talla
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div id="lista-tallas">
                                <table class="table" id="tabla-tallas" style="display: none;">
                                    <thead style="background-color: #0056b3; color: white;">
                                        <tr>
                                            <th style="color: black;">#</th>
                                            <th style="color: black;">Talla</th>
                                            <th style="color: black;">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody-tallas"></tbody>
                                </table>
                                <div id="mensaje-sin-tallas" class="alert alert-info" style="text-align: center;">
                                    <i class="fa fa-info-circle"></i> Agrega al menos una talla al rango
                                </div>
                            </div>
                        </div>

                        <hr style="margin: 20px 0; border-color: #dee2e6;">

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary" id="btn-guardar-rango" disabled>Guardar rango</button>
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
var tallasAgregadas = [];

$('#btn-ir-crear').on('click', function() {
    $('#vista-listado').fadeOut(200, function() {
        $('#vista-crear').removeClass('hidden').fadeIn();
        limpiarFormulario();
    });
});

$('#btn-volver-listado').on('click', function() {
    Swal.fire({
        icon: 'question',
        text: '¿Desea salir? Se perderán los cambios no guardados.',
        showCancelButton: true,
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $('#vista-crear').fadeOut(200, function() {
            $('#vista-listado').fadeIn();
        });
    });
});

function mostrarVista(vista) {
    document.querySelectorAll('#contenedor-vistas > div').forEach(function(el) {
        el.classList.add('hidden');
    });
    document.getElementById('vista-' + vista).classList.remove('hidden');
}

function cargarListado(page) {
    crudPostListadoPaginado(
        'registrar_rangos_tallas_data.php',
        { action: 'listar_html' },
        '#vista-listado tbody',
        '#paginacion-rangos-tallas',
        page || 1
    );
}

function actualizarTablaTallas() {
    var tbody = $('#tbody-tallas');
    tbody.empty();

    if (tallasAgregadas.length === 0) {
        $('#tabla-tallas').hide();
        $('#mensaje-sin-tallas').show();
        $('#btn-guardar-rango').prop('disabled', true);
        return;
    }

    $('#tabla-tallas').show();
    $('#mensaje-sin-tallas').hide();
    $('#btn-guardar-rango').prop('disabled', false);

    tallasAgregadas.forEach(function(nombre, index) {
        tbody.append(
            '<tr>' +
                '<td>' + (index + 1) + '</td>' +
                '<td>' + $('<div>').text(nombre).html() + '</td>' +
                '<td><button type="button" class="btn btn-sm btn-danger" onclick="quitarTalla(' + index + ')"><i class="fa fa-trash"></i></button></td>' +
            '</tr>'
        );
    });
}

function agregarTalla() {
    var nombre = $('#nueva-talla-nombre').val().trim();
    if (!nombre) {
        Swal.fire({ icon: 'warning', text: 'Ingrese el nombre de la talla.' });
        return;
    }

    var duplicada = tallasAgregadas.some(function(t) {
        return t.toLowerCase() === nombre.toLowerCase();
    });
    if (duplicada) {
        Swal.fire({ icon: 'warning', text: 'Esta talla ya fue agregada al rango.' });
        return;
    }

    tallasAgregadas.push(nombre);
    $('#nueva-talla-nombre').val('').focus();
    actualizarTablaTallas();
}

function quitarTalla(index) {
    tallasAgregadas.splice(index, 1);
    actualizarTablaTallas();
}

function limpiarFormulario() {
    $('#editar-rango-id').val('');
    $('#form-rango-tallas')[0].reset();
    tallasAgregadas = [];
    actualizarTablaTallas();
}

$('#btn-agregar-talla').on('click', agregarTalla);

$('#nueva-talla-nombre').on('keypress', function(e) {
    if (e.which === 13) {
        e.preventDefault();
        agregarTalla();
    }
});

$('#form-rango-tallas').on('submit', function(e) {
    e.preventDefault();

    if (tallasAgregadas.length === 0) {
        Swal.fire({ icon: 'warning', text: 'Debe agregar al menos una talla al rango.' });
        return;
    }

    var idEdit = $('#editar-rango-id').val();
    var datos = {
        nombre_rango: $('#nombre_rango').val().trim(),
        descripcion: $('#descripcion').val().trim(),
        tallas: tallasAgregadas.slice()
    };

    if (idEdit) {
        datos.action = 'editar';
        datos.id = parseInt(idEdit, 10);
    } else {
        datos.action = 'crear';
    }

    $.ajax({
        url: 'registrar_rangos_tallas_data.php',
        type: 'POST',
        data: JSON.stringify(datos),
        contentType: 'application/json',
        dataType: 'json',
        success: function(resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message });
                limpiarFormulario();
                $('#vista-crear').fadeOut(200, function() {
                    $('#vista-listado').fadeIn();
                });
                cargarListado(1);
            } else {
                Swal.fire({ icon: 'error', text: 'Error: ' + (resp ? resp.message : 'Error desconocido') });
            }
        },
        error: function(xhr) {
            try {
                var r = JSON.parse(xhr.responseText);
                Swal.fire({ icon: 'error', text: 'Error: ' + (r.message || xhr.responseText) });
            } catch (e) {
                Swal.fire({ icon: 'error', text: 'Error de conexión.' });
            }
        }
    });
});

function editarRangoTallas(id) {
    $.ajax({
        url: 'registrar_rangos_tallas_data.php',
        type: 'POST',
        data: JSON.stringify({ action: 'obtener', id: id }),
        contentType: 'application/json',
        dataType: 'json',
        success: function(resp) {
            if (!resp || !resp.success || !resp.rango) {
                Swal.fire({ icon: 'error', text: 'No se pudo cargar el rango de tallas.' });
                return;
            }

            var r = resp.rango;
            $('#editar-rango-id').val(String(r.id));
            $('#nombre_rango').val(r.nombre_rango || '');
            $('#descripcion').val(r.descripcion != null ? String(r.descripcion) : '');
            tallasAgregadas = (r.tallas || []).map(function(t) { return t.nombre; });
            actualizarTablaTallas();

            $('#vista-listado').fadeOut(200, function() {
                $('#vista-crear').removeClass('hidden').fadeIn();
            });
        },
        error: function(xhr) {
            try {
                var r = JSON.parse(xhr.responseText);
                Swal.fire({ icon: 'error', text: 'Error: ' + (r.message || xhr.responseText) });
            } catch (e) {
                Swal.fire({ icon: 'error', text: 'Error de conexión.' });
            }
        }
    });
}

function eliminarRangoTallas(id) {
    Swal.fire({
        icon: 'question',
        text: '¿Desea eliminar este rango de tallas?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.ajax({
            url: 'registrar_rangos_tallas_data.php',
            type: 'POST',
            data: JSON.stringify({ action: 'eliminar', id: id }),
            contentType: 'application/json',
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    cargarListado(1);
                } else {
                    Swal.fire({ icon: 'error', text: 'Error: ' + (resp ? resp.message : 'Error desconocido') });
                }
            },
            error: function(xhr) {
                try {
                    var r = JSON.parse(xhr.responseText);
                    Swal.fire({ icon: 'error', text: 'Error: ' + (r.message || xhr.responseText) });
                } catch (e) {
                    Swal.fire({ icon: 'error', text: 'Error de conexión.' });
                }
            }
        });
    });
}

$(document).ready(function() {
    mostrarVista('listado');
    cargarListado(1);
    bindCrudPagination('#paginacion-rangos-tallas', cargarListado);
});
</script>

<?php
$conn->close();
require_once "../template/footer.php";
?>
</body>
</html>
