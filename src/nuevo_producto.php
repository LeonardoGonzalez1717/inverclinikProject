<?php
require_once "../template/header.php";

$sqlProductos = "SELECT id, nombre FROM productos ORDER BY nombre";
$resultProductos = $conn->query($sqlProductos);
$productos = [];
if ($resultProductos) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
}

$sqlInsumos = "SELECT id, nombre, costo_unitario FROM insumos WHERE activo = 1 ORDER BY nombre";
$resultInsumos = $conn->query($sqlInsumos);
$insumos = [];
if ($resultInsumos) {
    while ($row = $resultInsumos->fetch_assoc()) {
        $insumos[] = $row;
    }
}

$sqlRangos = "SELECT id, nombre_rango FROM rangos_tallas ORDER BY nombre_rango";
$resultRangos = $conn->query($sqlRangos);
$rangos = [];
if ($resultRangos) {
    while ($row = $resultRangos->fetch_assoc()) {
        $rangos[] = $row;
    }
}

$sqlTipos = "SELECT id, nombre FROM tipos_produccion ORDER BY nombre";
$resultTipos = $conn->query($sqlTipos);
$tipos = [];
if ($resultTipos) {
    while ($row = $resultTipos->fetch_assoc()) {
        $tipos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Recetas</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Recetas</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nueva Receta</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Recetas</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Producto</th>
                                        <th>Rango de Tallas</th>
                                        <th>Tipo de Producción</th>
                                        <th>Cantidad de Insumos</th>
                                        <th>Costo Total</th>
                                        <th>Precio Total</th>
                                        <th>Observaciones</th>
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
                        <h5 class="subtitle">Crear Nueva Receta</h5>
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Producto</label>
                                <select name="producto_id" id="producto_id" class="form-control" required>
                                    <option value="">-- Seleccione un producto --</option>
                                    <?php foreach ($productos as $p): ?>
                                        <option value="<?php echo htmlspecialchars($p['id']); ?>">
                                            <?php echo htmlspecialchars($p['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Rango de Tallas</label>
                                <select name="rango_tallas_id" id="rango_tallas_id" class="form-control" required>
                                    <option value="">-- Seleccione un rango --</option>
                                    <?php foreach ($rangos as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r['id']); ?>">
                                            <?php echo htmlspecialchars($r['nombre_rango']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tipo de Producción</label>
                                <select name="tipo_produccion_id" id="tipo_produccion_id" class="form-control" required>
                                    <option value="">-- Seleccione un tipo --</option>
                                    <?php foreach ($tipos as $t): ?>
                                        <option value="<?php echo htmlspecialchars($t['id']); ?>">
                                            <?php echo htmlspecialchars($t['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <hr style="margin: 20px 0; border-color: #dee2e6;">

                            <div class="mb-3">
                                <h5 style="color: #0056b3; margin-bottom: 15px;">Insumos de la Receta</h5>
                                
                                <div class="card" style="padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">
                                    <div class="row">
                                        <div class="col-md-5 mb-2">
                                            <label class="form-label">Insumo</label>
                                            <select id="nuevo-insumo-id" class="form-control">
                                                <option value="">-- Seleccione un insumo --</option>
                                                <?php foreach ($insumos as $i): ?>
                                                    <option value="<?php echo htmlspecialchars($i['id']); ?>" 
                                                            data-costo="<?php echo htmlspecialchars($i['costo_unitario']); ?>"
                                                            data-nombre="<?php echo htmlspecialchars($i['nombre']); ?>">
                                                        <?php echo htmlspecialchars($i['nombre'] . ' ($' . number_format($i['costo_unitario'], 2) . ')'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Cantidad por Unidad</label>
                                            <input type="number" step="0.0001" min="0.0001" id="nuevo-cantidad" class="form-control" placeholder="Ej: 1.1">
                                        </div>
                                        <div class="col-md-3 mb-2">
                                            <label class="form-label">Costo Calculado</label>
                                            <input type="text" id="nuevo-costo-calculado" class="form-control" readonly placeholder="$0.00">
                                        </div>
                                        <div class="col-md-1 mb-2 d-flex align-items-end" style="position: relative; top: 22px;">
                                            <button type="button" class="btn btn-success" id="btn-agregar-insumo" style="width: fit-content; margin-bottom: 0;">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div id="lista-insumos" style="margin-top: 15px;">
                                    <table class="table table-bordered" id="tabla-insumos" style="display: none;">
                                        <thead style="background-color: #0056b3; color: white;">
                                            <tr>
                                                <th style="color: black;">Insumo</th>
                                                <th style="color: black;">Cantidad</th>
                                                <th style="color: black;">Costo Unitario</th>
                                                <th style="color: black;">Costo Total</th>
                                                <th style="color: black;">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-insumos">
                                        </tbody>
                                        <tfoot>
                                            <tr style="background-color: #f2f7ff; font-weight: bold;">
                                                <td colspan="3" style="text-align: right;">Costo Total de la Receta:</td>
                                                <td id="costo-total-receta" style="color: #0056b3; font-size: 16px;">$0.00</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <div id="mensaje-sin-insumos" class="alert alert-info" style="text-align: center;">
                                        <i class="fa fa-info-circle"></i> Agrega al menos un insumo para continuar
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Precio Total del Producto ($) <span style="color: red;">*</span></label>
                                <input type="number" step="0.01" min="0" name="precio_total" id="precio_total" class="form-control" placeholder="0.00" required>
                                <small class="form-text text-muted">Ingrese el precio de venta total del producto terminado</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observaciones Generales</label>
                                <textarea name="observaciones" id="observaciones" class="form-control" rows="3"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary" id="btn-guardar-receta" disabled>Guardar Receta Completa</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-receta-id" name="id" value="">
                            <input type="hidden" id="action" value="">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
var insumosAgregados = [];

function calcularCostoInsumo(insumoId, cantidad) {
    var costoUnitario = parseFloat($('#nuevo-insumo-id option:selected').data('costo')) || 0;
    return cantidad * costoUnitario;
}

function agregarInsumo() {
    var insumoId = $('#nuevo-insumo-id').val();
    var cantidad = parseFloat($('#nuevo-cantidad').val()) || 0;
    
    if (!insumoId || cantidad <= 0) {
        alert('Por favor selecciona un insumo e ingresa una cantidad válida');
        return;
    }
    
    if (insumosAgregados.some(i => i.insumo_id == insumoId)) {
        alert('Este insumo ya fue agregado a la receta');
        return;
    }
    
    var insumoNombre = $('#nuevo-insumo-id option:selected').data('nombre');
    var costoUnitario = parseFloat($('#nuevo-insumo-id option:selected').data('costo')) || 0;
    var costoTotal = calcularCostoInsumo(insumoId, cantidad);
    
    var insumo = {
        insumo_id: insumoId,
        insumo_nombre: insumoNombre,
        cantidad_por_unidad: cantidad,
        costo_unitario: costoUnitario,
        costo_total: costoTotal
    };
    
    insumosAgregados.push(insumo);
    actualizarTablaInsumos();
    limpiarFormularioInsumo();
}

function eliminarInsumo(index) {
    insumosAgregados.splice(index, 1);
    actualizarTablaInsumos();
}

function actualizarTablaInsumos() {
    var tbody = $('#tbody-insumos');
    
    var costoTotalReceta = 0;
    var htmlRows = '';
    
    insumosAgregados.forEach(function(insumo, index) {
        costoTotalReceta += insumo.costo_total;
        htmlRows += `
            <tr>
                <td>${insumo.insumo_nombre}</td>
                <td>${insumo.cantidad_por_unidad}</td>
                <td>$${insumo.costo_unitario.toFixed(2)}</td>
                <td>$${insumo.costo_total.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarInsumo(${index})">
                        <i class="fa fa-trash"></i> Eliminar
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.html(htmlRows);
    $('#costo-total-receta').text('$' + costoTotalReceta.toFixed(2));
    
    if (insumosAgregados.length > 0) {
        $('#tabla-insumos').show();
        $('#mensaje-sin-insumos').hide();
        // Validar que también haya precio total del producto
        var precioTotal = parseFloat($('#precio_total').val()) || 0;
        if (precioTotal > 0) {
            $('#btn-guardar-receta').prop('disabled', false);
        } else {
            $('#btn-guardar-receta').prop('disabled', true);
        }
    } else {
        $('#tabla-insumos').hide();
        $('#mensaje-sin-insumos').show();
        $('#btn-guardar-receta').prop('disabled', true);
    }
}

function limpiarFormularioInsumo() {
    $('#nuevo-insumo-id').val('');
    $('#nuevo-cantidad').val('');
    $('#nuevo-costo-calculado').val('');
}

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
    $.post('nuevo_producto_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    insumosAgregados = [];
    actualizarTablaInsumos();
    limpiarFormularioInsumo();
    $('#editar-receta-id').val('');
    $('#precio_total').val('');
}

$(document).ready(function() {
    $('#nuevo-insumo-id, #nuevo-cantidad').on('change input', function() {
        var insumoId = $('#nuevo-insumo-id').val();
        var cantidad = parseFloat($('#nuevo-cantidad').val()) || 0;
        
        if (insumoId && cantidad > 0) {
            var costo = calcularCostoInsumo(insumoId, cantidad);
            $('#nuevo-costo-calculado').val('$' + costo.toFixed(2));
        } else {
            $('#nuevo-costo-calculado').val('');
        }
    });
    
    $('#btn-agregar-insumo').on('click', function() {
        agregarInsumo();
    });
    
    $('#nuevo-cantidad').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            agregarInsumo();
        }
    });
    
    // Validar precio total del producto para habilitar botón guardar
    $('#precio_total').on('input', function() {
        var precioTotal = parseFloat($(this).val()) || 0;
        if (insumosAgregados.length > 0 && precioTotal > 0) {
            $('#btn-guardar-receta').prop('disabled', false);
        } else {
            $('#btn-guardar-receta').prop('disabled', true);
        }
    });
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();
    
    if (insumosAgregados.length === 0) {
        alert('Debes agregar al menos un insumo a la receta');
        return;
    }
    
    var producto_id = $("#producto_id").val();
    var rango_tallas_id = $("#rango_tallas_id").val();
    var tipo_produccion_id = $("#tipo_produccion_id").val();
    var precio_total = parseFloat($("#precio_total").val()) || 0;
    var observaciones = $("#observaciones").val() || "";
    
    if (!producto_id || !rango_tallas_id || !tipo_produccion_id) {
        alert('Por favor completa todos los campos requeridos');
        return;
    }
    
    if (precio_total <= 0) {
        alert('Por favor ingresa un precio total del producto válido');
        return;
    }
    
    var datos = {
        action: "crear_receta_completa",
        producto_id: producto_id,
        rango_tallas_id: rango_tallas_id,
        tipo_produccion_id: tipo_produccion_id,
        precio_total: precio_total,
        insumos: JSON.stringify(insumosAgregados),  // Convertir a JSON string
        observaciones: observaciones
    };
    
    $.ajax({
        url: "nuevo_producto_data.php",
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

document.addEventListener('DOMContentLoaded', function() {
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