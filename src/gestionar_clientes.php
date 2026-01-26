<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Clientes</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Cliente</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Clientes</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Tipo Documento</th>
                                        <th>Número Documento</th>
                                        <th>Teléfono</th>
                                        <th>Email</th>
                                        <th>Dirección</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Crear/Editar Cliente</h5>
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Cliente <span style="color: red;">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" required 
                                       maxlength="100" placeholder="Ej: Juan Pérez">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo de Documento</label>
                                <select name="tipo_documento" id="tipo_documento" class="form-control">
                                    <option value="">-- Seleccione --</option>
                                    <option value="V">V - Venezolano</option>
                                    <option value="E">E - Extranjero</option>
                                    <option value="J">J - Jurídico</option>
                                    <option value="G">G - Gobierno</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Número de Documento</label>
                                <input type="text" name="numero_documento" id="numero_documento" class="form-control" 
                                       maxlength="30" placeholder="Ej: 12345678">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="telefono" id="telefono" class="form-control" 
                                       maxlength="20" placeholder="Ej: 0412-1234567">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" id="email" class="form-control" 
                                       maxlength="100" placeholder="Ej: cliente@email.com">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea name="direccion" id="direccion" class="form-control" rows="3" 
                                          placeholder="Dirección completa del cliente..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Cliente</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-cliente-id" name="id" value="">
                            <input type="hidden" id="action" value="">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
function mostrarVista(vista) {
    document.querySelectorAll('#contenedor-vistas > div').forEach(el => {
        el.classList.add('hidden');
    });
    const vistaElement = document.getElementById('vista-' + vista);
    if (vistaElement) {
        vistaElement.classList.remove('hidden');
    }
}

function cargarListado() {
    $.post('gestionar_clientes_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#nombre').val('');
    $('#tipo_documento').val('');
    $('#numero_documento').val('');
    $('#telefono').val('');
    $('#email').val('');
    $('#direccion').val('');
    $('#editar-cliente-id').val('');
}

function editarCliente(data) {
    $('#nombre').val(data.nombre || '');
    $('#tipo_documento').val(data.tipo_documento || '');
    $('#numero_documento').val(data.numero_documento || '');
    $('#telefono').val(data.telefono || '');
    $('#email').val(data.email || '');
    $('#direccion').val(data.direccion || '');
    $('#editar-cliente-id').val(data.id);
    mostrarVista('crear');
}

function eliminarCliente(id) {
    if (!confirm('¿Está seguro de eliminar este cliente?')) {
        return;
    }

    $.ajax({
        url: "gestionar_clientes_data.php",
        type: "POST",
        data: {
            action: 'eliminar',
            id: id
        },
        success: function(resp) {
            if (resp && resp.success) {
                alert(resp.message);
                cargarListado();
            } else {
                alert("Error: " + (resp ? resp.message : "Respuesta inválida"));
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            var resp = JSON.parse(xhr.responseText);
            alert("Error: " + (resp ? resp.message : "Error al eliminar cliente"));
        }
    });
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado();
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    var idCliente = $("#editar-cliente-id").val();

    var datos = {
        action: idCliente ? "editar" : "crear",
        id: idCliente || null,
        nombre: $("#nombre").val(),
        tipo_documento: $("#tipo_documento").val() || "",
        numero_documento: $("#numero_documento").val() || "",
        telefono: $("#telefono").val() || "",
        email: $("#email").val() || "",
        direccion: $("#direccion").val() || ""
    };

    if (!datos.nombre || datos.nombre.trim() === '') {
        alert('El nombre del cliente es obligatorio');
        return;
    }

    $.ajax({
        url: "gestionar_clientes_data.php",
        type: "POST",
        data: datos,
        success: function(resp) {
            if (resp && resp.success) {
                alert(resp.message);
                mostrarVista("listado");
                cargarListado();
            } else {
                alert("Error: " + (resp ? resp.message : "Respuesta inválida"));
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            alert("Error de conexión.");
        }
    });
});
</script>

<?php
$conn->close();
require_once "../template/footer.php";
?>
</body>
</html>

