<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../lib/AbastecimientoService.php";

AbastecimientoService::asegurarTablas($conn);

$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;
$joinInv = $tieneInventarioNuevo
    ? "LEFT JOIN inventario inv ON inv.tipo_item = 'insumo' AND inv.tipo_item_id = i.id"
    : "LEFT JOIN inventario inv ON inv.insumo_id = i.id";

$sqlInsumos = "
    SELECT 
        i.id,
        i.nombre,
        i.costo_unitario,
        i.stock_minimo,
        COALESCE(inv.stock_actual, 0) AS stock_actual,
        um.codigo AS unidad_medida,
        COALESCE(um.permite_movimiento_decimal, 1) AS permite_decimal,
        COALESCE(p.nombre, 'Sin proveedor') AS proveedor_nombre
    FROM insumos i
    INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
    LEFT JOIN proveedores p ON p.id = i.proveedor_id
    {$joinInv}
    WHERE COALESCE(i.activo, 1) = 1
    ORDER BY i.nombre ASC
";
$resInsumos = $conn->query($sqlInsumos);
$insumos = [];
if ($resInsumos) {
    while ($row = $resInsumos->fetch_assoc()) {
        $insumos[] = $row;
    }
}

$nombreUsuarioSesion = $_SESSION['username'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Requisiciones de Compra - INVERCLINIK</title>
    <style>
        .info-stock-badge {
            display: inline-block;
            padding: 4px 10px;
            background: #eef4ff;
            border: 1px solid #cfe2ff;
            color: #084298;
            border-radius: 5px;
            font-size: 12.5px;
            margin-top: 6px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Requisiciones de Compra</h2>
                <!-- <p class="text-muted" style="margin-top: -8px; margin-bottom: 18px;">
                    Solicitud interna de insumos. Solo las requisiciones en estado <strong>PENDIENTE</strong> pueden ser editadas o eliminadas.
                </p> -->

                <div id="contenedor-vistas">
                    <!-- VISTA LISTADO -->
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div class="d-flex align-items-center" style="gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                                    <?php if (!esLector()): ?>
                                    <button class="btn btn-success" id="btn-ir-crear" title="Crear Nueva Requisición">
                                        <i class="fas fa-plus-circle"></i> Nueva Requisición
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-secondary" id="btn-toggle-filtros" title="Filtros">
                                        <i class="fas fa-filter"></i> Filtros
                                    </button>
                                    <!-- <a href="aprobacion_requisiciones.php" class="btn btn-outline-primary" title="Ir al panel de aprobación">
                                        <i class="fas fa-check-double"></i> Aprobación de Requisiciones
                                    </a>
                                    <a href="ordenes_compra.php" class="btn btn-outline-info" title="Ir a Órdenes de Compra">
                                        <i class="fas fa-file-invoice"></i> Órdenes de Compra
                                    </a> -->
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-sm-4">
                                    <label for="filtro-texto">Código / Solicitante / Justificación</label>
                                    <input type="text" id="filtro-texto" class="form-control clase-filtro-req" placeholder="">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-estado">Estado</label>
                                    <select id="filtro-estado" class="form-control clase-filtro-req">
                                        <option value="">Todos</option>
                                        <option value="1">1 - PENDIENTE</option>
                                        <option value="2">2 - APROBADA</option>
                                        <option value="3">3 - RECHAZADA</option>
                                        <option value="4">PROCESADA (O.C. Completa)</option>
                                        <option value="5">PARCIAL (O.C. Parcial)</option>
                                    </select>
                                </div>
                                <div class="col-sm-4" style="margin-top: 25px;">
                                    <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros-req">
                                        <i class="fas fa-eraser"></i> Limpiar Filtros
                                    </button>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <label for="filtro-desde">Fecha Desde</label>
                                    <input type="date" id="filtro-desde" class="form-control clase-filtro-req">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-hasta">Fecha Hasta</label>
                                    <input type="date" id="filtro-hasta" class="form-control clase-filtro-req">
                                </div>
                            </div>
                        </div>

                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Código</th>
                                        <th>Fecha Solicitud</th>
                                        <th>Solicitante</th>
                                        <th>Justificación</th>
                                        <th>Ítems (Cant. Total)</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="paginacion-requisiciones"></div>
                    </div>

                    <!-- VISTA CREAR / EDITAR -->
                    <div id="vista-crear" class="hidden">
                        <button type="button" class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>

                        <h4 id="titulo-form-requisicion" style="color: #0056b3; margin-bottom: 15px; font-weight: bold;">
                            Nueva Requisición de Compra
                        </h4>

                        <form id="form-requisicion">
                            <input type="hidden" id="req_id" name="id" value="">

                            <div class="row mb-3">
                                <div class="col-md-4">
                                    <label class="form-label">Fecha de Solicitud <span style="color: red;">*</span></label>
                                    <input type="date" id="req_fecha_solicitud" name="fecha_solicitud" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Usuario Solicitante</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($nombreUsuarioSesion); ?>" readonly style="background-color: #e9ecef;">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Estado</label>
                                    <input type="text" class="form-control" value="1 - PENDIENTE" readonly style="background-color: #fff3cd; font-weight: bold; color: #856404;">
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-7">
                                    <label class="form-label">Justificación de la Solicitud <span style="color: red;">*</span></label>
                                    <textarea id="req_justificacion" name="justificacion" class="form-control" rows="2" required placeholder="Indique el motivo o necesidad operativa de los insumos solicitados..."></textarea>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Observaciones Adicionales</label>
                                    <textarea id="req_observaciones" name="observaciones" class="form-control" rows="2" placeholder="Observaciones opcionales (área destino, prioridad, etc.)"></textarea>
                                </div>
                            </div>

                            <hr style="margin: 20px 0; border-color: #dee2e6;">

                            <div class="mb-3">
                                <h5 style="color: #0056b3; margin-bottom: 15px;">Insumos Solicitados</h5>

                                <div class="card" style="padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">
                                    <div class="row">
                                        <div class="col-md-5 mb-2">
                                            <label class="form-label">Insumo</label>
                                            <select id="nuevo-req-insumo-id" class="form-control">
                                                <option value="">-- Seleccione un insumo --</option>
                                                <?php foreach ($insumos as $ins): ?>
                                                    <option value="<?php echo (int) $ins['id']; ?>"
                                                            data-nombre="<?php echo htmlspecialchars($ins['nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-unidad="<?php echo htmlspecialchars($ins['unidad_medida'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-proveedor="<?php echo htmlspecialchars($ins['proveedor_nombre'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-stock="<?php echo (float) $ins['stock_actual']; ?>"
                                                            data-minimo="<?php echo $ins['stock_minimo'] !== null ? (float) $ins['stock_minimo'] : ''; ?>"
                                                            data-decimal="<?php echo (int) $ins['permite_decimal']; ?>">
                                                        <?php echo htmlspecialchars($ins['nombre'] . ' (' . $ins['unidad_medida'] . ') - Prov: ' . $ins['proveedor_nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div id="info-stock-req-insumo" style="display: none;">
                                                <span class="info-stock-badge" id="texto-stock-req-insumo"></span>
                                            </div>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Cantidad Solicitada</label>
                                            <input type="number" step="0.01" min="0.01" id="nuevo-req-cantidad" class="form-control" placeholder="0.00">
                                        </div>
                                        <div class="col-md-4 mb-2">
                                            <label class="form-label">Nota / Especificación (Opcional)</label>
                                            <input type="text" id="nuevo-req-obs-item" class="form-control" maxlength="255" placeholder="Ej: Urgente para lote de producción">
                                        </div>
                                        <div class="col-md-1 mb-2 d-flex align-items-end" style="position: relative; top: 22px;">
                                            <button type="button" class="btn btn-success" id="btn-agregar-insumo-req" title="Agregar Insumo">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div id="mensaje-sin-insumos-req" class="alert alert-info">
                                    No hay insumos en la requisición. Seleccione al menos un insumo y agréguelo al listado.
                                </div>

                                <div id="tabla-insumos-req-wrap" style="display: none;">
                                    <table class="recipe-table">
                                        <thead>
                                            <tr>
                                                <th>Insumo</th>
                                                <th>Proveedor Habitual</th>
                                                <th>Unidad</th>
                                                <th style="width: 160px;">Cantidad</th>
                                                <th>Nota / Especificación</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-insumos-req"></tbody>
                                    </table>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12" style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary" id="btn-guardar-requisicion" disabled>
                                        <i class="fas fa-save"></i> <span id="texto-btn-guardar-req">Guardar Requisición</span>
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" onclick="mostrarVista('listado')">
                                        <i class="fas fa-times"></i> Cancelar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL DETALLE DE REQUISICIÓN -->
    <div class="modal fade" id="modalDetalleRequisicion" tabindex="-1" role="dialog" aria-labelledby="modalDetalleRequisicionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h5 class="modal-title" id="modalDetalleRequisicionLabel">Detalle de Requisición</h5>
                </div>
                <div class="modal-body" id="modalDetalleRequisicionBody"></div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

<script>
var insumosReqAgregados = [];

function mostrarVista(vista) {
    $('#vista-listado, #vista-crear').addClass('hidden').hide();
    $('#vista-' + vista).removeClass('hidden').fadeIn(250);
}

function cargarListado(page) {
    var params = {
        action: 'listar_html',
        buscar_texto: $('#filtro-texto').val(),
        estado: $('#filtro-estado').val(),
        fecha_desde: $('#filtro-desde').val(),
        fecha_hasta: $('#filtro-hasta').val()
    };

    crudPostListadoPaginado(
        'requisiciones_data.php',
        params,
        '#vista-listado tbody',
        '#paginacion-requisiciones',
        page || 1
    );
}

function limpiarFormularioReq() {
    $('#form-requisicion')[0].reset();
    $('#req_id').val('');
    $('#req_fecha_solicitud').val('<?php echo date('Y-m-d'); ?>');
    $('#titulo-form-requisicion').text('Nueva Requisición de Compra');
    $('#texto-btn-guardar-req').text('Guardar Requisición');
    insumosReqAgregados = [];
    actualizarTablaInsumosReq();
    limpiarCamposInsumoReq();
}

function limpiarCamposInsumoReq() {
    $('#nuevo-req-insumo-id').val('');
    $('#nuevo-req-cantidad').val('').attr('step', '0.01').attr('min', '0.01');
    $('#nuevo-req-obs-item').val('');
    $('#info-stock-req-insumo').hide();
}

function agregarInsumoReq() {
    var $opt = $('#nuevo-req-insumo-id option:selected');
    var insumoId = $('#nuevo-req-insumo-id').val();
    var cantidad = parseFloat($('#nuevo-req-cantidad').val()) || 0;
    var obsItem = ($('#nuevo-req-obs-item').val() || '').trim();

    if (!insumoId || cantidad <= 0) {
        Swal.fire({ icon: 'warning', text: 'Por favor seleccione un insumo e ingrese una cantidad mayor a cero.' });
        return;
    }

    var permiteDecimal = parseInt($opt.data('decimal'), 10) === 1;
    if (!permiteDecimal && Math.abs(cantidad - Math.round(cantidad)) > 1e-9) {
        Swal.fire({ icon: 'warning', text: 'La unidad de medida de este insumo solo admite cantidades enteras.' });
        return;
    }

    var idxExistente = insumosReqAgregados.findIndex(function(item) {
        return String(item.insumo_id) === String(insumoId);
    });

    if (idxExistente >= 0) {
        insumosReqAgregados[idxExistente].cantidad = parseFloat((insumosReqAgregados[idxExistente].cantidad + cantidad).toFixed(2));
        if (obsItem !== '') {
            insumosReqAgregados[idxExistente].observaciones = obsItem;
        }
    } else {
        insumosReqAgregados.push({
            insumo_id: parseInt(insumoId, 10),
            insumo_nombre: $opt.data('nombre'),
            unidad_medida: $opt.data('unidad'),
            proveedor_nombre: $opt.data('proveedor') || '—',
            permite_decimal: permiteDecimal ? 1 : 0,
            cantidad: cantidad,
            observaciones: obsItem
        });
    }

    actualizarTablaInsumosReq();
    limpiarCamposInsumoReq();
}

function eliminarInsumoReq(index) {
    insumosReqAgregados.splice(index, 1);
    actualizarTablaInsumosReq();
}

function cambiarCantidadInsumoReq(index, valor) {
    var cant = parseFloat(valor) || 0;
    if (cant <= 0) {
        Swal.fire({ icon: 'warning', text: 'La cantidad debe ser mayor a 0.' });
        actualizarTablaInsumosReq();
        return;
    }
    var item = insumosReqAgregados[index];
    if (parseInt(item.permite_decimal, 10) === 0 && Math.abs(cant - Math.round(cant)) > 1e-9) {
        Swal.fire({ icon: 'warning', text: 'Este insumo solo permite cantidades enteras.' });
        actualizarTablaInsumosReq();
        return;
    }
    insumosReqAgregados[index].cantidad = cant;
}

function cambiarObsInsumoReq(index, valor) {
    insumosReqAgregados[index].observaciones = (valor || '').trim();
}

function actualizarTablaInsumosReq() {
    var $tbody = $('#tbody-insumos-req');
    $tbody.empty();

    insumosReqAgregados.forEach(function(item, idx) {
        var stepVal = parseInt(item.permite_decimal, 10) === 0 ? '1' : '0.01';
        var nombreEsc = $('<div>').text(item.insumo_nombre || '').html();
        var provEsc = $('<div>').text(item.proveedor_nombre || '—').html();
        var umEsc = $('<div>').text(item.unidad_medida || '').html();
        var obsEsc = $('<div>').text(item.observaciones || '').html();

        var tr = `
            <tr>
                <td style="text-align: left;"><strong>${nombreEsc}</strong></td>
                <td>${provEsc}</td>
                <td>${umEsc}</td>
                <td>
                    <input type="number" class="form-control form-control-sm"
                           step="${stepVal}" min="${stepVal}" value="${item.cantidad}"
                           onchange="cambiarCantidadInsumoReq(${idx}, this.value)">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm"
                           maxlength="255" value="${obsEsc}" placeholder="Opcional..."
                           onchange="cambiarObsInsumoReq(${idx}, this.value)">
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarInsumoReq(${idx})">
                        <i class="fa fa-trash"></i> Eliminar
                    </button>
                </td>
            </tr>
        `;
        $tbody.append(tr);
    });

    if (insumosReqAgregados.length > 0) {
        $('#tabla-insumos-req-wrap').show();
        $('#mensaje-sin-insumos-req').hide();
        $('#btn-guardar-requisicion').prop('disabled', false);
    } else {
        $('#tabla-insumos-req-wrap').hide();
        $('#mensaje-sin-insumos-req').show();
        $('#btn-guardar-requisicion').prop('disabled', true);
    }
}

function verDetalleRequisicion(id) {
    $('#modalDetalleRequisicionBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#modalDetalleRequisicion').modal('show');

    $.post('requisiciones_data.php', { action: 'obtener_detalle', requisicion_id: id }, function(resp) {
        if (!resp || !resp.success) {
            $('#modalDetalleRequisicionBody').html('<div class="alert alert-danger">No se pudo cargar el detalle.</div>');
            return;
        }
        var r = resp.requisicion;
        var html = '<div class="row mb-2">';
        html += '<div class="col-md-6"><strong>Código:</strong> ' + $('<div>').text(r.codigo_requisicion).html() + '</div>';
        html += '<div class="col-md-6"><strong>Estado:</strong> ' + r.estado_badge + '</div>';
        html += '</div>';
        html += '<div class="row mb-2">';
        html += '<div class="col-md-6"><strong>Fecha Solicitud:</strong> ' + $('<div>').text(r.fecha_formateada).html() + '</div>';
        html += '<div class="col-md-6"><strong>Solicitante:</strong> ' + $('<div>').text(r.solicitante_nombre).html() + '</div>';
        html += '</div>';
        html += '<div class="row mb-2">';
        html += '<div class="col-md-12"><strong>Justificación:</strong><div class="p-2 bg-light border rounded mt-1">' + $('<div>').text(r.justificacion || '—').html() + '</div></div>';
        html += '</div>';

        if (r.observaciones) {
            html += '<div class="row mb-2"><div class="col-md-12"><strong>Observaciones:</strong> ' + $('<div>').text(r.observaciones).html() + '</div></div>';
        }

        if (r.aprobador_nombre || r.fecha_aprobacion_fmt) {
            html += '<div class="row mb-2">';
            html += '<div class="col-md-6"><strong>Revisado por:</strong> ' + $('<div>').text(r.aprobador_nombre || '—').html() + '</div>';
            html += '<div class="col-md-6"><strong>Fecha Revisión:</strong> ' + $('<div>').text(r.fecha_aprobacion_fmt || '—').html() + '</div>';
            html += '</div>';
        }

        if (parseInt(r.estado, 10) === 3 && r.motivo_rechazo) {
            html += '<div class="alert alert-danger mt-2 mb-2"><strong>Motivo de Rechazo:</strong> ' + $('<div>').text(r.motivo_rechazo).html() + '</div>';
        }

        html += '<hr><h6><strong>Insumos Solicitados:</strong></h6>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead class="thead-light"><tr><th>Insumo</th><th>Proveedor Habitual</th><th>Cant. Solicitada</th><th>Cant. en O.C.</th><th>Pendiente</th><th>Nota</th></tr></thead><tbody>';

        resp.detalles.forEach(function(d) {
            html += '<tr>';
            html += '<td style="text-align:left;">' + $('<div>').text(d.insumo_nombre + ' (' + d.unidad_medida + ')').html() + '</td>';
            html += '<td>' + $('<div>').text(d.proveedor_nombre || '—').html() + '</td>';
            html += '<td><strong>' + parseFloat(d.cantidad).toFixed(2) + '</strong></td>';
            html += '<td>' + parseFloat(d.cantidad_pedida || 0).toFixed(2) + '</td>';
            html += '<td>' + parseFloat(d.cantidad_pendiente || 0).toFixed(2) + '</td>';
            html += '<td>' + $('<div>').text(d.observaciones || '—').html() + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';

        if (resp.ordenes_asociadas && resp.ordenes_asociadas.length > 0) {
            html += '<hr><h6><strong>Órdenes de Compra Generadas desde esta Requisición:</strong></h6><ul>';
            resp.ordenes_asociadas.forEach(function(oc) {
                html += '<li><strong>' + $('<div>').text(oc.codigo_oc).html() + '</strong> — Proveedor: ' + $('<div>').text(oc.proveedor_nombre).html() + ' — Estado: <strong>' + $('<div>').text(oc.estado).html() + '</strong> ($' + parseFloat(oc.total).toFixed(2) + ')</li>';
            });
            html += '</ul>';
        }

        $('#modalDetalleRequisicionBody').html(html);
    }, 'json').fail(function() {
        $('#modalDetalleRequisicionBody').html('<div class="alert alert-danger">Error de conexión al cargar los detalles.</div>');
    });
}

function editarRequisicion(id) {
    $.post('requisiciones_data.php', { action: 'obtener_detalle', requisicion_id: id }, function(resp) {
        if (!resp || !resp.success) {
            Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'No se pudo cargar la requisición.' });
            return;
        }
        var r = resp.requisicion;
        if (parseInt(r.estado, 10) !== 1 || parseInt(r.estado_orden || 0, 10) !== 0) {
            Swal.fire({ icon: 'warning', text: 'Solo se permite editar una requisición si su estado es PENDIENTE.' });
            return;
        }

        limpiarFormularioReq();
        $('#req_id').val(r.id);
        $('#req_fecha_solicitud').val(r.fecha_input);
        $('#req_justificacion').val(r.justificacion || '');
        $('#req_observaciones').val(r.observaciones || '');
        $('#titulo-form-requisicion').text('Editar Requisición ' + r.codigo_requisicion);
        $('#texto-btn-guardar-req').text('Guardar Cambios');

        insumosReqAgregados = (resp.detalles || []).map(function(d) {
            return {
                insumo_id: parseInt(d.insumo_id, 10),
                insumo_nombre: d.insumo_nombre,
                unidad_medida: d.unidad_medida,
                proveedor_nombre: d.proveedor_nombre,
                permite_decimal: parseInt(d.permite_decimal, 10),
                cantidad: parseFloat(d.cantidad),
                observaciones: d.observaciones || ''
            };
        });

        actualizarTablaInsumosReq();
        mostrarVista('crear');
    }, 'json');
}

function eliminarRequisicion(id, codigo) {
    Swal.fire({
        icon: 'warning',
        title: '¿Eliminar requisición ' + codigo + '?',
        text: 'Esta acción eliminará la requisición pendiente y sus renglones. No se puede deshacer.',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('requisiciones_data.php', { action: 'eliminar', id: id }, function(resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message });
                cargarListado(1);
            } else {
                Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'No se pudo eliminar.' });
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Error al eliminar la requisición.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', text: msg });
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado(1);
    bindCrudPagination('#paginacion-requisiciones', cargarListado);

    $('#btn-ir-crear').on('click', function() {
        limpiarFormularioReq();
        mostrarVista('crear');
    });

    $('#btn-volver-listado').on('click', function() {
        mostrarVista('listado');
    });

    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    $('.clase-filtro-req').on('change keyup', function(e) {
        if (e.type === 'keyup' && e.key !== 'Enter') return;
        cargarListado(1);
    });

    $('#btn-limpiar-filtros-req').on('click', function() {
        $('#filtro-texto').val('');
        $('#filtro-estado').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarListado(1);
    });

    $('#nuevo-req-insumo-id').on('change', function() {
        var $opt = $(this).find('option:selected');
        if (!$(this).val()) {
            $('#info-stock-req-insumo').hide();
            return;
        }
        var stock = parseFloat($opt.data('stock')) || 0;
        var min = $opt.data('minimo');
        var um = $opt.data('unidad') || '';
        var permiteDec = parseInt($opt.data('decimal'), 10) === 1;
        $('#nuevo-req-cantidad').attr('step', permiteDec ? '0.01' : '1').attr('min', permiteDec ? '0.01' : '1');
        var txt = 'Stock actual: ' + stock.toFixed(2) + ' ' + um;
        if (min !== '' && min !== undefined) {
            txt += ' | Stock mínimo: ' + parseFloat(min).toFixed(2) + ' ' + um;
        }
        $('#texto-stock-req-insumo').text(txt);
        $('#info-stock-req-insumo').show();
    });

    $('#btn-agregar-insumo-req').on('click', function() {
        agregarInsumoReq();
    });

    $('#nuevo-req-cantidad, #nuevo-req-obs-item').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            agregarInsumoReq();
        }
    });

    $('#form-requisicion').on('submit', function(e) {
        e.preventDefault();
        if (insumosReqAgregados.length === 0) {
            Swal.fire({ icon: 'warning', text: 'Agregue al menos un insumo a la requisición.' });
            return;
        }

        var payload = {
            action: 'guardar',
            id: $('#req_id').val(),
            fecha_solicitud: $('#req_fecha_solicitud').val(),
            justificacion: $('#req_justificacion').val(),
            observaciones: $('#req_observaciones').val(),
            insumos: insumosReqAgregados
        };

        $.ajax({
            url: 'requisiciones_data.php',
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    mostrarVista('listado');
                    cargarListado(1);
                } else {
                    Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'Error al guardar.' });
                }
            },
            error: function(xhr) {
                var msg = 'Error al guardar la requisición.';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                Swal.fire({ icon: 'error', text: msg });
            }
        });
    });
});
</script>

<?php
$conn->close();
require_once "../template/footer.php";
?>
</body>
</html>
