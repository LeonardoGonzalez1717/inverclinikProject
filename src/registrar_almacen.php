<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Almacén</title>
</head>
<body>
<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h2 class="main-title">Almacenes</h2>

            <div class="row mb-3">
                <div class="col-md-12">
                    <button type="button" class="btn btn-success" onclick="mostrarVista('crear'); limpiarFormulario();">
                        Registrar nuevo almacén
                    </button>
                </div>
            </div>

            <div id="contenedor-vistas">
                <div id="vista-listado">
                    <h5 class="subtitle">Lista de almacenes</h5>
                    <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Código</th>
                                    <!-- <th>Activo</th> -->
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="vista-crear" class="hidden">
                    <h5 class="subtitle" id="subtitle-form-almacen">Registrar nuevo almacén</h5>
                    <form id="form-almacen">
                        <input type="hidden" id="editar-almacen-id" value="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre <span style="color:red">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Código</label>
                                <input type="text" name="codigo" id="codigo" class="form-control" maxlength="20" placeholder="Opcional">
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <select name="activo" id="activo" class="form-control">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Guardar</button>
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
function mostrarVista(vista) {
    document.querySelectorAll('#contenedor-vistas > div').forEach(function (el) { el.classList.add('hidden'); });
    document.getElementById('vista-' + vista).classList.remove('hidden');
}

function cargarListado() {
    $.post('registrar_almacen_data.php', { action: 'listar_html' }, function (html) {
        $('#vista-listado tbody').html(html);
    });
}

function limpiarFormulario() {
    $('#editar-almacen-id').val('');
    $('#subtitle-form-almacen').text('Registrar nuevo almacén');
    $('#form-almacen')[0].reset();
    $('#activo').val('1');
}

$('#form-almacen').on('submit', function (e) {
    e.preventDefault();
    var idEdit = $('#editar-almacen-id').val();
    var activo = parseInt($('#activo').val(), 10) === 1 ? 1 : 0;
    var datos = idEdit
        ? {
            action: 'editar',
            id: parseInt(idEdit, 10),
            nombre: $('#nombre').val().trim(),
            codigo: $('#codigo').val().trim(),
            activo: activo
        }
        : {
            action: 'crear',
            nombre: $('#nombre').val().trim(),
            codigo: $('#codigo').val().trim(),
            activo: activo
        };
    $.ajax({
        url: 'registrar_almacen_data.php',
        type: 'POST',
        data: JSON.stringify(datos),
        contentType: 'application/json',
        dataType: 'json',
        success: function (resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message });
                limpiarFormulario();
                mostrarVista('listado');
                cargarListado();
            } else {
                Swal.fire({ icon: 'error', text: 'Error: ' + (resp ? resp.message : 'Error desconocido') });
            }
        },
        error: function (xhr) {
            try {
                var r = JSON.parse(xhr.responseText);
                Swal.fire({ icon: 'error', text: 'Error: ' + (r.message || xhr.responseText) });
            } catch (e) {
                Swal.fire({ icon: 'error', text: 'Error de conexión.' });
            }
        }
    });
});

function editarAlmacen(id, nombre, codigo, activo) {
    $('#editar-almacen-id').val(String(id));
    $('#nombre').val(nombre || '');
    $('#codigo').val(codigo != null ? String(codigo) : '');
    var esActivo = activo === 1 || activo === true || activo === '1';
    $('#activo').val(esActivo ? '1' : '0');
    $('#subtitle-form-almacen').text('Editar almacén');
    mostrarVista('crear');
}

function eliminarAlmacen(id) {
    Swal.fire({
        icon: 'question',
        text: '¿Desea eliminar este almacén?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function (r) {
        if (!r.isConfirmed) return;
        $.ajax({
            url: 'registrar_almacen_data.php',
            type: 'POST',
            data: JSON.stringify({ action: 'eliminar', id: id }),
            contentType: 'application/json',
            dataType: 'json',
            success: function (resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    cargarListado();
                } else {
                    Swal.fire({ icon: 'error', text: 'Error: ' + (resp ? resp.message : 'Error desconocido') });
                }
            },
            error: function (xhr) {
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

$(document).ready(function () {
    mostrarVista('listado');
    cargarListado();
});
</script>

<?php
$conn->close();
require_once "../template/footer.php";
?>
</body>
</html>
