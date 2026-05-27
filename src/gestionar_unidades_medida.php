<?php
require_once "../template/header.php";
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unidades de medida</title>
</head>
<body>
<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h2 class="main-title">Unidades de medida</h2>
            <div id="contenedor-vistas">
                <div id="vista-listado">
                    <div class="row mb-3">
                        <div class="col-md-12">
                            <button type="button" class="btn btn-success" id="btn-ir-crear">
                                <i class="fas fa-plus"></i> Nueva unidad
                            </button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Código</th>
                                    <th>Nombre</th>
                                    <th>Decimales en inventario</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <div id="paginacion-unidades-medida"></div>
                </div>

                <div id="vista-crear" class="hidden">
                    <button type="button" class="btn-volver" id="btn-volver-listado">
                        <i class="fas fa-arrow-left"></i> Volver al listado
                    </button>
                    <form id="form-um">
                        <input type="hidden" id="editar-um-id" value="">
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Código <span style="color:red">*</span></label>
                                <input type="text" name="codigo" id="codigo" class="form-control" maxlength="32" required
                                       placeholder="ej: metro, paquete, unidad">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Nombre para mostrar <span style="color:red">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" maxlength="100" required
                                       placeholder="Ej.: Metro, Paquete">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">
                                    <input type="checkbox" name="permite_movimiento_decimal" id="permite_movimiento_decimal" value="1" checked>
                                    Permitir movimientos de inventario con decimales
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                        <button type="button" class="btn btn-secondary" id="btn-cancelar-form">Cancelar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mostrarVista(v) {
    $('#vista-listado, #vista-crear').addClass('hidden').hide();
    $('#vista-' + v).removeClass('hidden').fadeIn(200);
}

function cargarListado(page) {
    crudPostListadoPaginado(
        'gestionar_unidades_medida_data.php',
        { action: 'listar_html' },
        '#vista-listado tbody',
        '#paginacion-unidades-medida',
        page || 1
    );
}

function limpiarFormulario() {
    $('#editar-um-id').val('');
    $('#form-um')[0].reset();
    $('#permite_movimiento_decimal').prop('checked', true);
}

function editarUm(data) {
    $('#editar-um-id').val(data.id);
    $('#codigo').val(data.codigo || '');
    $('#nombre').val(data.nombre || '');
    $('#permite_movimiento_decimal').prop('checked', data.permite_movimiento_decimal == 1 || data.permite_movimiento_decimal === '1');
    mostrarVista('crear');
}

$('#btn-ir-crear').on('click', function() {
    limpiarFormulario();
    mostrarVista('crear');
});

$('#btn-volver-listado, #btn-cancelar-form').on('click', function() {
    mostrarVista('listado');
    cargarListado();
});

$('#form-um').on('submit', function(e) {
    e.preventDefault();
    var id = $('#editar-um-id').val();
    var datos = {
        action: id ? 'editar' : 'crear',
        codigo: $('#codigo').val(),
        nombre: $('#nombre').val(),
        permite_movimiento_decimal: $('#permite_movimiento_decimal').is(':checked') ? 1 : 0
    };
    if (id) datos.id = id;

    $.ajax({
        url: 'gestionar_unidades_medida_data.php',
        type: 'POST',
        data: datos,
        dataType: 'json',
        success: function(resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message });
                mostrarVista('listado');
                cargarListado();
            } else {
                Swal.fire({ icon: 'error', text: resp ? resp.message : 'Error' });
            }
        },
        error: function(xhr) {
            var msg = 'Error de conexión';
            try {
                var j = JSON.parse(xhr.responseText);
                if (j.message) msg = j.message;
            } catch (err) {}
            Swal.fire({ icon: 'error', text: msg });
        }
    });
});

$(function() {
    mostrarVista('listado');
    cargarListado(1);
    bindCrudPagination('#paginacion-unidades-medida', cargarListado);
});
</script>

<?php require_once "../template/footer.php"; ?>
</body>
</html>
