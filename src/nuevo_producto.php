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

$sqlAlmacenes = "SELECT id, nombre FROM almacenes WHERE activo = 1 ORDER BY nombre";
$resultAlmacenes = $conn->query($sqlAlmacenes);
$almacenes = [];
if ($resultAlmacenes) {
    while ($row = $resultAlmacenes->fetch_assoc()) {
        $almacenes[] = $row;
    }
}
// Si no existe la tabla almacenes o está vacía, permitir continuar sin select
if (empty($almacenes)) {
    $conn->query("CREATE TABLE IF NOT EXISTS almacenes (id int(11) NOT NULL AUTO_INCREMENT, nombre varchar(100) NOT NULL, codigo varchar(20) DEFAULT NULL, activo tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $conn->query("INSERT IGNORE INTO almacenes (nombre, codigo, activo) VALUES ('Principal', 'ALM01', 1)");
    $resultAlmacenes = $conn->query($sqlAlmacenes);
    if ($resultAlmacenes) {
        while ($row = $resultAlmacenes->fetch_assoc()) {
            $almacenes[] = $row;
        }
    }
}

$tasa_actual = null;
$rt = $conn->query("SELECT tasa FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
if ($rt && $row_tasa = $rt->fetch_assoc()) {
    $tasa_actual = (float) $row_tasa['tasa'];
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
                                        <th>#</th>
                                        <th>Producto</th>
                                        <th>Rango de Tallas</th>
                                        <th>Tipo de Producción</th>
                                        <th>Almacén</th>
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
                        <input type="text" class="hidden" id="precio_total" name="precio_total" value="">
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
                            <div class="mb-3">
                                <label class="form-label">Almacén</label>
                                <select name="almacen_id" id="almacen_id" class="form-control">
                                    <option value="">-- Seleccione un almacén --</option>
                                    <?php foreach ($almacenes as $a): ?>
                                        <option value="<?php echo htmlspecialchars($a['id']); ?>">
                                            <?php echo htmlspecialchars($a['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Almacén al que pertenece esta receta en el inventario</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stock mínimo (producto terminado)</label>
                                <input type="number" step="0.01" min="0" name="stock_minimo" id="stock_minimo" class="form-control" placeholder="0">
                                <small class="form-text text-muted">Límite mínimo en inventario</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stock máximo (producto terminado)</label>
                                <input type="number" step="0.01" min="0" name="stock_maximo" id="stock_maximo" class="form-control" placeholder="0">
                                <small class="form-text text-muted">Límite máximo en inventario</small>

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
                                            <input type="number" step="1" min="1" id="nuevo-cantidad" class="form-control" placeholder="Ej: 1.1">
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
                                                <th style="color: black;">Equiv. Bs.</th>
                                                <th style="color: black;">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-insumos">
                                        </tbody>
                                        <tfoot>
                                            <tr style="background-color: #f2f7ff; font-weight: bold;">
                                                <td colspan="3" style="text-align: right;">Costo Total de la Receta:</td>
                                                <td id="costo-total-receta" style="color: #0056b3; font-size: 16px;">$0.00</td>
                                                <td id="costo-total-receta-bs" style="color: #0056b3; font-size: 16px;">—</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <div id="mensaje-sin-insumos" class="alert alert-info" style="text-align: center;">
                                        <i class="fa fa-info-circle"></i> Agrega al menos un insumo para continuar
                                    </div>
                                </div>
                            </div>

                            <hr style="margin: 20px 0; border-color: #dee2e6;">

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" style="font-weight: bold;">Porcentaje de Ganancia</label>
                                    <div class="input-group">
                                        <input type="number" id="porcentaje_ganancia" class="form-control" placeholder="Ej: 30">
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" style="font-weight: bold;">Porcentaje de Descuento</label>
                                    <div class="input-group">
                                        <input type="number" id="porcentaje_descuento" class="form-control" placeholder="Ej: 10">
                                        <small class="form-text text-muted">Valor tomado en cuenta para el calculo del precio al mayor</small>
                                    </div>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Precio Final Detal ($)</label>
                                    <input type="number" step="0.01" name="precio_detal" id="precio_detal" class="form-control" style="background-color: #e9ecef; font-weight: bold;" readonly>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Precio Final Mayor ($)</label>
                                    <input type="number" step="0.01" name="precio_mayor" id="precio_mayor" class="form-control" style="background-color: #e9ecef; font-weight: bold;" readonly>
                                </div>
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
var tasaCambiariaActual = <?php echo $tasa_actual !== null ? json_encode($tasa_actual) : 'null'; ?>;
var tasaParaEquivalenteReceta = tasaCambiariaActual;

function formatearBs(valor) {
    if (valor == null || isNaN(valor)) return '—';
    return 'Bs. ' + parseFloat(valor).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function calcularCostoInsumo(insumoId, cantidad) {
    var costoUnitario = parseFloat($('#nuevo-insumo-id option:selected').data('costo')) || 0;
    return cantidad * costoUnitario;
}

let costoTotalInsumos = 0;

function calcularPreciosFinales() {
    const margenGanancia = parseFloat($('#porcentaje_ganancia').val()) || 0;
    const descuentoMayor = parseFloat($('#porcentaje_descuento').val()) || 0;

    if (costoTotalInsumos > 0) {
        const precioDetal = costoTotalInsumos * (1 + (margenGanancia / 100));
        
        const precioMayor = precioDetal * (1 - (descuentoMayor / 100));

        $('#precio_detal').val(precioDetal.toFixed(2));
        $('#precio_mayor').val(precioMayor.toFixed(2));
    } else {
        $('#precio_detal').val('0.00');
        $('#precio_mayor').val('0.00');
    }
}

$('#porcentaje_ganancia, #porcentaje_descuento').on('input', function() {
    calcularPreciosFinales();
});

function actualizarCostoTotalReceta(nuevoCosto) {
    costoTotalInsumos = nuevoCosto;
    $('#costo-total-receta').text('$' + nuevoCosto.toFixed(2));
    calcularPreciosFinales();
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
    var costoTotalRecetaBs = 0;
    var htmlRows = '';
    
    var tasa = tasaParaEquivalenteReceta;
    insumosAgregados.forEach(function(insumo, index) {
        costoTotalReceta += insumo.costo_total;
        var equivBs = (tasa && tasa > 0) ? (insumo.costo_total * tasa) : null;
        if (equivBs != null) costoTotalRecetaBs += equivBs;
        var equivBsTexto = formatearBs(equivBs);
        htmlRows += `
            <tr>
                <td>${insumo.insumo_nombre}</td>
                <td>${insumo.cantidad_por_unidad}</td>
                <td>$${insumo.costo_unitario.toFixed(2)}</td>
                <td>$${insumo.costo_total.toFixed(2)}</td>
                <td>${equivBsTexto}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarInsumo(${index})">
                        <i class="fa fa-trash"></i> Eliminar
                    </button>
                </td>
            </tr>
        `;
    });
    
    tbody.html(htmlRows);

    actualizarCostoTotalReceta(costoTotalReceta);
    
    $('#costo-total-receta').text('$' + costoTotalReceta.toFixed(2));
    $('#precio_total').val(costoTotalReceta.toFixed(2));
    $('#costo-total-receta-bs').text(formatearBs(costoTotalRecetaBs > 0 ? costoTotalRecetaBs : null));
    
    if (insumosAgregados.length > 0) {
        $('#tabla-insumos').show();
        $('#mensaje-sin-insumos').hide();
        // Si hay % de ganancia, recalcular precio
        var pct = parseFloat($('#porcentaje_ganancia').val());
        if (!isNaN(pct) && pct >= 0) {
            aplicarPorcentajeGanancia();
        } else {
            var precioTotal = parseFloat($('#precio_total').val()) || 0;
            if (precioTotal > 0) {
                $('#btn-guardar-receta').prop('disabled', false);
            } else {
                $('#btn-guardar-receta').prop('disabled', true);
            }
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

function getCostoTotalReceta() {
    var total = 0;
    insumosAgregados.forEach(function(i) { total += i.costo_total; });
    return total;
}

function aplicarPorcentajeGanancia() {
    var pct = parseFloat($('#porcentaje_ganancia').val()) || 0;
    if (pct < 0) return;
    var costo = getCostoTotalReceta();
    if (costo <= 0) return;
    var precio = costo * (1 + pct / 100);
    $('#precio_total').val(precio.toFixed(2));
    var precioTotal = parseFloat($('#precio_total').val()) || 0;
    if (insumosAgregados.length > 0 && precioTotal > 0) {
        $('#btn-guardar-receta').prop('disabled', false);
    }
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    insumosAgregados = [];
    tasaParaEquivalenteReceta = tasaCambiariaActual;
    actualizarTablaInsumos();
    limpiarFormularioInsumo();
    $('#editar-receta-id').val('');
    $('#precio_total').val('');
    $('#porcentaje_ganancia').val('');
    $('#porcentaje_descuento').val('');
    $('#precio_detal').val('');
    $('#precio_mayor').val('');
}

function editarReceta(data) {
    $('#producto_id').val(data.producto_id || '');
    $('#rango_tallas_id').val(data.rango_tallas_id || '');
    $('#tipo_produccion_id').val(data.tipo_produccion_id || '');
    $('#almacen_id').val(data.almacen_id || '');
    $('#precio_total').val(data.precio_total || '');
    $('#porcentaje_ganancia').val(data.porcentaje_ganancia ?? '');
    $('#stock_minimo').val(data.stock_minimo ?? '');
    $('#stock_maximo').val(data.stock_maximo ?? '');
    $('#precio_detal').val(data.precio_detal || '');
    $('#precio_mayor').val(data.precio_mayor || '');
    $('#observaciones').val(data.observaciones || '');
    $('#editar-receta-id').val(data.id || '');

    let costoTotal = parseFloat(data.costo_total) || 0;
    let pDetal = parseFloat(data.precio_detal) || 0;
    let pMayor = parseFloat(data.precio_mayor) || 0;

    if (costoTotal > 0 && pDetal > 0) {
        let gananciaCalculada = ((pDetal / costoTotal) - 1) * 100;
        $('#porcentaje_ganancia').val(Math.round(gananciaCalculada));

        if (pMayor > 0) {
            let descuentoCalculado = (1 - (pMayor / pDetal)) * 100;
            $('#porcentaje_descuento').val(Math.round(descuentoCalculado));
        }
    } else {
        $('#porcentaje_ganancia').val('');
        $('#porcentaje_descuento').val('');
    }

    tasaParaEquivalenteReceta = (data.tasa_receta != null && parseFloat(data.tasa_receta) > 0) ? parseFloat(data.tasa_receta) : tasaCambiariaActual;
    insumosAgregados = [];
    
    if (data.id) {
        $.post('nuevo_producto_data.php', { action: 'obtener_insumos_receta', id: data.id }, function(resp) {
            if (resp && resp.success && resp.insumos) {
                insumosAgregados = resp.insumos;
                actualizarTablaInsumos(); 
            }
        }, 'json');
    } else {
        actualizarTablaInsumos();
    }
    mostrarVista('crear');
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
    $('#precio_detal').on('input', function() {
        var precioTotal = parseFloat($(this).val()) || 0;
        if (insumosAgregados.length > 0 && precioTotal > 0) {
            $('#btn-guardar-receta').prop('disabled', false);
        } else {
            $('#btn-guardar-receta').prop('disabled', true);
        }
    });

    // Porcentaje de ganancia: al cambiar, calcular precio desde el costo de la receta
    $('#porcentaje_ganancia').on('input change', function() {
        aplicarPorcentajeGanancia();
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
    var detal = parseFloat($("#precio_detal").val()) || 0;
    var mayor = parseFloat($("#precio_mayor").val()) || 0;
    var observaciones = $("#observaciones").val() || "";
    
    if (!producto_id || !rango_tallas_id || !tipo_produccion_id) {
        alert('Por favor completa todos los campos requeridos');
        return;
    }
    
    if (precio_total <= 0) {
        alert('Por favor ingresa un precio total del producto válido');
        return;
    }
    
    var almacen_id = $("#almacen_id").val() || "";
    var porcentaje_ganancia = $("#porcentaje_ganancia").val() || "";
    var stock_minimo = $("#stock_minimo").val() || "";
    var stock_maximo = $("#stock_maximo").val() || "";
    var datos = {
        action: "crear_receta_completa",
        producto_id: producto_id,
        rango_tallas_id: rango_tallas_id,
        tipo_produccion_id: tipo_produccion_id,
        almacen_id: almacen_id,
        precio_total: precio_total,
        porcentaje_ganancia: porcentaje_ganancia,
        stock_minimo: stock_minimo,
        stock_maximo: stock_maximo,
        detal: detal,
        mayor: mayor,
        insumos: JSON.stringify(insumosAgregados),
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