<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../lib/AbastecimientoService.php";

AbastecimientoService::asegurarTablas($conn);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aprobación de Requisiciones - INVERCLINIK</title>
    <style>
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }
        .kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-left: 5px solid #0056b3;
            border-radius: 8px;
            padding: 14px 18px;
            cursor: pointer;
            transition: transform 0.15s, box-shadow 0.15s;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        .kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .kpi-card.kpi-pendiente { border-left-color: #fd7e14; }
        .kpi-card.kpi-aprobada  { border-left-color: #198754; }
        .kpi-card.kpi-rechazada { border-left-color: #dc3545; }
        .kpi-card.kpi-procesada { border-left-color: #0d6efd; }
        .kpi-card .kpi-label {
            font-size: 12px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
        }
        .kpi-card .kpi-value {
            font-size: 24px;
            font-weight: 800;
            color: #1e293b;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Aprobación de Requisiciones de Compra</h2>
                <p class="text-muted" style="margin-top: -8px; margin-bottom: 18px;">
                    Panel de revisión y autorización de solicitudes internas de insumos.
                </p>

                <!-- TARJETAS RESUMEN -->
                <div class="kpi-grid">
                    <div class="kpi-card kpi-pendiente" onclick="filtrarPorEstadoRapido('1')" title="Filtrar Pendientes">
                        <div class="kpi-label"><i class="fas fa-clock text-warning"></i> Pendientes por Aprobar</div>
                        <div class="kpi-value" id="kpi-pendientes">0</div>
                    </div>
                    <div class="kpi-card kpi-aprobada" onclick="filtrarPorEstadoRapido('2')" title="Filtrar Aprobadas">
                        <div class="kpi-label"><i class="fas fa-check-circle text-success"></i> Aprobadas (Listas O.C.)</div>
                        <div class="kpi-value" id="kpi-aprobadas">0</div>
                    </div>
                    <div class="kpi-card kpi-procesada" onclick="filtrarPorEstadoRapido('todos')" title="Ver Todas">
                        <div class="kpi-label"><i class="fas fa-file-invoice text-primary"></i> Procesadas / Parciales</div>
                        <div class="kpi-value" id="kpi-en-oc">0</div>
                    </div>
                    <div class="kpi-card kpi-rechazada" onclick="filtrarPorEstadoRapido('3')" title="Filtrar Rechazadas">
                        <div class="kpi-label"><i class="fas fa-times-circle text-danger"></i> Rechazadas</div>
                        <div class="kpi-value" id="kpi-rechazadas">0</div>
                    </div>
                </div>

                <div class="row form-group">
                    <div class="col-sm-12">
                        <div class="d-flex align-items-center" style="gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                            <button class="btn btn-outline-secondary" id="btn-toggle-filtros">
                                <i class="fas fa-filter"></i> Filtros de Búsqueda
                            </button>
                            <!-- <a href="requisiciones.php" class="btn btn-outline-primary">
                                <i class="fas fa-clipboard-list"></i> Gestionar Requisiciones
                            </a>
                            <a href="ordenes_compra.php" class="btn btn-success">
                                <i class="fas fa-file-invoice-dollar"></i> Ir a Órdenes de Compra
                            </a> -->
                        </div>
                    </div>
                </div>

                <!-- PANEL DE FILTROS -->
                <div id="panel-filtros" style="display: block; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.08); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                    <div class="row">
                        <div class="col-sm-3">
                            <label for="filtro-estado">Estado de Requisición</label>
                            <select id="filtro-estado" class="form-control clase-filtro-aprob">
                                <option value="1" selected>1 - PENDIENTES POR REVISAR</option>
                                <option value="todos">Todos los estados</option>
                                <option value="2">2 - APROBADAS</option>
                                <option value="3">3 - RECHAZADAS</option>
                                <option value="4">PROCESADAS (O.C. Completa)</option>
                                <option value="5">PARCIALES (O.C. Parcial)</option>
                            </select>
                        </div>
                        <div class="col-sm-3">
                            <label for="filtro-texto">Buscar</label>
                            <input type="text" id="filtro-texto" class="form-control clase-filtro-aprob" placeholder="Código, solicitante o motivo...">
                        </div>
                        <div class="col-sm-2">
                            <label for="filtro-desde">Desde</label>
                            <input type="date" id="filtro-desde" class="form-control clase-filtro-aprob">
                        </div>
                        <div class="col-sm-2">
                            <label for="filtro-hasta">Hasta</label>
                            <input type="date" id="filtro-hasta" class="form-control clase-filtro-aprob">
                        </div>
                        <div class="col-sm-2" style="margin-top: 25px;">
                            <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros">
                                <i class="fas fa-eraser"></i> Limpiar
                            </button>
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
                                <th>Renglones</th>
                                <th>Costo Est. ($)</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-aprobaciones"></tbody>
                    </table>
                </div>
                <div id="paginacion-aprobaciones"></div>
            </div>
        </div>
    </div>

    <!-- MODAL DE REVISIÓN DETALLADA -->
    <div class="modal fade" id="modalRevisionRequisicion" tabindex="-1" role="dialog" aria-labelledby="modalRevisionLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h5 class="modal-title" id="modalRevisionLabel">Revisión de Requisición</h5>
                </div>
                <div class="modal-body" id="modalRevisionBody"></div>
                <div class="modal-footer" id="modalRevisionFooter">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

<script>
function cargarResumen() {
    $.post('aprobacion_requisiciones_data.php', { action: 'obtener_resumen' }, function(resp) {
        if (resp && resp.success) {
            $('#kpi-pendientes').text(resp.pendientes || 0);
            $('#kpi-aprobadas').text(resp.aprobadas || 0);
            $('#kpi-rechazadas').text(resp.rechazadas || 0);
            $('#kpi-en-oc').text(resp.en_orden_compra || 0);
        }
    }, 'json');
}

function cargarListado(page) {
    cargarResumen();
    var params = {
        action: 'listar_html',
        estado: $('#filtro-estado').val(),
        buscar_texto: $('#filtro-texto').val(),
        fecha_desde: $('#filtro-desde').val(),
        fecha_hasta: $('#filtro-hasta').val()
    };

    crudPostListadoPaginado(
        'aprobacion_requisiciones_data.php',
        params,
        '#tbody-aprobaciones',
        '#paginacion-aprobaciones',
        page || 1
    );
}

function filtrarPorEstadoRapido(estadoVal) {
    $('#filtro-estado').val(estadoVal);
    cargarListado(1);
}

function abrirModalRevision(idReq) {
    $('#modalRevisionBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#modalRevisionFooter').html('<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>');
    $('#modalRevisionRequisicion').modal('show');

    $.post('requisiciones_data.php', { action: 'obtener_detalle', requisicion_id: idReq }, function(resp) {
        if (!resp || !resp.success) {
            $('#modalRevisionBody').html('<div class="alert alert-danger">Error al cargar la requisición.</div>');
            return;
        }
        var r = resp.requisicion;
        var codEsc = $('<div>').text(r.codigo_requisicion).html();
        $('#modalRevisionLabel').html('Revisión de Requisición <strong>' + codEsc + '</strong>');

        var html = '<div class="row mb-2">';
        html += '<div class="col-md-4"><strong>Código:</strong> ' + codEsc + '</div>';
        html += '<div class="col-md-4"><strong>Fecha:</strong> ' + $('<div>').text(r.fecha_formateada).html() + '</div>';
        html += '<div class="col-md-4"><strong>Estado:</strong> ' + r.estado_badge + '</div>';
        html += '</div>';

        html += '<div class="row mb-3">';
        html += '<div class="col-md-6"><strong>Solicitante:</strong> ' + $('<div>').text(r.solicitante_nombre).html() + '</div>';
        if (r.aprobador_nombre) {
            html += '<div class="col-md-6"><strong>Revisado por:</strong> ' + $('<div>').text(r.aprobador_nombre + ' (' + (r.fecha_aprobacion_fmt || '') + ')').html() + '</div>';
        }
        html += '</div>';

        html += '<div class="p-3 mb-3 rounded" style="background-color: #f8fafc; border: 1px solid #cbd5e1;">';
        html += '<div style="font-weight: bold; color: #0056b3; margin-bottom: 4px;"><i class="fas fa-comment-alt"></i> Justificación del Solicitante:</div>';
        html += '<div>' + $('<div>').text(r.justificacion || 'Sin justificación especificada').html() + '</div>';
        if (r.observaciones) {
            html += '<div class="mt-2 small text-muted"><strong>Observaciones:</strong> ' + $('<div>').text(r.observaciones).html() + '</div>';
        }
        html += '</div>';

        if (parseInt(r.estado, 10) === 3 && r.motivo_rechazo) {
            html += '<div class="alert alert-danger"><strong>Motivo de Rechazo:</strong> ' + $('<div>').text(r.motivo_rechazo).html() + '</div>';
        }

        html += '<h6><strong>Detalle de Insumos Solicitados:</strong></h6>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead class="thead-light"><tr><th>Insumo</th><th>Proveedor Sugerido</th><th>Stock Actual</th><th>Cant. Solicitada</th><th>Costo Ref.</th><th>Subtotal Est.</th><th>Nota</th></tr></thead><tbody>';

        var totalEst = 0;
        (resp.detalles || []).forEach(function(d) {
            var cant = parseFloat(d.cantidad) || 0;
            var costo = parseFloat(d.costo_unitario) || 0;
            var sub = cant * costo;
            totalEst += sub;
            var stockAct = parseFloat(d.stock_actual) || 0;
            var stockMin = d.stock_minimo !== null ? parseFloat(d.stock_minimo) : null;
            var alertaStock = (stockMin !== null && stockAct <= stockMin)
                ? ' <span class="badge badge-danger" title="En o bajo el stock mínimo">Bajo Stock</span>'
                : '';

            html += '<tr>';
            html += '<td style="text-align:left;"><strong>' + $('<div>').text(d.insumo_nombre).html() + '</strong> (' + $('<div>').text(d.unidad_medida).html() + ')</td>';
            html += '<td>' + $('<div>').text(d.proveedor_nombre || '—').html() + '</td>';
            html += '<td>' + stockAct.toFixed(2) + alertaStock + '</td>';
            html += '<td style="font-weight:bold; color:#0056b3;">' + cant.toFixed(2) + ' ' + $('<div>').text(d.unidad_medida).html() + '</td>';
            html += '<td>$' + costo.toFixed(2) + '</td>';
            html += '<td>$' + sub.toFixed(2) + '</td>';
            html += '<td>' + $('<div>').text(d.observaciones || '—').html() + '</td>';
            html += '</tr>';
        });

        html += '</tbody>';
        html += '<tfoot><tr style="background:#f8f9fa; font-weight:bold;"><td colspan="5" style="text-align:right;">Total Estimado Referencial:</td><td colspan="2" style="color:#0056b3;">$' + totalEst.toFixed(2) + '</td></tr></tfoot>';
        html += '</table></div>';

        $('#modalRevisionBody').html(html);

        var footerHtml = '';
        <?php if (!esLector()): ?>
        if (parseInt(r.estado, 10) === 1) {
            var codAttr = String(r.codigo_requisicion).replace(/'/g, "\\'");
            footerHtml += '<button type="button" class="btn btn-success" onclick="aprobarRequisicion(' + r.id + ', \'' + codAttr + '\', true)"><i class="fas fa-check-circle"></i> Aprobar Requisición</button> ';
            footerHtml += '<button type="button" class="btn btn-danger" onclick="rechazarRequisicion(' + r.id + ', \'' + codAttr + '\', true)"><i class="fas fa-times-circle"></i> Rechazar Requisición</button> ';
        } else if (parseInt(r.estado, 10) === 2 || parseInt(r.estado, 10) === 5) {
            footerHtml += '<a href="ordenes_compra.php?req_id=' + r.id + '" class="btn btn-info"><i class="fas fa-file-invoice-dollar"></i> Generar Orden de Compra</a> ';
        }
        <?php endif; ?>
        footerHtml += '<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>';
        $('#modalRevisionFooter').html(footerHtml);
    }, 'json');
}

function aprobarRequisicion(idReq, codigo, desdeModal) {
    Swal.fire({
        icon: 'question',
        title: '¿Aprobar Requisición ' + codigo + '?',
        text: 'El estado cambiará a 2 = APROBADA y quedará habilitada para generar Órdenes de Compra.',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Sí, Aprobar',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;

        $.post('aprobacion_requisiciones_data.php', {
            action: 'aprobar',
            requisicion_id: idReq
        }, function(resp) {
            if (resp && resp.success) {
                if (desdeModal) {
                    $('#modalRevisionRequisicion').modal('hide');
                }
                Swal.fire({ icon: 'success', text: resp.message });
                cargarListado(1);
            } else {
                Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'No se pudo aprobar.' });
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Error al aprobar la requisición.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', text: msg });
        });
    });
}

function rechazarRequisicion(idReq, codigo, desdeModal) {
    if (desdeModal) {
        $('#modalRevisionRequisicion').modal('hide');
    }
    Swal.fire({
        icon: 'warning',
        title: 'Rechazar Requisición ' + codigo,
        input: 'textarea',
        inputLabel: 'Motivo del rechazo (opcional)',
        inputPlaceholder: 'Indique la razón por la cual se rechaza esta solicitud...',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Confirmar Rechazo',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;

        $.post('aprobacion_requisiciones_data.php', {
            action: 'rechazar',
            requisicion_id: idReq,
            motivo_rechazo: r.value || ''
        }, function(resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message });
                cargarListado(1);
            } else {
                Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'No se pudo rechazar.' });
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Error al rechazar la requisición.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', text: msg });
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    cargarListado(1);
    bindCrudPagination('#paginacion-aprobaciones', cargarListado);

    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    $('.clase-filtro-aprob').on('change keyup', function(e) {
        if (e.type === 'keyup' && e.key !== 'Enter') return;
        cargarListado(1);
    });

    $('#btn-limpiar-filtros').on('click', function() {
        $('#filtro-estado').val('1');
        $('#filtro-texto').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarListado(1);
    });
});
</script>

<?php
$conn->close();
require_once "../template/footer.php";
?>
</body>
</html>
