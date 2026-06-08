<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

$categorias = [];
$resCat = $conn->query("SELECT id, nombre FROM categorias ORDER BY nombre ASC");
if ($resCat) {
    while ($row = $resCat->fetch_assoc()) {
        $categorias[] = $row;
    }
}

$rangos = [];
$resRangos = $conn->query("SELECT id, nombre_rango FROM rangos_tallas ORDER BY nombre_rango ASC");
if ($resRangos) {
    while ($row = $resRangos->fetch_assoc()) {
        $rangos[] = $row;
    }
}

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
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Categoría</th>
                                        <th>Tipo/Género</th>
                                        <th>Rango de Tallas</th>
                                        <th>Descripción</th>
                                        <th>Fecha Creación</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                        <div id="paginacion-productos"></div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <form id="form-crear">
                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label" for="nombre">Nombre del Producto <span style="color: red;">*</span></label>
                                    <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="255">
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label" for="categoria">Categoría</label>
                                    <select name="categoria" id="categoria" class="form-control">
                                        <option value=""></option>
                                        <?php foreach ($categorias as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <?php echo htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label" for="tipo_genero">Tipo/Género</label>
                                    <select name="tipo_genero" id="tipo_genero" class="form-control">
                                        <option value="">-- Seleccione un tipo --</option>
                                        <option value="Caballero">Caballero</option>
                                        <option value="Dama">Dama</option>
                                        <option value="Niño">Niño</option>
                                        <option value="Niña">Niña</option>
                                        <option value="Unisex">Unisex</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label" for="rango_tallas_id">Rango de Tallas <span style="color: red;">*</span></label>
                                    <select name="rango_tallas_id" id="rango_tallas_id" class="form-control" required>
                                        <option value="">Seleccione un rango de tallas</option>
                                        <?php foreach ($rangos as $rango): ?>
                                            <option value="<?php echo (int) $rango['id']; ?>">
                                                <?php echo htmlspecialchars($rango['nombre_rango'], ENT_QUOTES, 'UTF-8'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">
                                        Defina el rango en <a href="registrar_rangos_tallas.php">Rangos de Tallas</a> si no aparece en la lista.
                                    </small>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-3" style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                    <input type="checkbox" id="activo" name="activo" value="1" checked style="width: 20px; height: 20px;">
                                    <label for="activo" style="cursor: pointer; font-weight: bold;">
                                        Publicar en el catálogo
                                    </label>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label" for="descripcion">Descripción</label>
                                    <textarea name="descripcion" id="descripcion" class="form-control" rows="4"
                                              placeholder="Descripción detallada del producto..."></textarea>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label" for="imagen">Imagen del Producto</label>
                                    <input type="file" name="imagen" id="imagen" class="form-control" accept="image/*">
                                    <small class="form-text text-muted">Formatos permitidos: JPG, PNG, GIF. Tamaño máximo: 5MB</small>
                                    <div id="imagen-preview" style="margin-top: 10px;"></div>
                                    <input type="hidden" id="imagen-actual" name="imagen_actual" value="">
                                </div>
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

function cargarListado(page) {
    crudPostListadoPaginado(
        'gestionar_productos_data.php',
        { action: 'listar_html' },
        '#vista-listado tbody',
        '#paginacion-productos',
        page || 1
    );
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#categoria').find('option[data-legacy]').remove();
    $('#nombre').val('');
    $('#categoria').val('');
    $('#tipo_genero').val('');
    $('#rango_tallas_id').val('');
    $('#descripcion').val('');
    $('#editar-producto-id').val('');
    $('#imagen-actual').val('');
    $('#activo').val('');
    $('#imagen-preview').html('');
}

function editarProducto(data) {
    $('#nombre').val(data.nombre || '');
    var catVal = data.categoria || '';
    $('#categoria').find('option[data-legacy]').remove();
    var catExists = false;
    $('#categoria option').each(function() {
        if ($(this).val() === catVal) { catExists = true; return false; }
    });
    if (catVal && !catExists) {
        $('#categoria').append(
            $('<option></option>').attr('value', catVal).attr('data-legacy', '1').text(catVal + ' (valor actual)')
        );
    }
    $('#categoria').val(catVal);
    $('#tipo_genero').val(data.tipo_genero || '');
    $('#rango_tallas_id').val(data.rango_tallas_id || '');
    $('#descripcion').val(data.descripcion || '');
    $('#activo').val(data.activo || '');
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
    cargarListado(1);
    bindCrudPagination('#paginacion-productos', cargarListado);
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
    formData.append('rango_tallas_id', $("#rango_tallas_id").val() || "");
    formData.append('descripcion', $("#descripcion").val() || "");
    formData.append('activo', $("#activo").val() || "");
    formData.append('imagen_actual', $("#imagen-actual").val() || "");
    
    var imagenFile = $("#imagen")[0].files[0];
    if (imagenFile) {
        formData.append('imagen', imagenFile);
    }

    if (!$("#nombre").val() || $("#nombre").val().trim() === '') {
        Swal.fire({ icon: 'warning', text: 'El nombre del producto es obligatorio' });
        return;
    }

    if (!$("#rango_tallas_id").val()) {
        Swal.fire({ icon: 'warning', text: 'Debe seleccionar un rango de tallas para el producto.' });
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
                Swal.fire({ icon: 'success', text: resp.message });
                mostrarVista("listado");
                cargarListado();
                limpiarFormulario();
            } else {
                Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Respuesta inválida") });
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            var resp = JSON.parse(xhr.responseText);
            Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Error de conexión.") });
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
