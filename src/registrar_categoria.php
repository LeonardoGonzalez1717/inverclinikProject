<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Categoría</title>
</head>
<body>
<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h2 class="main-title">Registrar Categoría</h2>

            <div class="row mb-3">
                <div class="col-md-12">
                    <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">
                        Registrar Nueva Categoría
                    </button>
                </div>
            </div>

            <div id="contenedor-vistas">
                <div id="vista-listado">
                    <h5 class="subtitle">Lista de Categorías</h5>
                    <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="vista-crear" class="hidden">
                    <h5 class="subtitle" id="subtitle-form-categoria">Registrar Nueva Categoría</h5>
                    <form id="form-categoria">
                        <input type="hidden" id="editar-categoria-id" value="">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre <span style="color:red">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Descripción</label>
                                <input type="text" name="descripcion" id="descripcion" class="form-control">
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Guardar Categoría</button>
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
    document.querySelectorAll('#contenedor-vistas > div').forEach(el => el.classList.add('hidden'));
    document.getElementById('vista-' + vista).classList.remove('hidden');
}

function cargarListado() {
    $.post('registrar_categoria_data.php', {action: 'listar_html'}, function(html){
        $('#vista-listado tbody').html(html);
    });
}

function limpiarFormulario() {
    $('#editar-categoria-id').val('');
    $('#subtitle-form-categoria').text('Registrar Nueva Categoría');
    $('#form-categoria')[0].reset();
}

$("#form-categoria").on("submit", function(e) {
    e.preventDefault();
    var idEdit = $('#editar-categoria-id').val();
    var nombre = $("#nombre").val().trim();
    var descripcion = $("#descripcion").val().trim();
    var datos = idEdit
        ? { action: 'editar', id: parseInt(idEdit, 10), nombre: nombre, descripcion: descripcion }
        : { action: 'crear', nombre: nombre, descripcion: descripcion };
    $.ajax({
        url: "registrar_categoria_data.php",
        type: "POST",
        data: JSON.stringify(datos),
        contentType: "application/json",
        dataType: "json", // <-- asegura interpretar JSON
        success: function(resp) {
            if(resp && resp.success){
                Swal.fire({ icon: 'success', text: resp.message });
                limpiarFormulario();
                mostrarVista('listado');
                cargarListado();
            } else {
                Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Error desconocido") });
            }
        },
        error: function(xhr) {
            Swal.fire({ icon: 'error', text: "Error: " + xhr.responseText });
        }
    });
});

function editarCategoria(id, nombre, descripcion) {
    $('#editar-categoria-id').val(String(id));
    $('#nombre').val(nombre != null ? String(nombre) : '');
    $('#descripcion').val(descripcion != null ? String(descripcion) : '');
    $('#subtitle-form-categoria').text('Editar categoría');
    mostrarVista('crear');
}

function eliminarCategoria(id){
    Swal.fire({
        icon: 'question',
        text: '¿Desea eliminar esta categoría?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;

        $.ajax({
            url: "registrar_categoria_data.php",
            type: "POST",
            data: JSON.stringify({action: 'eliminar', id: id}),
            contentType: "application/json",
            dataType: "json",
            success: function(resp){
                if(resp.success){
                    Swal.fire({ icon: 'success', text: resp.message });
                    cargarListado();
                } else {
                    Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Error desconocido") });
                }
            }
        });
    });
}

$(document).ready(function(){
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