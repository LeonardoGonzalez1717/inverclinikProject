<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Productos</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Productos</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Producto</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Productos</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Categoría</th>
                                        <th>Tipo/Género</th>
                                        <th>Descripción</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Crear/Editar Producto</h5>
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Producto <span style="color: red;">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" required 
                                       maxlength="255" placeholder="Ej: Jean Triple Costura de Caballero">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Categoría</label>
                                <input type="text" name="categoria" id="categoria" class="form-control" 
                                       maxlength="100" placeholder="Ej: Pantalón, Camisa, Chaqueta">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo/Género</label>
                                <select name="tipo_genero" id="tipo_genero" class="form-control">
                                    <option value="">-- Seleccione un tipo --</option>
                                    <option value="Caballero">Caballero</option>
                                    <option value="Dama">Dama</option>
                                    <option value="Niño">Niño</option>
                                    <option value="Niña">Niña</option>
                                    <option value="Unisex">Unisex</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Descripción</label>
                                <textarea name="descripcion" id="descripcion" class="form-control" rows="4" 
                                          placeholder="Descripción detallada del producto..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Imagen del Producto</label>
                                <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*">
                                <small class="form-text text-muted">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB</small>
                                <div id="imagen-preview" style="margin-top: 10px;"></div>
                                <input type="hidden" id="imagen-actual" name="imagen_actual" value="">
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Producto</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-producto-id" name="id" value="">
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
    $.post('gestionar_productos_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#nombre').val('');
    $('#categoria').val('');
    $('#tipo_genero').val('');
    $('#descripcion').val('');
    $('#editar-producto-id').val('');
    $('#imagen-actual').val('');
    $('#imagen-preview').html('');
}

function editarProducto(data) {
    $('#nombre').val(data.nombre || '');
    $('#categoria').val(data.categoria || '');
    $('#tipo_genero').val(data.tipo_genero || '');
    $('#descripcion').val(data.descripcion || '');
    $('#editar-producto-id').val(data.id);
    $('#imagen-actual').val(data.imagen || '');
    
    // Mostrar imagen actual si existe
    if (data.imagen) {
        $('#imagen-preview').html('<img src="../assets/img/productos/' + data.imagen + '" style="max-width: 200px; max-height: 200px; border-radius: 6px; margin-top: 10px;"><br><small>Imagen actual</small>');
    } else {
        $('#imagen-preview').html('');
    }
    
    mostrarVista('crear');
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado();
});

// Preview de imagen
$("#imagen").on("change", function(e) {
    var file = e.target.files[0];
    if (file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            $("#imagen-preview").html('<img src="' + e.target.result + '" style="max-width: 200px; max-height: 200px; border-radius: 6px; margin-top: 10px;">');
        };
        reader.readAsDataURL(file);
    }
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    var idProducto = $("#editar-producto-id").val();
    var formData = new FormData();
    
    formData.append('action', idProducto ? "editar" : "crear");
    if (idProducto) formData.append('id', idProducto);
    formData.append('nombre', $("#nombre").val());
    formData.append('categoria', $("#categoria").val() || "");
    formData.append('tipo_genero', $("#tipo_genero").val() || "");
    formData.append('descripcion', $("#descripcion").val() || "");
    formData.append('imagen_actual', $("#imagen-actual").val() || "");
    
    var imagenFile = $("#imagen")[0].files[0];
    if (imagenFile) {
        formData.append('imagen', imagenFile);
    }

    if (!$("#nombre").val() || $("#nombre").val().trim() === '') {
        alert('El nombre del producto es obligatorio');
        return;
    }

    $.ajax({
        url: "gestionar_productos_data.php",
        type: "POST",
        data: formData,
        processData: false,
        contentType: false,
        success: function(resp) {
            if (resp && resp.success) {
                alert(resp.message);
                mostrarVista("listado");
                cargarListado();
                limpiarFormulario();
            } else {
                alert("Error: " + (resp ? resp.message : "Respuesta inválida"));
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            var resp = JSON.parse(xhr.responseText);
            alert("Error: " + (resp ? resp.message : "Error de conexión."));
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
