<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../lib/orden_numero.php";
require_once "../lib/OrdenProduccionUnidades.php";

OrdenProduccionUnidades::asegurarTablas($conn);
OrdenProduccionUnidades::sincronizarTodasLasOrdenes($conn);

// Obtener listado de clientes
$clientes = [];
$resClientes = $conn->query("SELECT id, nombre, numero_documento FROM clientes ORDER BY nombre ASC");
if ($resClientes) {
    while ($c = $resClientes->fetch_assoc()) {
        $clientes[] = $c;
    }
}

// Obtener órdenes de producción para el selector
$ordenes = [];
$resOrdenes = $conn->query("
    SELECT op.id, op.cantidad_a_producir, op.creado_en, p.nombre AS producto_nombre, COALESCE(t.nombre, 'Única') AS talla_nombre
    FROM ordenes_produccion op
    INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
    INNER JOIN productos p ON p.id = rp.producto_id
    LEFT JOIN tallas t ON t.id = op.talla_id
    ORDER BY op.id DESC
    LIMIT 50
");
if ($resOrdenes) {
    while ($o = $resOrdenes->fetch_assoc()) {
        $o['numero_orden'] = numero_orden_produccion((int)$o['id'], $o['creado_en']);
        $ordenes[] = $o;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Devoluciones de Producto Terminado - INVERCLINIK</title>
    
    <style>
        .devoluciones-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .devoluciones-table th {
            background-color: #0056b3;
            color: white;
            font-weight: bold;
            padding: 12px 14px;
            text-align: left;
            font-size: 14px;
        }

        .devoluciones-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: middle;
            font-size: 14px;
        }

        .devoluciones-table tr:nth-child(even) {
            background-color: #f8fbff;
        }

        .devoluciones-table tr:hover {
            background-color: #eaf2fc;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 15px;
        }

        .badge-code {
            font-family: monospace;
            background: #e2e8f0;
            color: #0f172a;
            font-size: 13px;
            font-weight: bold;
            padding: 3px 8px;
            border-radius: 4px;
            border: 1px solid #cbd5e1;
            display: inline-block;
        }

        .unit-preview-card {
            background: #f8fafc;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 16px;
            margin: 15px 0;
            transition: all 0.2s;
        }

        .unit-preview-card.active {
            background: #eff6ff;
            border-color: #3b82f6;
            border-style: solid;
        }

        .info-pill-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .info-pill-item {
            background: white;
            padding: 8px 10px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .info-pill-item small {
            display: block;
            color: #64748b;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold;
        }

        .info-pill-item span {
            font-weight: bold;
            color: #1e293b;
            font-size: 13px;
        }

        @media print {
            body * {
                visibility: hidden;
            }
            #printableComprobante, #printableComprobante * {
                visibility: visible;
            }
            #printableComprobante {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
            }
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h2 class="main-title">Devoluciones de Producto Terminado</h2>
            <p class="subtitle" style="margin-bottom: 20px;">Control y trazabilidad de productos devueltos con reingreso automático al stock</p>

            <div id="contenedor-vistas">
                <div id="vista-listado">
                    <div class="row form-group">
                        <div class="col-sm-12">
                            <div aria-label="Acciones de devoluciones">
                                <button class="btn btn-success" id="btn-ir-crear" style="margin-bottom: 0px !important;" title="Registrar Nueva Devolución" data-toggle="tooltip">
                                    <i class="fas fa-plus"></i> Registrar Devolución
                                </button>
                                <button class="btn btn-info" id="btn-toggle-filtros" title="Filtros" data-toggle="tooltip">
                                    <i class="fas fa-filter"></i> Filtros
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Panel de Filtros -->
                    <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                        <div class="row" style="margin-bottom: 10px;">
                            <div class="col-sm-6">
                                <label for="filtroBusqueda">Buscar</label>
                                <input type="text" id="filtroBusqueda" class="form-control" placeholder="Buscar por Código, Identificador (ej. OP1-U001), Producto, Cliente o Motivo...">
                            </div>
                            <div class="col-sm-3">
                                <label for="filtroFechaDesde">Fecha Desde</label>
                                <input type="date" id="filtroFechaDesde" class="form-control">
                            </div>
                            <div class="col-sm-3">
                                <label for="filtroFechaHasta">Fecha Hasta</label>
                                <input type="date" id="filtroFechaHasta" class="form-control">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-sm-10"></div>
                            <div class="col-sm-2" style="text-align: right;">
                                <button type="button" class="btn btn-secondary btn-block" id="btnLimpiarFiltros">
                                    <i class="fas fa-eraser"></i> Limpiar Filtros
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Tabla de Devoluciones -->
                    <div class="table-container">
                        <table class="devoluciones-table" id="tablaDevoluciones">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Fecha</th>
                                    <th>Identificador Pieza</th>
                                    <th>Orden de Prod.</th>
                                    <th>Producto / Talla</th>
                                    <th>Cliente</th>
                                    <th>Motivo Escrito</th>
                                    <th>Efecto Inventario</th>
                                    <th style="text-align: center; width: 80px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="devolucionesTableBody">
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 30px; color: #64748b;">
                                        <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando devoluciones...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div id="paginacionContainer" style="padding: 15px 0; display: flex; justify-content: space-between; align-items: center;">
                        <div id="infoPaginacion" style="font-size: 13px; color: #64748b;"></div>
                        <div id="botonesPaginacion" style="display: flex; gap: 5px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Registrar Devolución -->
<div class="modal fade" id="modalNuevaDevolucion" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #dee2e6;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" style="font-weight: bold; color: #1e293b;">
                    <i class="fas fa-undo-alt text-primary"></i> Registrar Devolución de Producto Terminado
                </h4>
            </div>
            <form id="formNuevaDevolucion">
                <div class="modal-body">
                    <!-- Buscador de Pieza -->
                    <div class="form-group">
                        <label style="font-weight: bold;">Identificador del Producto a Devolver <span style="color: red;">*</span></label>
                        <div class="input-group" style="margin-bottom: 8px;">
                            <input type="text" id="inputBuscarIdentificador" class="form-control" placeholder="Ingrese o escanee el Identificador (ej. OP1-U001)...">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-primary" id="btnBuscarUnidad">
                                    <i class="fas fa-search"></i> Buscar
                                </button>
                            </span>
                        </div>
                        <small class="text-muted">O seleccione una Orden de Producción reciente para listar sus piezas:</small>
                        <select id="selectOrdenAux" class="form-control" style="margin-top: 4px;">
                            <option value="">-- Seleccionar Orden de Producción reciente --</option>
                            <?php foreach ($ordenes as $ord): ?>
                                <option value="<?php echo $ord['id']; ?>">
                                    <?php echo htmlspecialchars($ord['numero_orden'] . ' - ' . $ord['producto_nombre'] . ' (' . $ord['talla_nombre'] . ') [' . (int)$ord['cantidad_a_producir'] . ' unids]'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" id="contenedorSelectorUnidadesOrden" style="display: none;">
                        <label style="font-weight: bold;">Pieza de la Orden Seleccionada <span style="color: red;">*</span></label>
                        <select id="selectUnidadesDeOrden" class="form-control">
                            <option value="">-- Seleccionar pieza producida --</option>
                        </select>
                    </div>

                    <!-- Tarjeta Preview del Producto -->
                    <div class="unit-preview-card" id="cardPreviewUnidad">
                        <div id="previewVacio" style="text-align: center; color: #64748b; padding: 10px;">
                            <i class="fas fa-barcode fa-2x" style="margin-bottom: 6px; color: #94a3b8;"></i>
                            <div>Ingrese o seleccione el identificador único para validar los datos de producción.</div>
                        </div>

                        <div id="previewContenido" style="display: none;">
                            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">
                                <div>
                                    <span class="badge-code" id="prevCodigoIdentificador">OP1-U001</span>
                                    <strong id="prevProductoNombre" style="margin-left: 8px; font-size: 15px; color: #0056b3;">Nombre Producto</strong>
                                </div>
                                <div id="prevEstadoBadge"></div>
                            </div>

                            <div class="info-pill-grid">
                                <div class="info-pill-item">
                                    <small>Talla</small>
                                    <span id="prevTalla">M</span>
                                </div>
                                <div class="info-pill-item">
                                    <small>Orden Prod.</small>
                                    <span id="prevOrdenNumero">1-AGOST-26</span>
                                </div>
                                <div class="info-pill-item">
                                    <small>Fecha Prod.</small>
                                    <span id="prevFechaProd">--</span>
                                </div>
                                <div class="info-pill-item">
                                    <small>Factura</small>
                                    <span id="prevFactura">N/A</span>
                                </div>
                            </div>

                            <div id="alertaHistorialDevoluciones" style="display: none; margin-top: 10px; padding: 8px 12px; background: #fff3cd; color: #856404; border-radius: 6px; font-size: 12px;">
                                <i class="fas fa-exclamation-triangle"></i> Esta unidad ya cuenta con registros previos de devolución.
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="unidadIdSeleccionada" name="unidad_id" value="">

                    <!-- Datos del Formulario -->
                    <div class="form-group">
                        <label>Cliente (Opcional)</label>
                        <select id="inputClienteId" name="cliente_id" class="form-control">
                            <option value="">-- Cliente no especificado / Anónimo --</option>
                            <?php foreach ($clientes as $cl): ?>
                                <option value="<?php echo $cl['id']; ?>">
                                    <?php echo htmlspecialchars($cl['nombre'] . ($cl['numero_documento'] ? ' (' . $cl['numero_documento'] . ')' : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- MOTIVO ESCRITO POR EL USUARIO -->
                    <div class="form-group">
                        <label style="font-weight: bold;">Motivo de la Devolución <span style="color: red;">*</span></label>
                        <textarea id="inputMotivo" name="motivo" class="form-control" rows="3" placeholder="Escriba aquí el motivo o razón por la cual se devuelve el producto..." required></textarea>
                    </div>

                    <!-- ACCIÓN EN EL INVENTARIO FIJA: SUMAR STOCK -->
                    <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 15px; font-size: 13px; background-color: #e7f3fe; border-color: #b8daff; color: #004085;">
                        <i class="fas fa-boxes"></i> <strong>Efecto en Inventario:</strong> Al registrar la devolución, el sistema sumará automáticamente <strong>+1 unidad</strong> al stock de producto terminado en el inventario.
                    </div>

                    <div class="form-group">
                        <label>Observaciones Adicionales (Opcional)</label>
                        <textarea id="inputObservaciones" name="observaciones" class="form-control" rows="2" placeholder="Observaciones internas adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f8fafc;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarDevolucion" disabled>
                        <i class="fas fa-save"></i> Guardar Devolución
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle / Comprobante de Devolución -->
<div class="modal fade" id="modalDetalleDevolucion" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #dee2e6;">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title" style="font-weight: bold; color: #1e293b;">
                    <i class="fas fa-file-invoice text-primary"></i> Detalle de Devolución
                </h4>
            </div>
            <div class="modal-body" id="printableComprobante">
                <div style="text-align: center; margin-bottom: 15px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                    <h3 style="margin: 0; color: #0056b3; font-weight: bold;">INVERCLINIK C.A.</h3>
                    <div style="font-size: 14px; color: #64748b;">Comprobante de Devolución de Producto Terminado</div>
                    <div style="margin-top: 8px;">
                        <span class="badge-code" id="detCodigoDevolucion" style="font-size: 16px; padding: 4px 12px;">DEV-0001</span>
                    </div>
                </div>

                <div class="row" style="margin-bottom: 15px;">
                    <div class="col-xs-6 col-sm-6">
                        <small class="text-muted" style="display:block; font-weight:bold;">FECHA DE REGISTRO</small>
                        <strong id="detFecha">--</strong>
                    </div>
                    <div class="col-xs-6 col-sm-6">
                        <small class="text-muted" style="display:block; font-weight:bold;">CLIENTE ASOCIADO</small>
                        <strong id="detCliente">--</strong>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                    <h5 style="font-weight: bold; color: #334155; margin-top: 0; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 5px;">
                        <i class="fas fa-tshirt text-primary"></i> Información de la Pieza Devuelta
                    </h5>
                    <div class="row">
                        <div class="col-sm-6" style="margin-bottom: 8px;">
                            <small class="text-muted" style="display:block;">PRODUCTO</small>
                            <span id="detProducto" style="font-weight: bold; font-size: 15px;">--</span>
                        </div>
                        <div class="col-sm-6" style="margin-bottom: 8px;">
                            <small class="text-muted" style="display:block;">TALLA</small>
                            <span id="detTalla" style="font-weight: bold;">--</span>
                        </div>
                        <div class="col-sm-6" style="margin-bottom: 8px;">
                            <small class="text-muted" style="display:block;">NÚMERO IDENTIFICADOR (SERIAL)</small>
                            <span class="badge-code" id="detIdentificador">--</span>
                        </div>
                        <div class="col-sm-6" style="margin-bottom: 8px;">
                            <small class="text-muted" style="display:block;">ORDEN DE PRODUCCIÓN ORIGEN</small>
                            <span id="detOrden" style="font-weight: bold; color: #0056b3;">--</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <small class="text-muted" style="display:block; font-weight:bold;">MOTIVO DE LA DEVOLUCIÓN</small>
                    <div id="detMotivo" style="background: #fff8e1; border: 1px solid #ffe082; padding: 12px; border-radius: 6px; font-size: 14px; color: #5d4037; font-weight: 600;">--</div>
                </div>

                <div class="form-group">
                    <small class="text-muted" style="display:block; font-weight:bold;">ACCIÓN DE INVENTARIO APLICADA</small>
                    <div style="background: #e8f5e9; border: 1px solid #c8e6c9; padding: 10px; border-radius: 6px; font-size: 13px; color: #2e7d32; font-weight: bold;">
                        <i class="fas fa-check-circle"></i> +1 unidad reingresada al stock de producto terminado
                    </div>
                </div>

                <div class="form-group" id="detContenedorObservaciones">
                    <small class="text-muted" style="display:block; font-weight:bold;">OBSERVACIONES INTERNAS</small>
                    <div id="detObservaciones" style="background: white; border: 1px solid #e2e8f0; padding: 10px; border-radius: 6px; font-size: 13px;">--</div>
                </div>
            </div>
            <div class="modal-footer" style="background: #f8fafc;">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" onclick="window.print();">
                    <i class="fas fa-print"></i> Imprimir Comprobante
                </button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let paginaActual = 1;

    // Cargar listado inicial
    cargarDevoluciones();

    // Toggle de panel de filtros
    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    // Filtros de búsqueda con debounce
    let timeoutBusqueda = null;
    $('#filtroBusqueda').on('input', function() {
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(function() {
            paginaActual = 1;
            cargarDevoluciones();
        }, 300);
    });

    $('#filtroFechaDesde, #filtroFechaHasta').on('change', function() {
        paginaActual = 1;
        cargarDevoluciones();
    });

    $('#btnLimpiarFiltros').on('click', function() {
        $('#filtroBusqueda').val('');
        $('#filtroFechaDesde').val('');
        $('#filtroFechaHasta').val('');
        paginaActual = 1;
        cargarDevoluciones();
    });

    // Abrir modal nueva devolución
    $('#btn-ir-crear').on('click', function() {
        resetearFormularioDevolucion();
        $('#modalNuevaDevolucion').modal('show');
        setTimeout(function() {
            $('#inputBuscarIdentificador').focus();
        }, 400);
    });

    // Buscar unidad por identificador
    $('#btnBuscarUnidad').on('click', function() {
        buscarUnidad($('#inputBuscarIdentificador').val());
    });

    $('#inputBuscarIdentificador').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            buscarUnidad($(this).val());
        }
    });

    // Selector auxiliar de orden de producción
    $('#selectOrdenAux').on('change', function() {
        const ordenId = $(this).val();
        if (!ordenId) {
            $('#contenedorSelectorUnidadesOrden').hide();
            return;
        }

        $.ajax({
            url: 'devoluciones_data.php',
            type: 'GET',
            data: { action: 'buscar_unidades_por_orden', orden_id: ordenId },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.unidades) {
                    let opts = '<option value="">-- Seleccionar pieza producida --</option>';
                    res.unidades.forEach(function(u) {
                        opts += `<option value="${u.numero_identificador}">${u.numero_identificador} - Unidad #${u.numero_secuencia} (${u.estado})</option>`;
                    });
                    $('#selectUnidadesDeOrden').html(opts);
                    $('#contenedorSelectorUnidadesOrden').slideDown(200);
                }
            }
        });
    });

    $('#selectUnidadesDeOrden').on('change', function() {
        const codigo = $(this).val();
        if (codigo) {
            $('#inputBuscarIdentificador').val(codigo);
            buscarUnidad(codigo);
        }
    });

    // Función de búsqueda de unidad
    function buscarUnidad(codigo) {
        codigo = $.trim(codigo);
        if (!codigo) {
            Swal.fire('Atención', 'Por favor ingrese o escanee un número identificador.', 'warning');
            return;
        }

        $('#btnBuscarUnidad').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

        $.ajax({
            url: 'devoluciones_data.php',
            type: 'GET',
            data: { action: 'buscar_unidad', codigo: codigo },
            dataType: 'json',
            success: function(res) {
                $('#btnBuscarUnidad').prop('disabled', false).html('<i class="fas fa-search"></i> Buscar');
                if (res.success && res.unidad) {
                    mostrarPreviewUnidad(res.unidad);
                } else {
                    limpiarPreviewUnidad();
                    Swal.fire('No encontrado', res.message || 'No se encontró el producto con ese identificador.', 'error');
                }
            },
            error: function(xhr) {
                $('#btnBuscarUnidad').prop('disabled', false).html('<i class="fas fa-search"></i> Buscar');
                limpiarPreviewUnidad();
                let msg = 'Error de conexión con el servidor.';
                try {
                    const err = JSON.parse(xhr.responseText);
                    if (err.message) msg = err.message;
                } catch(e) {}
                Swal.fire('Error', msg, 'error');
            }
        });
    }

    function mostrarPreviewUnidad(u) {
        $('#unidadIdSeleccionada').val(u.unidad_id);
        $('#prevCodigoIdentificador').text(u.numero_identificador);
        $('#prevProductoNombre').text(u.producto_nombre);
        $('#prevTalla').text(u.talla_nombre || 'Única');
        $('#prevOrdenNumero').text(u.numero_orden || ('OP #' + u.orden_id));
        $('#prevFechaProd').text(u.unidad_fecha_creacion ? u.unidad_fecha_creacion.substring(0, 10) : '--');
        $('#prevFactura').text(u.venta_factura || 'N/A');

        // Badge de estado
        let badgeHtml = '';
        if (u.estado_unidad === 'disponible') {
            badgeHtml = '<span class="label label-success"><i class="fas fa-check"></i> Disponible</span>';
        } else if (u.estado_unidad === 'devuelto') {
            badgeHtml = '<span class="label label-warning"><i class="fas fa-undo"></i> Devuelto</span>';
        } else if (u.estado_unidad === 'en_revision') {
            badgeHtml = '<span class="label label-info"><i class="fas fa-tools"></i> En Revisión</span>';
        } else if (u.estado_unidad === 'baja') {
            badgeHtml = '<span class="label label-danger"><i class="fas fa-times"></i> De Baja</span>';
        } else {
            badgeHtml = `<span class="label label-default">${u.estado_unidad}</span>`;
        }
        $('#prevEstadoBadge').html(badgeHtml);

        if (u.historial_devoluciones && u.historial_devoluciones.length > 0) {
            $('#alertaHistorialDevoluciones').show();
        } else {
            $('#alertaHistorialDevoluciones').hide();
        }

        if (u.cliente_id) {
            $('#inputClienteId').val(u.cliente_id);
        }

        $('#previewVacio').hide();
        $('#previewContenido').show();
        $('#cardPreviewUnidad').addClass('active');
        $('#btnGuardarDevolucion').prop('disabled', false);
    }

    function limpiarPreviewUnidad() {
        $('#unidadIdSeleccionada').val('');
        $('#previewContenido').hide();
        $('#previewVacio').show();
        $('#cardPreviewUnidad').removeClass('active');
        $('#btnGuardarDevolucion').prop('disabled', true);
    }

    function resetearFormularioDevolucion() {
        $('#formNuevaDevolucion')[0].reset();
        $('#inputBuscarIdentificador').val('');
        $('#selectOrdenAux').val('');
        $('#contenedorSelectorUnidadesOrden').hide();
        limpiarPreviewUnidad();
    }

    // Guardar Devolución
    $('#formNuevaDevolucion').on('submit', function(e) {
        e.preventDefault();
        const unidadId = $('#unidadIdSeleccionada').val();
        if (!unidadId) {
            Swal.fire('Atención', 'Debe seleccionar y validar un producto terminado antes de guardar.', 'warning');
            return;
        }

        const motivo = $.trim($('#inputMotivo').val());
        if (!motivo) {
            Swal.fire('Atención', 'Por favor escriba el motivo de la devolución.', 'warning');
            $('#inputMotivo').focus();
            return;
        }

        $('#btnGuardarDevolucion').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

        $.ajax({
            url: 'devoluciones_data.php',
            type: 'POST',
            data: {
                action: 'crear_devolucion',
                unidad_id: unidadId,
                cliente_id: $('#inputClienteId').val(),
                motivo: motivo,
                observaciones: $('#inputObservaciones').val()
            },
            dataType: 'json',
            success: function(res) {
                $('#btnGuardarDevolucion').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Devolución');
                if (res.success) {
                    $('#modalNuevaDevolucion').modal('hide');
                    Swal.fire({
                        icon: 'success',
                        title: 'Devolución Registrada',
                        text: res.message
                    });
                    cargarDevoluciones();
                } else {
                    Swal.fire('Error', res.message || 'No se pudo registrar la devolución', 'error');
                }
            },
            error: function(xhr) {
                $('#btnGuardarDevolucion').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Devolución');
                let msg = 'Error en el servidor al guardar la devolución.';
                try {
                    const err = JSON.parse(xhr.responseText);
                    if (err.message) msg = err.message;
                } catch(e) {}
                Swal.fire('Error', msg, 'error');
            }
        });
    });

    // Cargar Devoluciones
    function cargarDevoluciones() {
        $.ajax({
            url: 'devoluciones_data.php',
            type: 'GET',
            data: {
                action: 'listar_devoluciones',
                busqueda: $('#filtroBusqueda').val(),
                fecha_desde: $('#filtroFechaDesde').val(),
                fecha_hasta: $('#filtroFechaHasta').val(),
                page: paginaActual
            },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    renderizarTablaDevoluciones(res.devoluciones);
                    renderizarPaginacion(res.paginacion);
                }
            },
            error: function() {
                $('#devolucionesTableBody').html('<tr><td colspan="9" class="text-center text-danger py-4">Error al cargar listado de devoluciones.</td></tr>');
            }
        });
    }

    function renderizarTablaDevoluciones(lista) {
        if (!lista || lista.length === 0) {
            $('#devolucionesTableBody').html(`
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px 20px; color: #94a3b8;">
                        <i class="fas fa-box-open fa-3x" style="margin-bottom: 12px; color: #cbd5e1;"></i>
                        <div style="font-size: 15px; font-weight: bold; color: #64748b;">No se encontraron registros de devoluciones</div>
                        <div style="font-size: 13px;">Utilice el botón "Registrar Devolución" para procesar el retorno de un producto.</div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        lista.forEach(function(d) {
            const fechaFormat = d.fecha ? d.fecha.substring(0, 16) : '--';
            const motivoTexto = d.motivo || '-';

            html += `
                <tr>
                    <td><span class="badge-code">${d.codigo_devolucion}</span></td>
                    <td>${fechaFormat}</td>
                    <td><strong>${d.numero_identificador || '-'}</strong></td>
                    <td><span style="color: #0056b3; font-weight: bold;">${d.numero_orden || '-'}</span></td>
                    <td>
                        <div><strong>${d.producto_nombre || '-'}</strong></div>
                        <small class="text-muted">Talla: ${d.talla_nombre || 'Única'}</small>
                    </td>
                    <td>${d.cliente_nombre ? d.cliente_nombre : '<span class="text-muted">Anónimo</span>'}</td>
                    <td><span title="${motivoTexto}" style="display: block; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;">${motivoTexto}</span></td>
                    <td><span class="label label-success" style="font-size: 12px;"><i class="fas fa-plus"></i> +1 Stock Sumado</span></td>
                    <td style="text-align: center;">
                        <button class="btn btn-sm btn-info btnVerDetalleDevolucion" data-id="${d.id}" title="Ver Comprobante / Detalle">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        $('#devolucionesTableBody').html(html);

        // Event listener para ver detalle
        $('.btnVerDetalleDevolucion').on('click', function() {
            const devId = $(this).data('id');
            verDetalleDevolucion(devId);
        });
    }

    function renderizarPaginacion(pag) {
        if (!pag || pag.total_registros === 0) {
            $('#infoPaginacion').text('');
            $('#botonesPaginacion').html('');
            return;
        }

        const inicio = ((pag.pagina_actual - 1) * pag.por_pagina) + 1;
        const fin = Math.min(pag.pagina_actual * pag.por_pagina, pag.total_registros);
        $('#infoPaginacion').text(`Mostrando ${inicio} a ${fin} de ${pag.total_registros} devoluciones`);

        let btns = '';
        if (pag.pagina_actual > 1) {
            btns += `<button class="btn btn-sm btn-default btnPag" data-page="${pag.pagina_actual - 1}"><i class="fas fa-chevron-left"></i></button>`;
        }

        for (let i = 1; i <= pag.total_paginas; i++) {
            if (i === pag.pagina_actual) {
                btns += `<button class="btn btn-sm btn-primary btnPag active" data-page="${i}">${i}</button>`;
            } else if (i === 1 || i === pag.total_paginas || (i >= pag.pagina_actual - 2 && i <= pag.pagina_actual + 2)) {
                btns += `<button class="btn btn-sm btn-default btnPag" data-page="${i}">${i}</button>`;
            }
        }

        if (pag.pagina_actual < pag.total_paginas) {
            btns += `<button class="btn btn-sm btn-default btnPag" data-page="${pag.pagina_actual + 1}"><i class="fas fa-chevron-right"></i></button>`;
        }

        $('#botonesPaginacion').html(btns);

        $('.btnPag').on('click', function() {
            paginaActual = parseInt($(this).data('page'));
            cargarDevoluciones();
        });
    }

    function verDetalleDevolucion(id) {
        $.ajax({
            url: 'devoluciones_data.php',
            type: 'GET',
            data: { action: 'obtener_detalle', id: id },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.devolucion) {
                    const d = res.devolucion;
                    $('#detCodigoDevolucion').text(d.codigo_devolucion);
                    $('#detFecha').text(d.fecha || '--');
                    $('#detCliente').text(d.cliente_nombre ? (d.cliente_nombre + (d.cliente_documento ? ' (' + d.cliente_documento + ')' : '')) : 'Cliente no especificado');
                    $('#detProducto').text(d.producto_nombre || '--');
                    $('#detTalla').text(d.talla_nombre || 'Única');
                    $('#detIdentificador').text(d.numero_identificador || '--');
                    $('#detOrden').text(d.numero_orden || ('OP #' + d.orden_produccion_id));
                    $('#detMotivo').text(d.motivo || '--');

                    if (d.observaciones) {
                        $('#detObservaciones').text(d.observaciones);
                        $('#detContenedorObservaciones').show();
                    } else {
                        $('#detContenedorObservaciones').hide();
                    }

                    $('#modalDetalleDevolucion').modal('show');
                } else {
                    Swal.fire('Error', res.message || 'No se pudo cargar el detalle de la devolución.', 'error');
                }
            }
        });
    }
});
</script>

<?php require_once "../template/footer.php"; ?>
