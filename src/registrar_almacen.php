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
                    <h5 class="subtitle">Registrar nuevo almacén</h5>
                    <form id="form-almacen">
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
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Guardar</button>
                                <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
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
    $('#form-almacen')[0].reset();
}

$('#form-almacen').on('submit', function (e) {
    e.preventDefault();
    var datos = {
        action: 'crear',
        nombre: $('#nombre').val().trim(),
        codigo: $('#codigo').val().trim(),
        activo: 1
    };
    $.ajax({
        url: 'registrar_almacen_data.php',
        type: 'POST',
        data: JSON.stringify(datos),
        contentType: 'application/json',
        dataType: 'json',
        success: function (resp) {
            if (resp && resp.success) {
                alert(resp.message);
                mostrarVista('listado');
                cargarListado();
            } else {
                alert('Error: ' + (resp ? resp.message : 'Error desconocido'));
            }
        },
        error: function (xhr) {
            try {
                var r = JSON.parse(xhr.responseText);
                alert('Error: ' + (r.message || xhr.responseText));
            } catch (e) {
                alert('Error de conexión.');
            }
        }
    });
});

function editarAlmacen(id, nombre, codigo, activo) {
    var n = window.prompt('Nombre del almacén:', nombre || '');
    if (n === null) return;
    n = n.trim();
    if (n === '') {
        alert('El nombre es obligatorio.');
        return;
    }
    var c = window.prompt('Código (opcional):', codigo != null ? String(codigo) : '');
    if (c === null) return;
    var actStr = window.prompt('¿Activo? Escriba 1 (sí) o 0 (no):', activo ? '1' : '0');
    if (actStr === null) return;
    actStr = actStr.trim();
    var act = (actStr === '1') ? 1 : 0;

    $.ajax({
        url: 'registrar_almacen_data.php',
        type: 'POST',
        data: JSON.stringify({
            action: 'editar',
            id: id,
            nombre: n,
            codigo: c.trim(),
            activo: act
        }),
        contentType: 'application/json',
        dataType: 'json',
        success: function (resp) {
            if (resp && resp.success) {
                alert(resp.message);
                cargarListado();
            } else {
                alert('Error: ' + (resp ? resp.message : 'Error desconocido'));
            }
        },
        error: function (xhr) {
            try {
                var r = JSON.parse(xhr.responseText);
                alert('Error: ' + (r.message || xhr.responseText));
            } catch (e) {
                alert('Error de conexión.');
            }
        }
    });
}

function eliminarAlmacen(id) {
    if (!window.confirm('¿Desea eliminar este almacén?')) return;
    $.ajax({
        url: 'registrar_almacen_data.php',
        type: 'POST',
        data: JSON.stringify({ action: 'eliminar', id: id }),
        contentType: 'application/json',
        dataType: 'json',
        success: function (resp) {
            if (resp && resp.success) {
                alert(resp.message);
                cargarListado();
            } else {
                alert('Error: ' + (resp ? resp.message : 'Error desconocido'));
            }
        },
        error: function (xhr) {
            try {
                var r = JSON.parse(xhr.responseText);
                alert('Error: ' + (r.message || xhr.responseText));
            } catch (e) {
                alert('Error de conexión.');
            }
        }
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
