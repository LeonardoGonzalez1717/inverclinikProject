<?php
require_once "../template/header.php";

$sqlProveedores = "SELECT id, nombre FROM proveedores ORDER BY nombre";
$resultProveedores = $conn->query($sqlProveedores);
$proveedores = [];
if ($resultProveedores) {
    while ($row = $resultProveedores->fetch_assoc()) {
        $proveedores[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Insumos</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Insumos</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Insumo</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Insumos</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nombre</th>
                                        <th>Unidad de Medida</th>
                                        <th>Costo Unitario</th>
                                        <th>Proveedor</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Crear/Editar Insumo</h5>
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Insumo <span style="color: red;">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" required 
                                       maxlength="255" placeholder="Ej: Tela jean rígido 14oz">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Unidad de Medida <span style="color: red;">*</span></label>
                                <select name="unidad_medida" id="unidad_medida" class="form-control" required>
                                    <option value="">-- Seleccione una unidad --</option>
                                    <option value="metro">Metro</option>
                                    <option value="unidad">Unidad</option>
                                    <option value="kilogramo">Kilogramo</option>
                                    <option value="litro">Litro</option>
                                    <option value="metro_cuadrado">Metro Cuadrado</option>
                                    <option value="carrete">Carrete</option>
                                    <option value="rollo">Rollo</option>
                                    <option value="pieza">Pieza</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Costo Unitario ($) <span style="color: red;">*</span></label>
                                <input type="number" step="0.01" min="0" name="costo_unitario" id="costo_unitario" 
                                       class="form-control" required placeholder="Ej: 5.00">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Proveedor</label>
                                <select name="proveedor_id" id="proveedor_id" class="form-control">
                                    <option value="">-- Seleccione un proveedor (opcional) --</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?php echo htmlspecialchars($prov['id']); ?>">
                                            <?php echo htmlspecialchars($prov['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Insumo</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-insumo-id" name="id" value="">
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
    $.post('gestionar_insumos_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#nombre').val('');
    $('#unidad_medida').val('');
    $('#costo_unitario').val('');
    $('#proveedor_id').val('');
    $('#editar-insumo-id').val('');
}

function editarInsumo(data) {
    $('#nombre').val(data.nombre || '');
    $('#unidad_medida').val(data.unidad_medida || '');
    $('#costo_unitario').val(data.costo_unitario || '');
    $('#proveedor_id').val(data.proveedor_id || '');
    $('#editar-insumo-id').val(data.id);
    mostrarVista('crear');
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado();
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    var idInsumo = $("#editar-insumo-id").val();

    var datos = {
        action: idInsumo ? "editar" : "crear",
        id: idInsumo || null,
        nombre: $("#nombre").val(),
        unidad_medida: $("#unidad_medida").val(),
        costo_unitario: $("#costo_unitario").val(),
        proveedor_id: $("#proveedor_id").val() || null
    };

    if (!datos.nombre || datos.nombre.trim() === '') {
        alert('El nombre del insumo es obligatorio');
        return;
    }

    if (!datos.unidad_medida || datos.unidad_medida.trim() === '') {
        alert('La unidad de medida es obligatoria');
        return;
    }

    if (!datos.costo_unitario || parseFloat(datos.costo_unitario) < 0) {
        alert('El costo unitario debe ser mayor o igual a 0');
        return;
    }

    $.ajax({
        url: "gestionar_insumos_data.php",
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
