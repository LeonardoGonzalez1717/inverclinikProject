<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

$sqlProveedores = "SELECT id, nombre FROM proveedores ORDER BY nombre";
$resultProveedores = $conn->query($sqlProveedores);
$proveedores = [];
if ($resultProveedores) {
    while ($row = $resultProveedores->fetch_assoc()) {
        $proveedores[] = $row;
    }
}

$sqlInsumos = "SELECT id, nombre, costo_unitario, unidad_medida FROM insumos ORDER BY nombre";
$resultInsumos = $conn->query($sqlInsumos);
$insumos = [];
if ($resultInsumos) {
    while ($row = $resultInsumos->fetch_assoc()) {
        $insumos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Compra</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Registrar Compra</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Registrar Nueva Compra</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Compras</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th>Número Factura</th>
                                        <th>Cantidad</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Registrar Nueva Compra</h5>
                        <form id="form-compra">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Proveedor <span style="color: red;">*</span></label>
                            <select name="proveedor_id" id="proveedor_id" class="form-control" required>
                                <option value="">-- Seleccione un proveedor --</option>
                                <?php foreach ($proveedores as $prov): ?>
                                    <option value="<?php echo htmlspecialchars($prov['id']); ?>">
                                        <?php echo htmlspecialchars($prov['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha <span style="color: red;">*</span></label>
                            <input type="date" name="fecha" id="fecha" class="form-control" required 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Número de Factura</label>
                            <input type="text" name="numero_factura" id="numero_factura" class="form-control" 
                                   maxlength="50" placeholder="Ej: FAC-001">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" id="estado" class="form-control">
                                <option value="pendiente">Pendiente</option>
                                <option value="recibido">Recibido</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                    </div>

                    <hr style="margin: 20px 0; border-color: #dee2e6;">

                    <div class="mb-3">
                        <h5 style="color: #0056b3; margin-bottom: 15px;">Detalle de la Compra</h5>
                        
                        <div class="card" style="padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">Insumo</label>
                                    <select id="nuevo-insumo-id" class="form-control" disabled>
                                        <option value="">-- Primero seleccione un proveedor --</option>
                                    </select>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" step="0.01" min="0.01" id="nuevo-cantidad" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Costo Unitario</label>
                                    <input type="number" step="0.01" min="0" id="nuevo-costo-unitario" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Subtotal</label>
                                    <input type="text" id="nuevo-subtotal" class="form-control" readonly placeholder="$0.00">
                                </div>
                                <div class="col-md-1 mb-2 d-flex align-items-end" style="position: relative; top: 22px;">
                                    <button type="button" class="btn btn-success" id="btn-agregar-insumo" style="width: fit-content; margin-bottom: 0;">
                                        <i class="fa fa-plus"></i> Agregar
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="mensaje-sin-insumos" class="alert alert-info" style="display: none;">
                            No hay insumos agregados. Por favor, agrega al menos un insumo a la compra.
                        </div>

                        <div id="tabla-insumos" style="display: none;">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>Insumo</th>
                                        <th>Cantidad</th>
                                        <th>Costo Unitario</th>
                                        <th>Subtotal</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-insumos">
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                                        <td colspan="3" style="text-align: right;">Total:</td>
                                        <td id="total-compra" style="color: #0056b3; font-size: 1.1em;">$0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary" id="btn-guardar-compra">Guardar Compra</button>
                                    <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetalleCompra" tabindex="-1" role="dialog" aria-labelledby="modalDetalleCompraLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalleCompraLabel">Detalle de la Compra</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalDetalleCompraBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Cargando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

<script>
var insumosAgregados = [];

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
    $.post('registrar_compra_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-compra')[0].reset();
    $('#fecha').val('<?php echo date('Y-m-d'); ?>');
    $('#estado').val('pendiente');
    $('#proveedor_id').val('');
    insumosAgregados = [];
    actualizarTablaInsumos();
    limpiarFormularioInsumo();
    $('#nuevo-insumo-id').html('<option value="">-- Primero seleccione un proveedor --</option>').prop('disabled', true);
}

function calcularSubtotal(cantidad, costoUnitario) {
    return cantidad * costoUnitario;
}

function agregarInsumo() {
    var insumoId = $('#nuevo-insumo-id').val();
    var cantidad = parseFloat($('#nuevo-cantidad').val()) || 0;
    var costoUnitario = parseFloat($('#nuevo-costo-unitario').val()) || 0;
    
    if (!insumoId || cantidad <= 0) {
        alert('Por favor selecciona un insumo e ingresa una cantidad válida');
        return;
    }
    
    if (costoUnitario <= 0) {
        alert('Por favor ingresa un costo unitario válido');
        return;
    }
    
    var insumoNombre = $('#nuevo-insumo-id option:selected').data('nombre');
    var unidadMedida = $('#nuevo-insumo-id option:selected').data('unidad');
    var subtotal = calcularSubtotal(cantidad, costoUnitario);
    
    var insumo = {
        insumo_id: insumoId,
        insumo_nombre: insumoNombre,
        unidad_medida: unidadMedida,
        cantidad: cantidad,
        costo_unitario: costoUnitario,
        subtotal: subtotal
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
    tbody.empty();
    
    var totalCompra = 0;
    
    insumosAgregados.forEach(function(insumo, index) {
        totalCompra += insumo.subtotal;
        var row = `
            <tr>
                <td>${insumo.insumo_nombre}</td>
                <td>${insumo.cantidad} ${insumo.unidad_medida}</td>
                <td>$${insumo.costo_unitario.toFixed(2)}</td>
                <td>$${insumo.subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarInsumo(${index})">
                        <i class="fa fa-trash"></i> Eliminar
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
    
    $('#total-compra').text('$' + totalCompra.toFixed(2));
    
    if (insumosAgregados.length > 0) {
        $('#tabla-insumos').show();
        $('#mensaje-sin-insumos').hide();
        $('#btn-guardar-compra').prop('disabled', false);
    } else {
        $('#tabla-insumos').hide();
        $('#mensaje-sin-insumos').show();
        $('#btn-guardar-compra').prop('disabled', true);
    }
}

function limpiarFormularioInsumo() {
    $('#nuevo-insumo-id').val('');
    $('#nuevo-cantidad').val('');
    $('#nuevo-costo-unitario').val('');
    $('#nuevo-subtotal').val('');
}

function verDetalle(compraId) {
    $('#modalDetalleCompraBody').html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Cargando...</span></div></div>');
    
    var intentos = 0;
    var maxIntentos = 50;
    
    function mostrarModal() {
        if (typeof $.fn.modal !== 'undefined') {
            $('#modalDetalleCompra').modal('show');
        } else {
            intentos++;
            if (intentos < maxIntentos) {
                setTimeout(mostrarModal, 100);
            } else {
                console.error('Bootstrap modal no está disponible después de varios intentos');
                alert('Error: No se pudo cargar el modal. Por favor, recarga la página.');
            }
        }
    }
    
    mostrarModal();
    
    $.post('registrar_compra_data.php', {
        action: 'obtener_detalle',
        compra_id: compraId
    }, function(resp) {
        if (resp && resp.success) {
            var html = '<div class="row mb-3">';
            html += '<div class="col-md-6"><strong>Proveedor:</strong> ' + resp.compra.proveedor_nombre + '</div>';
            html += '<div class="col-md-6"><strong>Fecha:</strong> ' + resp.compra.fecha_formateada + '</div>';
            html += '</div>';
            
            html += '<div class="row mb-3">';
            html += '<div class="col-md-6"><strong>Número de Factura:</strong> ' + (resp.compra.numero_factura || '-') + '</div>';
            html += '<div class="col-md-6"><strong>Estado:</strong> ' + resp.compra.estado_badge + '</div>';
            html += '</div>';
            
            html += '<hr>';
            html += '<h6><strong>Detalle de Insumos:</strong></h6>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-bordered table-sm">';
            html += '<thead><tr><th>Insumo</th><th>Cantidad</th><th>Costo Unitario</th><th>Subtotal</th></tr></thead>';
            html += '<tbody>';
            
            var total = 0;
            resp.detalles.forEach(function(detalle) {
                total += parseFloat(detalle.subtotal);
                html += '<tr>';
                html += '<td>' + detalle.insumo_nombre + ' (' + detalle.unidad_medida + ')</td>';
                html += '<td>' + parseFloat(detalle.cantidad).toFixed(2) + '</td>';
                html += '<td>$' + parseFloat(detalle.costo_unitario).toFixed(2) + '</td>';
                html += '<td>$' + parseFloat(detalle.subtotal).toFixed(2) + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '<tfoot><tr style="background-color: #f8f9fa; font-weight: bold;"><td colspan="3" style="text-align: right;">Total:</td><td>$' + total.toFixed(2) + '</td></tr></tfoot>';
            html += '</table>';
            html += '</div>';
            
            $('#modalDetalleCompraBody').html(html);
        } else {
            $('#modalDetalleCompraBody').html('<div class="alert alert-danger">Error al cargar los detalles de la compra</div>');
        }
    }, 'json').fail(function() {
        $('#modalDetalleCompraBody').html('<div class="alert alert-danger">Error de conexión al cargar los detalles</div>');
    });
}

function cargarInsumosPorProveedor(proveedorId) {
    if (!proveedorId) {
        $('#nuevo-insumo-id').html('<option value="">-- Primero seleccione un proveedor --</option>').prop('disabled', true);
        limpiarFormularioInsumo();
        return;
    }
    
    $.post('registrar_compra_data.php', {
        action: 'obtener_insumos_proveedor',
        proveedor_id: proveedorId
    }, function(resp) {
        if (resp && resp.success && resp.insumos) {
            var select = $('#nuevo-insumo-id');
            select.html('<option value="">-- Seleccione un insumo --</option>');
            
            resp.insumos.forEach(function(insumo) {
                var option = $('<option></option>')
                    .attr('value', insumo.id)
                    .attr('data-costo', insumo.costo_unitario)
                    .attr('data-nombre', insumo.nombre)
                    .attr('data-unidad', insumo.unidad_medida)
                    .text(insumo.nombre + ' ($' + parseFloat(insumo.costo_unitario).toFixed(2) + ' / ' + insumo.unidad_medida + ')');
                select.append(option);
            });
            
            select.prop('disabled', false);
        } else {
            $('#nuevo-insumo-id').html('<option value="">-- No hay insumos para este proveedor --</option>').prop('disabled', true);
        }
    }, 'json').fail(function() {
        $('#nuevo-insumo-id').html('<option value="">-- Error al cargar insumos --</option>').prop('disabled', true);
    });
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado();
    
    $('#proveedor_id').on('change', function() {
        var proveedorId = $(this).val();
        cargarInsumosPorProveedor(proveedorId);
        insumosAgregados = [];
        actualizarTablaInsumos();
        limpiarFormularioInsumo();
    });
    
    $('#nuevo-insumo-id').on('change', function() {
        var costo = $(this).find('option:selected').data('costo');
        if (costo) {
            $('#nuevo-costo-unitario').val(costo);
            calcularSubtotalInsumo();
        }
    });
    
    $('#nuevo-cantidad, #nuevo-costo-unitario').on('input', function() {
        calcularSubtotalInsumo();
    });
    
    function calcularSubtotalInsumo() {
        var cantidad = parseFloat($('#nuevo-cantidad').val()) || 0;
        var costoUnitario = parseFloat($('#nuevo-costo-unitario').val()) || 0;
        var subtotal = calcularSubtotal(cantidad, costoUnitario);
        $('#nuevo-subtotal').val('$' + subtotal.toFixed(2));
    }
    
    $('#btn-agregar-insumo').on('click', function() {
        agregarInsumo();
    });
    
    $('#nuevo-cantidad, #nuevo-costo-unitario').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            agregarInsumo();
        }
    });
    
    $('#btn-guardar-compra').prop('disabled', true);
});

$("#form-compra").on("submit", function(e) {
    e.preventDefault();

    if (insumosAgregados.length === 0) {
        alert('Por favor agrega al menos un insumo a la compra');
        return;
    }

    var datos = {
        action: 'crear',
        proveedor_id: $("#proveedor_id").val(),
        fecha: $("#fecha").val(),
        numero_factura: $("#numero_factura").val() || "",
        estado: $("#estado").val(),
        insumos: insumosAgregados
    };

    if (!datos.proveedor_id || !datos.fecha) {
        alert('Por favor completa todos los campos obligatorios');
        return;
    }

    $.ajax({
        url: "registrar_compra_data.php",
        type: "POST",
        data: JSON.stringify(datos),
        contentType: "application/json",
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
            try {
                var resp = JSON.parse(xhr.responseText);
                alert("Error: " + (resp.message || "Error al guardar la compra"));
            } catch (e) {
                alert("Error de conexión.");
            }
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

