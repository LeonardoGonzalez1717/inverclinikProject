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

$sqlInsumos = "SELECT i.id, i.nombre, i.costo_unitario, um.codigo AS unidad_medida
               FROM insumos i
               INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
               ORDER BY i.nombre";
$resultInsumos = $conn->query($sqlInsumos);
$insumos = [];
if ($resultInsumos) {
    while ($row = $resultInsumos->fetch_assoc()) {
        $insumos[] = $row;
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
    <title>Registrar Compra</title>
    <style>
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Registrar Compra</h2>
                
                <!-- <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Registrar Nueva Compra</button>
                    </div>
                </div> -->

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div class="d-flex align-items-center" style="gap: 10px; margin-bottom: 12px;" aria-label="Acciones de compras">
                                    <button class="btn btn-success" id="btn-ir-crear" title="Registrar Nueva Compra">
                                        <i class="fas fa-plus-circle"></i> Nueva Compra
                                    </button>
                                    <button class="btn btn-outline-secondary" id="btn-toggle-filtros" title="Filtros">
                                        <i class="fas fa-filter"></i> Filtros
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-sm-4">
                                    <label for="filtro-proveedor">Proveedor</label>
                                    <input type="text" id="filtro-proveedor" class="form-control clase-filtro-compra" placeholder="Buscar por proveedor o RIF...">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-factura">Nro. Factura</label>
                                    <input type="text" id="filtro-factura" class="form-control clase-filtro-compra" placeholder="Buscar Nro. Factura...">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-estado">Estado de Compra</label>
                                    <select id="filtro-estado" class="form-control clase-filtro-compra">
                                        <option value="">Todos</option>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="recibido">Recibido</option>
                                        <option value="cancelado">Cancelado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <label for="filtro-desde">Fecha Desde</label>
                                    <input type="date" id="filtro-desde" class="form-control clase-filtro-compra">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-hasta">Fecha Hasta</label>
                                    <input type="date" id="filtro-hasta" class="form-control clase-filtro-compra">
                                </div>
                                <div class="col-sm-4" style="margin-top: 25px;">
                                    <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros-compra">
                                        <i class="fas fa-eraser"></i> Limpiar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th>Nro. Factura</th>
                                        <th>Cantidad</th>
                                        <th>Total</th>
                                        <th>Total Bs.</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                        <div id="paginacion-compras"></div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <button class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>
                        <!-- <h5 class="subtitle">Registrar Nueva Compra</h5> -->
                        <form id="form-compra">
                            <input type="hidden" name="id_orden_compra" id="id_orden_compra" value="">
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

                            <!-- Selector/Botón opcional: Cargar datos desde Orden de Compra (Encima de Proveedor) -->
                            <div class="card mb-3" style="padding: 12px 16px; background-color: #f0f6ff; border: 1px dashed #0056b3; border-radius: 8px;">
                                <div class="d-flex justify-content-between align-items-center flex-wrap" style="gap: 10px;">
                                    <div>
                                        <strong style="color: #0056b3;">Buscar Orden de Compra</strong>
                                        <div class="small text-muted">Seleccione primero el <strong>Proveedor</strong> para consultar sus Órdenes de Compra en estado <strong>EMITIDA</strong>.</div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span id="badge-oc-cargada" class="badge badge-primary" style="display: none; font-size: 0.9rem; padding: 6px 10px;"></span>
                                        <button type="button" class="btn btn-sm btn-outline-danger" id="btn-quitar-oc" style="display: none;" title="Desvincular Orden de Compra">
                                            <i class="fas fa-unlink"></i> Quitar O.C.
                                        </button>
                                        <button type="button" class="btn btn-sm btn-primary" id="btn-abrir-modal-oc">
                                            <i class="fas fa-search"></i> Cargar datos desde Orden de Compra
                                        </button>
                                    </div>
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
                                                <i class="fa fa-plus"></i> 
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
                                                <th>Equiv. Bs.</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-insumos">
                                        </tbody>
                                        <tfoot>
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td colspan="3" style="text-align: right;">Total:</td>
                                                <td id="total-compra" style="color: #0056b3; font-size: 1.1em;">$0.00</td>
                                                <td id="total-compra-bs" style="color: #0056b3; font-size: 1.1em;">—</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <small class="text-muted" id="texto-tasa-informativa-compra"></small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12" style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary" id="btn-guardar-compra"><i class="fas fa-save"></i> Guardar Compra</button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="mostrarVista('listado')"><i class="fas fa-times"></i> Cancelar</button>
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
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h5 class="modal-title" id="modalDetalleCompraLabel">Detalle de la Compra</h5>
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

    <!-- Modal para cargar Orden de Compra pendiente (EMITIDA) del proveedor -->
    <div class="modal fade" id="modalOrdenesCompraPendientes" tabindex="-1" role="dialog" aria-labelledby="modalOrdenesCompraPendientesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h5 class="modal-title" id="modalOrdenesCompraPendientesLabel">
                        <i class="fas fa-file-invoice-dollar"></i> Órdenes de Compra Pendientes (Estado: EMITIDA)
                    </h5>
                </div>
                <div class="modal-body" id="modalOrdenesCompraPendientesBody">
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
var tasaCambiariaActual = <?php echo $tasa_actual !== null ? json_encode($tasa_actual) : 'null'; ?>;

$('#btn-ir-crear').on('click', function() {
    $('#vista-listado').fadeOut(200, function() {
        $('#vista-crear').removeClass('hidden').fadeIn();
        limpiarFormulario();
        // cargarStockInsumos(); 
    });
});

$('#btn-volver-listado').on('click', function() {
    Swal.fire({
        icon: 'question',
        text: '¿Desea salir? Se perderán los cambios no guardados.',
        showCancelButton: true,
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $('#vista-crear').fadeOut(200, function() {
            $('#vista-listado').fadeIn();
        });
    });
});

function formatearBs(valor) {
    if (valor == null || isNaN(valor)) return '—';
    return 'Bs. ' + parseFloat(valor).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function mostrarVista(vista) {
    $('#vista-listado, #vista-crear').addClass('hidden').hide();
    $('#vista-' + vista).removeClass('hidden').fadeIn(250);
}

function cargarListado(page) {
    var params = { 
        action: 'listar_html',
        buscar_proveedor: $('#filtro-proveedor').val(),
        buscar_factura: $('#filtro-factura').val(),
        estado: $('#filtro-estado').val(),
        fecha_desde: $('#filtro-desde').val(),
        fecha_hasta: $('#filtro-hasta').val()
    };

    crudPostListadoPaginado(
        'registrar_compra_data.php',
        params,
        '#vista-listado tbody',
        '#paginacion-compras',
        page || 1
    );
    limpiarFormulario();
}

function limpiarOrdenCompraSeleccionada() {
    $('#id_orden_compra').val('');
    $('#badge-oc-cargada').text('').hide();
    $('#btn-quitar-oc').hide();
}

function limpiarFormulario() {
    $('#form-compra')[0].reset();
    $('#fecha').val('<?php echo date('Y-m-d'); ?>');
    $('#estado').val('pendiente');
    $('#proveedor_id').val('');
    limpiarOrdenCompraSeleccionada();
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
        Swal.fire({ icon: 'warning', text: 'Por favor selecciona un insumo e ingresa una cantidad válida' });
        return;
    }
    
    if (costoUnitario <= 0) {
        Swal.fire({ icon: 'warning', text: 'Por favor ingresa un costo unitario válido' });
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
    
    var totalCompraBs = 0;
    insumosAgregados.forEach(function(insumo, index) {
        totalCompra += insumo.subtotal;
        var equivBs = (tasaCambiariaActual && tasaCambiariaActual > 0) ? (insumo.subtotal * tasaCambiariaActual) : null;
        if (equivBs != null) totalCompraBs += equivBs;
        var equivBsTexto = formatearBs(equivBs);
        var row = `
            <tr>
                <td>${insumo.insumo_nombre}</td>
                <td>${insumo.cantidad} ${insumo.unidad_medida}</td>
                <td>$${insumo.costo_unitario.toFixed(2)}</td>
                <td>$${insumo.subtotal.toFixed(2)}</td>
                <td>${equivBsTexto}</td>
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
    $('#total-compra-bs').text(formatearBs(totalCompraBs > 0 ? totalCompraBs : null));
    if (tasaCambiariaActual && tasaCambiariaActual > 0) {
        $('#texto-tasa-informativa-compra').text('Tasa informativa: ' + tasaCambiariaActual.toFixed(4) + ' Bs/USD').show();
    } else {
        $('#texto-tasa-informativa-compra').hide();
    }
    
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
                Swal.fire({ icon: 'error', text: 'Error: No se pudo cargar el modal. Por favor, recarga la página.' });
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
            html += '<div class="col-md-4"><strong>Número de Factura:</strong> ' + (resp.compra.numero_factura || '-') + '</div>';
            html += '<div class="col-md-4"><strong>Orden de Compra:</strong> ' + (resp.compra.codigo_oc ? '<span class="badge badge-info">' + resp.compra.codigo_oc + '</span>' : '<span class="text-muted">Compra Directa</span>') + '</div>';
            html += '<div class="col-md-4"><strong>Estado:</strong> ' + resp.compra.estado_badge + '</div>';
            html += '</div>';
            
            html += '<hr>';
            html += '<h6><strong>Detalle de Insumos:</strong></h6>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-sm">';
            html += '<thead><tr><th>Insumo</th><th>Cantidad</th><th>Costo Unitario</th><th>Subtotal</th></tr></thead>';
            html += '<tbody>';
            
            var total = 0;
            resp.detalles.forEach(function(detalle) {
                total += parseFloat(detalle.subtotal);
                html += '<tr>';
                html += '<td style="text-align: left;">' + detalle.insumo_nombre + ' (' + detalle.unidad_medida + ')</td>';
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

function abrirModalOrdenesCompra() {
    var proveedorId = $('#proveedor_id').val();
    if (!proveedorId) {
        Swal.fire({
            icon: 'warning',
            title: 'Seleccione un Proveedor',
            text: 'Solo después de seleccionar el proveedor se podrá abrir el modal para cargar las órdenes de compra asignadas a ese proveedor.'
        });
        return;
    }

    var proveedorNombre = $('#proveedor_id option:selected').text().trim();
    $('#modalOrdenesCompraPendientesLabel').html('<i class="fas fa-file-invoice-dollar"></i> Órdenes de Compra Pendientes - ' + proveedorNombre);
    $('#modalOrdenesCompraPendientesBody').html('<div class="text-center p-4"><div class="spinner-border text-primary" role="status"><span class="sr-only">Cargando...</span></div></div>');
    $('#modalOrdenesCompraPendientes').modal('show');

    $.post('registrar_compra_data.php', {
        action: 'obtener_ordenes_compra_proveedor',
        proveedor_id: proveedorId
    }, function(resp) {
        if (!resp || !resp.success) {
            $('#modalOrdenesCompraPendientesBody').html('<div class="alert alert-danger">Error al consultar las órdenes de compra.</div>');
            return;
        }
        var ordenes = resp.ordenes || [];
        if (ordenes.length === 0) {
            $('#modalOrdenesCompraPendientesBody').html('<div class="alert alert-info mb-0">No existen Órdenes de Compra en estado <strong>EMITIDA</strong> pendientes para el proveedor seleccionado.</div>');
            return;
        }
        var html = '<div class="table-responsive"><table class="table table-sm table-bordered table-hover">';
        html += '<thead class="thead-light"><tr><th>Código O.C.</th><th>Fecha Emisión</th><th>Ítems</th><th>Total Estimado</th><th>Observaciones</th><th>Acción</th></tr></thead><tbody>';
        ordenes.forEach(function(oc) {
            if (!oc) return;
            var montoOc = parseFloat(oc.monto_total !== undefined ? oc.monto_total : (oc.total || 0)) || 0;
            var totalItems = oc.total_items !== undefined ? oc.total_items : 0;
            var codigoOc = oc.codigo_oc || ('OC #' + (oc.id || ''));
            var fechaFmt = oc.fecha_formateada || oc.fecha || '-';
            html += '<tr>';
            html += '<td><strong>' + codigoOc + '</strong></td>';
            html += '<td>' + fechaFmt + '</td>';
            html += '<td class="text-center"><span class="badge badge-secondary">' + totalItems + '</span></td>';
            html += '<td><strong>$' + montoOc.toFixed(2) + '</strong></td>';
            html += '<td>' + (oc.observaciones || '-') + '</td>';
            html += '<td class="text-center"><button type="button" class="btn btn-sm btn-success" onclick="seleccionarOrdenCompra(' + oc.id + ')"><i class="fas fa-download"></i> Cargar O.C.</button></td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        $('#modalOrdenesCompraPendientesBody').html(html);
    }, 'json').fail(function() {
        $('#modalOrdenesCompraPendientesBody').html('<div class="alert alert-danger">Error de conexión al cargar órdenes de compra.</div>');
    });
}

function seleccionarOrdenCompra(ordenId) {
    $.post('registrar_compra_data.php', {
        action: 'obtener_insumos_orden_compra',
        orden_compra_id: ordenId
    }, function(resp) {
        if (!resp || !resp.success) {
            Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'No se pudo cargar la Orden de Compra.' });
            return;
        }

        var oc = (resp && (resp.orden || resp.orden_compra || resp.data)) ? (resp.orden || resp.orden_compra || resp.data) : (resp || {});
        var idProveedor = oc.id_proveedor || oc.proveedor_id || resp.id_proveedor || resp.proveedor_id || $('#proveedor_id').val() || '';
        var idOrden = oc.id || oc.id_orden_compra || resp.id || ordenId || '';
        var codigoOc = oc.codigo_oc || resp.codigo_oc || ('OC #' + idOrden);

        if (idProveedor && String($('#proveedor_id').val() || '') !== String(idProveedor)) {
            $('#proveedor_id').val(idProveedor);
            cargarInsumosPorProveedor(idProveedor);
        }

        $('#id_orden_compra').val(idOrden);
        $('#badge-oc-cargada').html('<i class="fas fa-link"></i> O.C. Vinculada: ' + codigoOc).show();
        $('#btn-quitar-oc').show();

        insumosAgregados = [];
        var listaInsumos = Array.isArray(resp.insumos) ? resp.insumos : (Array.isArray(oc.insumos) ? oc.insumos : []);
        listaInsumos.forEach(function(item) {
            if (!item) return;
            var cant = parseFloat(item.cantidad) || 0;
            var costo = parseFloat(item.costo_unitario) || 0;
            var insumoId = item.insumo_id || item.id_insumo || '';
            if (!insumoId || cant <= 0) return;
            insumosAgregados.push({
                insumo_id: insumoId,
                insumo_nombre: item.insumo_nombre || item.nombre || ('Insumo #' + insumoId),
                unidad_medida: item.unidad_medida || 'unidad',
                cantidad: cant,
                costo_unitario: costo,
                subtotal: cant * costo
            });
        });

        actualizarTablaInsumos();
        $('#modalOrdenesCompraPendientes').modal('hide');
        Swal.fire({
            icon: 'success',
            title: 'Orden de Compra Cargada',
            text: 'Se han cargado los insumos de la Orden de Compra ' + codigoOc + '.',
            timer: 2000,
            showConfirmButton: false
        });
    }, 'json').fail(function() {
        Swal.fire({ icon: 'error', text: 'Error de conexión al cargar los insumos de la Orden de Compra.' });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado(1);
    bindCrudPagination('#paginacion-compras', cargarListado);

    $('#btn-abrir-modal-oc').on('click', function() {
        abrirModalOrdenesCompra();
    });

    $('#btn-quitar-oc').on('click', function() {
        limpiarOrdenCompraSeleccionada();
        insumosAgregados = [];
        actualizarTablaInsumos();
    });
    
    // DENTRO DE TU document.addEventListener('DOMContentLoaded', function() { ... }) AGREGA ESTO:
    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    // Escuchar cambios en los filtros para recargar al escribir o seleccionar
    $('.clase-filtro-compra').on('change keyup', function(e) {
        if (e.type === 'keyup' && e.key !== 'Enter') return; // Que busque al dar Enter o al cambiar select/dates
        cargarListado(1);
    });

    $('#btn-limpiar-filtros-compra').on('click', function() {
        $('#filtro-proveedor').val('');
        $('#filtro-factura').val('');
        $('#filtro-estado').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarListado(1);
    });
    
    $('#proveedor_id').on('change', function() {
        var proveedorId = $(this).val();
        limpiarOrdenCompraSeleccionada();
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

    // Si viene redirigido desde Órdenes de Compra (?oc_id=X&proveedor_id=Y)
    var urlParams = new URLSearchParams(window.location.search);
    var ocIdParam = parseInt(urlParams.get('oc_id') || '0', 10);
    var provIdParam = parseInt(urlParams.get('proveedor_id') || '0', 10);
    if (ocIdParam > 0 && provIdParam > 0) {
        mostrarVista('crear');
        limpiarFormulario();
        $('#proveedor_id').val(provIdParam);
        cargarInsumosPorProveedor(provIdParam);
        seleccionarOrdenCompra(ocIdParam);
    }
});

$("#form-compra").on("submit", function(e) {
    e.preventDefault();

    if (insumosAgregados.length === 0) {
        Swal.fire({ icon: 'warning', text: 'Por favor agrega al menos un insumo a la compra' });
        return;
    }

    var datos = {
        action: 'crear',
        proveedor_id: $("#proveedor_id").val(),
        id_orden_compra: $("#id_orden_compra").val() || null,
        fecha: $("#fecha").val(),
        numero_factura: $("#numero_factura").val() || "",
        estado: $("#estado").val(),
        insumos: insumosAgregados
    };

    if (!datos.proveedor_id || !datos.fecha) {
        Swal.fire({ icon: 'warning', text: 'Por favor completa todos los campos obligatorios' });
        return;
    }

    function enviarCompraAlServidor() {
        $.ajax({
            url: "registrar_compra_data.php",
            type: "POST",
            data: JSON.stringify(datos),
            contentType: "application/json",
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    mostrarVista("listado");
                    cargarListado();
                } else {
                    Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Respuesta inválida") });
                }
            },
            error: function(xhr) {
                console.error("Error:", xhr.responseText);
                try {
                    var resp = JSON.parse(xhr.responseText);
                    Swal.fire({ icon: 'error', text: "Error: " + (resp.message || "Error al guardar la compra") });
                } catch (e) {
                    Swal.fire({ icon: 'error', text: "Error de conexión." });
                }
            }
        });
    }

    $.ajax({
        url: "registrar_compra_data.php",
        type: "POST",
        data: JSON.stringify({ action: 'prevalidar_stock_max', insumos: insumosAgregados }),
        contentType: "application/json",
        success: function(pre) {
            if (!pre || !pre.success) {
                Swal.fire({ icon: 'error', text: pre && pre.message ? pre.message : 'Error al validar el stock' });
                return;
            }
            if (pre.requiere_confirmacion) {
                Swal.fire({
                    icon: 'warning',
                    text: 'El stock máximo ha sido superado, ¿estás seguro que deseas continuar?',
                    showCancelButton: true,
                    confirmButtonText: 'Continuar',
                    cancelButtonText: 'Cancelar'
                }).then(function(r) {
                    if (r.isConfirmed) enviarCompraAlServidor();
                });
                return;
            }
            enviarCompraAlServidor();
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            try {
                var resp = JSON.parse(xhr.responseText);
                Swal.fire({ icon: 'error', text: "Error: " + (resp.message || "Error al validar") });
            } catch (e) {
                Swal.fire({ icon: 'error', text: "Error de conexión." });
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

