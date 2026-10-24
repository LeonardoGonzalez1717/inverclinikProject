<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../lib/AbastecimientoService.php";

AbastecimientoService::asegurarTablas($conn);

$proveedores = [];
$resProv = $conn->query("SELECT id, nombre, cedrif FROM proveedores ORDER BY nombre ASC");
if ($resProv) {
    while ($row = $resProv->fetch_assoc()) {
        $proveedores[] = $row;
    }
}

$tasa_actual = null;
$rt = $conn->query("SELECT tasa FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
if ($rt && ($row_tasa = $rt->fetch_assoc())) {
    $tasa_actual = (float) $row_tasa['tasa'];
}

$req_id_preseleccionada = isset($_GET['req_id']) ? (int) $_GET['req_id'] : 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Órdenes de Compra - INVERCLINIK</title>
    <style>
        .btn-volver {
            background: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-bottom: 15px;
        }
        .btn-volver:hover {
            background: #5a6268;
            color: white;
        }
        .modalidad-selector {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .modalidad-card {
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            padding: 14px 18px;
            cursor: pointer;
            background: #ffffff;
            transition: all 0.2s;
        }
        .modalidad-card.active {
            border-color: #0056b3;
            background: #eff6ff;
            box-shadow: 0 2px 8px rgba(0, 86, 179, 0.15);
        }
        .modalidad-card h5 {
            margin: 0 0 5px 0;
            font-weight: 700;
            color: #1e293b;
        }
        .modalidad-card.active h5 {
            color: #0056b3;
        }
        .req-selector-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
        }
        .req-list-scroll {
            max-height: 240px;
            overflow-y: auto;
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Órdenes de Compra (O.C.)</h2>
                <p class="text-muted" style="margin-top: -8px; margin-bottom: 18px;">
                    Generación de Órdenes de Compra desde una o más Requisiciones Aprobadas o de forma directa.
                </p>

                <div id="contenedor-vistas">
                    <!-- VISTA LISTADO -->
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div class="d-flex align-items-center" style="gap: 10px; margin-bottom: 12px; flex-wrap: wrap;">
                                    <?php if (!esLector()): ?>
                                    <button class="btn btn-success" id="btn-ir-crear">
                                        <i class="fas fa-plus-circle"></i> Nueva Orden de Compra
                                    </button>
                                    <?php endif; ?>
                                    <button class="btn btn-outline-secondary" id="btn-toggle-filtros">
                                        <i class="fas fa-filter"></i> Filtros
                                    </button>
                                    <!-- <a href="requisiciones.php" class="btn btn-outline-primary">
                                        <i class="fas fa-clipboard-list"></i> Requisiciones
                                    </a>
                                    <a href="registrar_compra.php" class="btn btn-outline-info">
                                        <i class="fas fa-cart-plus"></i> Registrar Compra
                                    </a> -->
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.08); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-sm-3">
                                    <label for="filtro-codigo">Código O.C.</label>
                                    <input type="text" id="filtro-codigo" class="form-control clase-filtro-oc" placeholder="Ej: OC-0001">
                                </div>
                                <div class="col-sm-3">
                                    <label for="filtro-proveedor">Proveedor</label>
                                    <input type="text" id="filtro-proveedor" class="form-control clase-filtro-oc" placeholder="Nombre o RIF...">
                                </div>
                                <div class="col-sm-3">
                                    <label for="filtro-modalidad">Modalidad</label>
                                    <select id="filtro-modalidad" class="form-control clase-filtro-oc">
                                        <option value="">Todas</option>
                                        <option value="requisicion">Desde Requisición</option>
                                        <option value="directa">Directa (Desde Cero)</option>
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label for="filtro-estado">Estado O.C.</label>
                                    <select id="filtro-estado" class="form-control clase-filtro-oc">
                                        <option value="">Todos</option>
                                        <option value="EMITIDA">EMITIDA</option>
                                        <option value="COMPLETADA">COMPLETADA</option>
                                        <option value="CANCELADA">CANCELADA</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <label for="filtro-desde">Fecha Desde</label>
                                    <input type="date" id="filtro-desde" class="form-control clase-filtro-oc">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-hasta">Fecha Hasta</label>
                                    <input type="date" id="filtro-hasta" class="form-control clase-filtro-oc">
                                </div>
                                <div class="col-sm-4" style="margin-top: 25px;">
                                    <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros-oc">
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
                                        <th>Código O.C.</th>
                                        <th>Proveedor</th>
                                        <th>Fecha</th>
                                        <th>Modalidad</th>
                                        <th>Ítems (Cant.)</th>
                                        <th>Total ($)</th>
                                        <th>Total Bs.</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="paginacion-oc"></div>
                    </div>

                    <!-- VISTA CREAR ORDEN DE COMPRA -->
                    <div id="vista-crear" class="hidden">
                        <button type="button" class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>

                        <h4 style="color: #0056b3; margin-bottom: 15px; font-weight: bold;">Emitir Nueva Orden de Compra</h4>

                        <form id="form-orden-compra">
                            <!-- SELECTOR DE MODALIDAD -->
                            <label class="form-label" style="font-weight: bold;">Seleccione la Modalidad de Creación <span style="color: red;">*</span></label>
                            <div class="modalidad-selector">
                                <div class="modalidad-card active" id="card-mod-requisicion" onclick="seleccionarModalidadOC('requisicion')">
                                    <h5>Desde Requisición Aprobada</h5>
                                    <small class="text-muted">Seleccione una o más requisiciones aprobadas para cargar sus insumos pendientes automáticamente.</small>
                                </div>
                                <div class="modalidad-card" id="card-mod-directa" onclick="seleccionarModalidadOC('directa')">
                                    <h5>Nueva</h5>
                                    <small class="text-muted">Cree una Orden de Compra directa seleccionando el proveedor y agregando insumos manualmente.</small>
                                </div>
                            </div>
                            <input type="hidden" id="oc_modalidad" name="modalidad" value="requisicion">

                            <div class="row mb-3">
                                <div class="col-md-5">
                                    <label class="form-label">Proveedor <span style="color: red;">*</span></label>
                                    <select id="oc_proveedor_id" name="proveedor_id" class="form-control" required>
                                        <option value="">-- Seleccione un proveedor --</option>
                                        <?php foreach ($proveedores as $p): ?>
                                            <option value="<?php echo (int) $p['id']; ?>">
                                                <?php echo htmlspecialchars($p['nombre'] . ' (' . $p['cedrif'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Fecha de Emisión <span style="color: red;">*</span></label>
                                    <input type="date" id="oc_fecha" name="fecha" class="form-control" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Observaciones / Condiciones</label>
                                    <input type="text" id="oc_observaciones" name="observaciones" class="form-control" placeholder="Plazo de entrega, notas al proveedor...">
                                </div>
                            </div>

                            <!-- PANEL DE SELECCIÓN DE REQUISICIONES APROBADAS (MODALIDAD A) -->
                            <div id="panel-seleccion-requisiciones" class="req-selector-box">
                                <div class="d-flex justify-content-between align-items-center mb-2" style="flex-wrap: wrap; gap: 10px;">
                                    <h5 style="color: #0056b3; margin: 0;">
                                         Requisiciones Aprobadas / Parciales Disponibles
                                    </h5>
                                    <br>
                                    <div class="d-flex align-items-center" style="gap: 12px;">
                                        <label style="margin: 0; cursor: pointer; font-size: 13px;">
                                            <input type="checkbox" id="chk-filtrar-insumos-prov" checked>
                                            Cargar solo insumos del proveedor seleccionado (si se eligió proveedor)
                                        </label>
                                        <button type="button" class="btn btn-sm btn-primary" id="btn-cargar-desde-req">
                                            <i class="fas fa-download"></i> Cargar Insumos de Seleccionadas
                                        </button>
                                    </div>
                                </div>
                                <small class="text-muted d-block mb-2">
                                    Marque una o más requisiciones aprobadas. Si ordena solo parte de los insumos o cantidades de una requisición, su estado pasará a <strong>PARCIAL</strong>; si cubre todos sus insumos, pasará a <strong>PROCESADA</strong>.
                                </small>
                                <div class="req-list-scroll">
                                    <table class="table table-sm table-hover mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th style="width: 40px; text-align: center;">Sel.</th>
                                                <th>Código</th>
                                                <th>Fecha</th>
                                                <th>Solicitante</th>
                                                <th>Justificación</th>
                                                <th>Proveedores en Req.</th>
                                                <th>Ítems Pendientes</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-requisiciones-disponibles">
                                            <tr><td colspan="8" class="text-center text-muted">Cargando requisiciones aprobadas...</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <hr style="margin: 20px 0; border-color: #dee2e6;">

                            <!-- AGREGAR INSUMOS ADICIONALES O MANUALES -->
                            <div class="mb-3">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 style="color: #0056b3; margin: 0;">Detalle de la Orden de Compra</h5>
                                    <br>
                                    <label style="font-size: 13px; cursor: pointer; margin: 0;">
                                        <input type="checkbox" id="chk-mostrar-todos-insumos">
                                        Mostrar todo el catálogo de insumos al agregar manualmente
                                    </label>
                                </div>

                                <div class="card" style="padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">
                                    <div class="row">
                                        <div class="col-md-5 mb-2">
                                            <label class="form-label">Agregar Insumo (Manual / Adicional)</label>
                                            <select id="oc-nuevo-insumo-id" class="form-control">
                                                <option value="">Seleccione un proveedor o marque mostrar todos </option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Cantidad</label>
                                            <input type="number" step="0.01" min="0.01" id="oc-nuevo-cantidad" class="form-control" placeholder="0.00">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Costo Unitario ($)</label>
                                            <input type="number" step="0.01" min="0.01" id="oc-nuevo-costo" class="form-control" placeholder="0.00">
                                        </div>
                                        <div class="col-md-2 mb-2">
                                            <label class="form-label">Subtotal</label>
                                            <input type="text" id="oc-nuevo-subtotal" class="form-control" readonly placeholder="$0.00">
                                        </div>
                                        <div class="col-md-1 mb-2 d-flex align-items-end" style="position: relative; top: 22px;">
                                            <button type="button" class="btn btn-success" id="btn-oc-agregar-insumo" title="Agregar a la O.C.">
                                                <i class="fa fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <div id="mensaje-sin-insumos-oc" class="alert alert-info">
                                    No hay insumos en la Orden de Compra. Puede cargarlos desde una Requisición Aprobada o agregarlos manualmente.
                                </div>

                                <div id="tabla-insumos-oc-wrap" style="display: none;">
                                    <table class="recipe-table">
                                        <thead>
                                            <tr>
                                                <th>Insumo</th>
                                                <th>Origen</th>
                                                <th style="width: 150px;">Cantidad</th>
                                                <th style="width: 160px;">Costo Unitario ($)</th>
                                                <th>Subtotal ($)</th>
                                                <th>Equiv. Bs.</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-insumos-oc"></tbody>
                                        <tfoot>
                                            <tr style="background-color: #f8f9fa; font-weight: bold;">
                                                <td colspan="4" style="text-align: right;">Total Orden de Compra:</td>
                                                <td id="total-oc-usd" style="color: #0056b3; font-size: 1.1em;">$0.00</td>
                                                <td id="total-oc-bs" style="color: #0056b3; font-size: 1.1em;">—</td>
                                                <td></td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                    <small class="text-muted" id="texto-tasa-informativa-oc"></small>
                                </div>
                            </div>

                            <div class="row mb-3">
                                <div class="col-md-12" style="display: flex; gap: 10px; margin-top: 15px;">
                                    <button type="submit" class="btn btn-primary" id="btn-guardar-oc" disabled>
                                        <i class="fas fa-save"></i> Emitir Orden de Compra
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

    <!-- MODAL DETALLE DE ORDEN DE COMPRA -->
    <div class="modal fade" id="modalDetalleOC" tabindex="-1" role="dialog" aria-labelledby="modalDetalleOCLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h5 class="modal-title" id="modalDetalleOCLabel">Detalle de Orden de Compra</h5>
                </div>
                <div class="modal-body" id="modalDetalleOCBody"></div>
                <div class="modal-footer" id="modalDetalleOCFooter">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

<script>
var insumosOC = [];
var tasaCambiariaActual = <?php echo $tasa_actual !== null ? json_encode($tasa_actual) : 'null'; ?>;
var reqPreseleccionada = <?php echo (int) $req_id_preseleccionada; ?>;

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
        buscar_codigo: $('#filtro-codigo').val(),
        buscar_proveedor: $('#filtro-proveedor').val(),
        modalidad: $('#filtro-modalidad').val(),
        estado: $('#filtro-estado').val(),
        fecha_desde: $('#filtro-desde').val(),
        fecha_hasta: $('#filtro-hasta').val()
    };

    crudPostListadoPaginado(
        'ordenes_compra_data.php',
        params,
        '#vista-listado tbody',
        '#paginacion-oc',
        page || 1
    );
}

function seleccionarModalidadOC(modalidad) {
    $('#oc_modalidad').val(modalidad);
    $('.modalidad-card').removeClass('active');
    if (modalidad === 'requisicion') {
        $('#card-mod-requisicion').addClass('active');
        $('#panel-seleccion-requisiciones').slideDown(200);
        cargarRequisicionesDisponibles();
    } else {
        $('#card-mod-directa').addClass('active');
        $('#panel-seleccion-requisiciones').slideUp(200);
        // Quitar ítems que venían de requisición si cambia a directa
        insumosOC = insumosOC.filter(function(i) { return !i.requisicion_id; });
        actualizarTablaInsumosOC();
    }
}

function cargarRequisicionesDisponibles(callback) {
    var provId = $('#oc_proveedor_id').val() || 0;
    var seleccionadosActuales = [];
    $('.chk-req-oc:checked').each(function() {
        seleccionadosActuales.push(parseInt($(this).val(), 10));
    });
    if (reqPreseleccionada > 0 && seleccionadosActuales.indexOf(reqPreseleccionada) === -1) {
        seleccionadosActuales.push(reqPreseleccionada);
    }

    $.post('ordenes_compra_data.php', {
        action: 'listar_requisiciones_aprobadas',
        proveedor_id: provId
    }, function(resp) {
        var $tb = $('#tbody-requisiciones-disponibles');
        $tb.empty();
        if (!resp || !resp.success || !resp.requisiciones || resp.requisiciones.length === 0) {
            $tb.html('<tr><td colspan="8" class="text-center text-muted">No hay requisiciones aprobadas con insumos pendientes.</td></tr>');
            if (typeof callback === 'function') callback();
            return;
        }

        resp.requisiciones.forEach(function(r) {
            var idReq = parseInt(r.id, 10);
            var checked = seleccionadosActuales.indexOf(idReq) >= 0 ? 'checked' : '';
            var just = r.justificacion || '—';
            if (just.length > 50) just = just.substring(0, 50) + '...';

            var tr = `
                <tr>
                    <td style="text-align: center;">
                        <input type="checkbox" class="chk-req-oc" value="${idReq}" ${checked} style="width:17px; height:17px; cursor:pointer;">
                    </td>
                    <td><strong>${$('<div>').text(r.codigo_requisicion).html()}</strong></td>
                    <td>${$('<div>').text(r.fecha_formateada).html()}</td>
                    <td>${$('<div>').text(r.solicitante_nombre).html()}</td>
                    <td>${$('<div>').text(just).html()}</td>
                    <td><small>${$('<div>').text(r.proveedores_involucrados || '—').html()}</small></td>
                    <td class="text-center"><strong>${r.items_pendientes}</strong> ítem(s)</td>
                    <td><span style="${r.estado_style}">${$('<div>').text(r.estado_texto).html()}</span></td>
                </tr>
            `;
            $tb.append(tr);
        });

        if (typeof callback === 'function') callback();
    }, 'json');
}

function cargarInsumosDesdeRequisicionesSeleccionadas() {
    var reqIds = [];
    $('.chk-req-oc:checked').each(function() {
        reqIds.push(parseInt($(this).val(), 10));
    });

    if (reqIds.length === 0) {
        Swal.fire({ icon: 'info', text: 'Seleccione al menos una requisición aprobada de la lista.' });
        return;
    }

    var provId = parseInt($('#oc_proveedor_id').val(), 10) || 0;
    var soloProv = $('#chk-filtrar-insumos-prov').is(':checked') && provId > 0 ? 1 : 0;

    $.post('ordenes_compra_data.php', {
        action: 'cargar_insumos_requisiciones',
        requisiciones_ids: reqIds,
        proveedor_id: provId,
        solo_proveedor: soloProv
    }, function(resp) {
        if (!resp || !resp.success) {
            Swal.fire({ icon: 'error', text: 'No se pudieron cargar los insumos de las requisiciones.' });
            return;
        }

        // Conservar insumos manuales ya agregados
        var manuales = insumosOC.filter(function(item) {
            return !item.requisicion_id;
        });

        var nuevosReq = (resp.insumos || []).map(function(d) {
            var cant = parseFloat(d.cantidad) || 0;
            var costo = parseFloat(d.costo_unitario) || 0;
            return {
                requisicion_id: parseInt(d.requisicion_id, 10),
                requisicion_detalle_id: parseInt(d.requisicion_detalle_id, 10),
                codigo_requisicion: d.codigo_requisicion,
                insumo_id: parseInt(d.insumo_id, 10),
                insumo_nombre: d.insumo_nombre,
                unidad_medida: d.unidad_medida,
                permite_decimal: parseInt(d.permite_decimal, 10),
                cantidad: cant,
                costo_unitario: costo,
                subtotal: cant * costo,
                proveedor_id: d.proveedor_id
            };
        });

        // Si aún no había proveedor seleccionado y el primer insumo tiene proveedor, sugerirlo automáticamente
        if (!provId && nuevosReq.length > 0 && nuevosReq[0].proveedor_id) {
            $('#oc_proveedor_id').val(nuevosReq[0].proveedor_id);
            cargarSelectorInsumosManuales();
        }

        if (nuevosReq.length === 0 && soloProv) {
            Swal.fire({
                icon: 'warning',
                text: 'Las requisiciones seleccionadas no tienen insumos asociados al proveedor actual. Puede desmarcar el filtro de proveedor si desea cargar todos los insumos pendientes.'
            });
        }

        insumosOC = nuevosReq.concat(manuales);
        actualizarTablaInsumosOC();
    }, 'json');
}

function cargarSelectorInsumosManuales() {
    var provId = $('#oc_proveedor_id').val() || 0;
    var todos = $('#chk-mostrar-todos-insumos').is(':checked') ? 1 : 0;

    if (!provId && !todos) {
        $('#oc-nuevo-insumo-id').html('<option value="">-- Seleccione un proveedor o marque mostrar todo el catálogo --</option>');
        return;
    }

    $.post('ordenes_compra_data.php', {
        action: 'obtener_insumos_proveedor',
        proveedor_id: provId,
        todos_insumos: todos
    }, function(resp) {
        var $sel = $('#oc-nuevo-insumo-id');
        $sel.html('<option value="">-- Seleccione un insumo --</option>');
        if (resp && resp.success && resp.insumos) {
            resp.insumos.forEach(function(ins) {
                var opt = $('<option></option>')
                    .attr('value', ins.id)
                    .attr('data-nombre', ins.nombre)
                    .attr('data-costo', ins.costo_unitario)
                    .attr('data-unidad', ins.unidad_medida)
                    .attr('data-decimal', ins.permite_decimal)
                    .text(ins.nombre + ' ($' + parseFloat(ins.costo_unitario).toFixed(2) + ' / ' + ins.unidad_medida + ')');
                $sel.append(opt);
            });
        }
    }, 'json');
}

function agregarInsumoManualOC() {
    var $opt = $('#oc-nuevo-insumo-id option:selected');
    var insumoId = $('#oc-nuevo-insumo-id').val();
    var cantidad = parseFloat($('#oc-nuevo-cantidad').val()) || 0;
    var costo = parseFloat($('#oc-nuevo-costo').val()) || 0;

    if (!insumoId || cantidad <= 0 || costo <= 0) {
        Swal.fire({ icon: 'warning', text: 'Seleccione un insumo e ingrese cantidad y costo unitario válidos.' });
        return;
    }

    var permiteDecimal = parseInt($opt.data('decimal'), 10) === 1;
    if (!permiteDecimal && Math.abs(cantidad - Math.round(cantidad)) > 1e-9) {
        Swal.fire({ icon: 'warning', text: 'Este insumo solo permite cantidades enteras.' });
        return;
    }

    insumosOC.push({
        requisicion_id: null,
        requisicion_detalle_id: null,
        codigo_requisicion: null,
        insumo_id: parseInt(insumoId, 10),
        insumo_nombre: $opt.data('nombre'),
        unidad_medida: $opt.data('unidad'),
        permite_decimal: permiteDecimal ? 1 : 0,
        cantidad: cantidad,
        costo_unitario: costo,
        subtotal: cantidad * costo
    });

    actualizarTablaInsumosOC();
    $('#oc-nuevo-insumo-id').val('');
    $('#oc-nuevo-cantidad').val('');
    $('#oc-nuevo-costo').val('');
    $('#oc-nuevo-subtotal').val('');
}

function cambiarCantidadOC(index, valor) {
    var cant = parseFloat(valor) || 0;
    if (cant <= 0) {
        Swal.fire({ icon: 'warning', text: 'La cantidad debe ser mayor a 0.' });
        actualizarTablaInsumosOC();
        return;
    }
    var item = insumosOC[index];
    if (parseInt(item.permite_decimal, 10) === 0 && Math.abs(cant - Math.round(cant)) > 1e-9) {
        Swal.fire({ icon: 'warning', text: 'Este insumo solo admite cantidades enteras.' });
        actualizarTablaInsumosOC();
        return;
    }
    item.cantidad = cant;
    item.subtotal = item.cantidad * item.costo_unitario;
    actualizarTablaInsumosOC();
}

function cambiarCostoOC(index, valor) {
    var costo = parseFloat(valor) || 0;
    if (costo <= 0) {
        Swal.fire({ icon: 'warning', text: 'El costo unitario debe ser mayor a 0.' });
        actualizarTablaInsumosOC();
        return;
    }
    var item = insumosOC[index];
    item.costo_unitario = costo;
    item.subtotal = item.cantidad * item.costo_unitario;
    actualizarTablaInsumosOC();
}

function eliminarInsumoOC(index) {
    insumosOC.splice(index, 1);
    actualizarTablaInsumosOC();
}

function actualizarTablaInsumosOC() {
    var $tbody = $('#tbody-insumos-oc');
    $tbody.empty();

    var totalUsd = 0;
    var totalBs = 0;

    insumosOC.forEach(function(item, idx) {
        item.subtotal = item.cantidad * item.costo_unitario;
        totalUsd += item.subtotal;
        var equivBs = (tasaCambiariaActual && tasaCambiariaActual > 0) ? (item.subtotal * tasaCambiariaActual) : null;
        if (equivBs !== null) totalBs += equivBs;

        var stepVal = parseInt(item.permite_decimal, 10) === 0 ? '1' : '0.01';
        var origenHtml = item.codigo_requisicion
            ? '<span class="badge badge-info">' + $('<div>').text(item.codigo_requisicion).html() + '</span>'
            : '<span class="badge badge-secondary">Adicional / Manual</span>';

        var tr = `
            <tr>
                <td style="text-align:left;"><strong>${$('<div>').text(item.insumo_nombre).html()}</strong> (${$('<div>').text(item.unidad_medida).html()})</td>
                <td>${origenHtml}</td>
                <td>
                    <input type="number" class="form-control form-control-sm"
                           step="${stepVal}" min="${stepVal}" value="${item.cantidad}"
                           onchange="cambiarCantidadOC(${idx}, this.value)">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm"
                           step="0.01" min="0.01" value="${item.costo_unitario.toFixed(2)}"
                           onchange="cambiarCostoOC(${idx}, this.value)">
                </td>
                <td style="font-weight:bold;">$${item.subtotal.toFixed(2)}</td>
                <td>${formatearBs(equivBs)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarInsumoOC(${idx})">
                        <i class="fa fa-trash"></i>
                    </button>
                </td>
            </tr>
        `;
        $tbody.append(tr);
    });

    $('#total-oc-usd').text('$' + totalUsd.toFixed(2));
    $('#total-oc-bs').text(formatearBs(totalBs > 0 ? totalBs : null));

    if (tasaCambiariaActual && tasaCambiariaActual > 0) {
        $('#texto-tasa-informativa-oc').text('Tasa BCV/Sistema referencial: ' + tasaCambiariaActual.toFixed(4) + ' Bs/USD').show();
    } else {
        $('#texto-tasa-informativa-oc').hide();
    }

    if (insumosOC.length > 0) {
        $('#tabla-insumos-oc-wrap').show();
        $('#mensaje-sin-insumos-oc').hide();
        $('#btn-guardar-oc').prop('disabled', false);
    } else {
        $('#tabla-insumos-oc-wrap').hide();
        $('#mensaje-sin-insumos-oc').show();
        $('#btn-guardar-oc').prop('disabled', true);
    }
}

function limpiarFormularioOC() {
    $('#form-orden-compra')[0].reset();
    $('#oc_fecha').val('<?php echo date('Y-m-d'); ?>');
    $('#oc_proveedor_id').val('');
    insumosOC = [];
    actualizarTablaInsumosOC();
    seleccionarModalidadOC('requisicion');
    cargarSelectorInsumosManuales();
}

function verDetalleOC(idOc) {
    $('#modalDetalleOCBody').html('<div class="text-center p-4"><i class="fas fa-spinner fa-spin fa-2x"></i></div>');
    $('#modalDetalleOCFooter').html('<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>');
    $('#modalDetalleOC').modal('show');

    $.post('ordenes_compra_data.php', { action: 'obtener_detalle', oc_id: idOc }, function(resp) {
        if (!resp || !resp.success) {
            $('#modalDetalleOCBody').html('<div class="alert alert-danger">No se pudo cargar la Orden de Compra.</div>');
            return;
        }
        var oc = resp.orden_compra;
        var html = '<div class="row mb-2">';
        html += '<div class="col-md-4"><strong>Código O.C.:</strong> ' + $('<div>').text(oc.codigo_oc).html() + '</div>';
        html += '<div class="col-md-4"><strong>Fecha:</strong> ' + $('<div>').text(oc.fecha_formateada).html() + '</div>';
        html += '<div class="col-md-4"><strong>Estado:</strong> ' + oc.estado_badge + '</div>';
        html += '</div>';

        html += '<div class="row mb-2">';
        html += '<div class="col-md-6"><strong>Proveedor:</strong> ' + $('<div>').text(oc.proveedor_nombre + ' (' + (oc.proveedor_rif || '') + ')').html() + '</div>';
        html += '<div class="col-md-6"><strong>Modalidad:</strong> ' + (oc.modalidad === 'requisicion' ? 'Desde Requisición Aprobada' : 'Directa (Desde Cero)') + '</div>';
        html += '</div>';

        if (oc.observaciones) {
            html += '<div class="row mb-2"><div class="col-md-12"><strong>Observaciones:</strong> ' + $('<div>').text(oc.observaciones).html() + '</div></div>';
        }

        if (resp.compra_vinculada) {
            var cv = resp.compra_vinculada;
            html += '<div class="alert alert-success mt-2 mb-2"><i class="fas fa-check-circle"></i> <strong>Compra Registrada:</strong> ID #' + cv.id + ' | Factura: ' + $('<div>').text(cv.numero_factura || 'S/N').html() + ' | Total: $' + parseFloat(cv.total).toFixed(2) + '</div>';
        }

        html += '<hr><h6><strong>Detalle de Insumos de la Orden de Compra:</strong></h6>';
        html += '<div class="table-responsive"><table class="table table-sm table-bordered">';
        html += '<thead class="thead-light"><tr><th>Insumo</th><th>Requisición Origen</th><th>Cantidad</th><th>Costo Unit.</th><th>Subtotal</th></tr></thead><tbody>';

        var total = 0;
        (resp.detalles || []).forEach(function(d) {
            var sub = parseFloat(d.subtotal) || 0;
            total += sub;
            html += '<tr>';
            html += '<td style="text-align:left;">' + $('<div>').text(d.insumo_nombre + ' (' + d.unidad_medida + ')').html() + '</td>';
            html += '<td>' + (d.codigo_requisicion ? '<span class="badge badge-info">' + $('<div>').text(d.codigo_requisicion).html() + '</span>' : '<span class="text-muted">Directo</span>') + '</td>';
            html += '<td>' + parseFloat(d.cantidad).toFixed(2) + '</td>';
            html += '<td>$' + parseFloat(d.costo_unitario).toFixed(2) + '</td>';
            html += '<td><strong>$' + sub.toFixed(2) + '</strong></td>';
            html += '</tr>';
        });
        html += '</tbody>';
        html += '<tfoot><tr style="background:#f8f9fa; font-weight:bold;"><td colspan="4" style="text-align:right;">Total Orden de Compra:</td><td style="color:#0056b3;">$' + total.toFixed(2) + '</td></tr></tfoot>';
        html += '</table></div>';

        $('#modalDetalleOCBody').html(html);

        if (oc.estado === 'EMITIDA') {
            var btnCompra = '<a href="registrar_compra.php?oc_id=' + oc.id + '&proveedor_id=' + oc.proveedor_id + '" class="btn btn-success"><i class="fas fa-cart-plus"></i> Cargar en Registrar Compra</a> ';
            $('#modalDetalleOCFooter').html(btnCompra + '<button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>');
        }
    }, 'json');
}

function cancelarOC(idOc, codigo) {
    Swal.fire({
        icon: 'warning',
        title: '¿Cancelar Orden de Compra ' + codigo + '?',
        text: 'El estado cambiará a CANCELADA y las cantidades comprometidas en requisiciones volverán a quedar disponibles.',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        confirmButtonText: 'Sí, Cancelar O.C.',
        cancelButtonText: 'Volver'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $.post('ordenes_compra_data.php', { action: 'cancelar', oc_id: idOc }, function(resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message });
                cargarListado(1);
            } else {
                Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'Error al cancelar.' });
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Error al cancelar la Orden de Compra.';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            Swal.fire({ icon: 'error', text: msg });
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    cargarListado(1);
    bindCrudPagination('#paginacion-oc', cargarListado);

    if (reqPreseleccionada > 0) {
        mostrarVista('crear');
        seleccionarModalidadOC('requisicion');
        cargarRequisicionesDisponibles(function() {
            $('#chk-filtrar-insumos-prov').prop('checked', false);
            cargarInsumosDesdeRequisicionesSeleccionadas();
        });
    } else {
        mostrarVista('listado');
    }

    $('#btn-ir-crear').on('click', function() {
        reqPreseleccionada = 0;
        limpiarFormularioOC();
        mostrarVista('crear');
    });

    $('#btn-volver-listado').on('click', function() {
        mostrarVista('listado');
    });

    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    $('.clase-filtro-oc').on('change keyup', function(e) {
        if (e.type === 'keyup' && e.key !== 'Enter') return;
        cargarListado(1);
    });

    $('#btn-limpiar-filtros-oc').on('click', function() {
        $('#filtro-codigo').val('');
        $('#filtro-proveedor').val('');
        $('#filtro-modalidad').val('');
        $('#filtro-estado').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarListado(1);
    });

    $('#oc_proveedor_id').on('change', function() {
        cargarSelectorInsumosManuales();
        if ($('#oc_modalidad').val() === 'requisicion') {
            cargarRequisicionesDisponibles();
        }
    });

    $('#chk-mostrar-todos-insumos').on('change', function() {
        cargarSelectorInsumosManuales();
    });

    $('#btn-cargar-desde-req').on('click', function() {
        cargarInsumosDesdeRequisicionesSeleccionadas();
    });

    $(document).on('change', '.chk-req-oc', function() {
        if ($('.chk-req-oc:checked').length > 0) {
            cargarInsumosDesdeRequisicionesSeleccionadas();
        } else {
            insumosOC = insumosOC.filter(function(i) { return !i.requisicion_id; });
            actualizarTablaInsumosOC();
        }
    });

    $('#oc-nuevo-insumo-id').on('change', function() {
        var $opt = $(this).find('option:selected');
        var costo = $opt.data('costo');
        var dec = parseInt($opt.data('decimal'), 10) === 1;
        $('#oc-nuevo-cantidad').attr('step', dec ? '0.01' : '1').attr('min', dec ? '0.01' : '1');
        if (costo !== undefined) {
            $('#oc-nuevo-costo').val(costo);
            var cant = parseFloat($('#oc-nuevo-cantidad').val()) || 0;
            $('#oc-nuevo-subtotal').val('$' + (cant * parseFloat(costo)).toFixed(2));
        }
    });

    $('#oc-nuevo-cantidad, #oc-nuevo-costo').on('input', function() {
        var cant = parseFloat($('#oc-nuevo-cantidad').val()) || 0;
        var costo = parseFloat($('#oc-nuevo-costo').val()) || 0;
        $('#oc-nuevo-subtotal').val('$' + (cant * costo).toFixed(2));
    });

    $('#btn-oc-agregar-insumo').on('click', function() {
        agregarInsumoManualOC();
    });

    $('#form-orden-compra').on('submit', function(e) {
        e.preventDefault();
        if (insumosOC.length === 0) {
            Swal.fire({ icon: 'warning', text: 'Agregue al menos un insumo a la Orden de Compra.' });
            return;
        }
        var provId = $('#oc_proveedor_id').val();
        if (!provId) {
            Swal.fire({ icon: 'warning', text: 'Seleccione un proveedor.' });
            return;
        }

        var reqIds = [];
        $('.chk-req-oc:checked').each(function() {
            reqIds.push(parseInt($(this).val(), 10));
        });

        var payload = {
            action: 'crear',
            proveedor_id: provId,
            fecha: $('#oc_fecha').val(),
            modalidad: $('#oc_modalidad').val(),
            observaciones: $('#oc_observaciones').val(),
            requisiciones_ids: reqIds,
            insumos: insumosOC
        };

        $.ajax({
            url: 'ordenes_compra_data.php',
            type: 'POST',
            data: JSON.stringify(payload),
            contentType: 'application/json',
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    mostrarVista('listado');
                    cargarListado(1);
                } else {
                    Swal.fire({ icon: 'error', text: (resp && resp.message) ? resp.message : 'Error al guardar la O.C.' });
                }
            },
            error: function(xhr) {
                var msg = 'Error al emitir la Orden de Compra.';
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
