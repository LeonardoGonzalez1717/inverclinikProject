<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../lib/orden_numero.php";

// Obtener listado de clientes
$clientes = [];
$resClientes = $conn->query("SELECT id, nombre, numero_documento FROM clientes ORDER BY nombre ASC");
if ($resClientes) {
    while ($c = $resClientes->fetch_assoc()) {
        $clientes[] = $c;
    }
}

// Obtener órdenes de producción recientes para el selector
$ordenes = [];
$resOrdenes = $conn->query("
    SELECT op.id, op.cantidad_a_producir, op.creado_en, p.nombre AS producto_nombre, COALESCE(t.nombre, 'Única') AS talla_nombre
    FROM ordenes_produccion op
    INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
    INNER JOIN productos p ON p.id = rp.producto_id
    LEFT JOIN tallas t ON t.id = op.talla_id
    ORDER BY op.id DESC
    LIMIT 60
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
            padding: 18px;
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
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }

        .info-pill-item {
            background: white;
            padding: 10px 12px;
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
            font-size: 14px;
        }

        .form-section-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 24px;
            margin-top: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
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

        .select2-container--default .select2-selection--single {
            height: 42px !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 6px !important;
            padding: 7px 12px !important;
            background-color: #fff !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 26px !important;
            color: #1e293b !important;
            font-size: 14px !important;
            padding-left: 0 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: #94a3b8 !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
        }
        .select2-dropdown {
            border-color: #cbd5e1 !important;
            border-radius: 6px !important;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05) !important;
        }
        .select2-results__option {
            padding: 9px 12px !important;
            font-size: 13.5px !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #0056b3 !important;
        }
    </style>
</head>
<body>

<div class="main-content">
    <div class="container-wrapper">
        <div class="container-inner">
            <h2 class="main-title">Devoluciones de Producto Terminado</h2>
            <p class="subtitle" style="margin-bottom: 20px;">Control y registro de productos devueltos con reingreso automático al stock</p>

            <div id="contenedor-vistas">
                <!-- VISTA 1: LISTADO DE DEVOLUCIONES -->
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
                                <input type="text" id="filtroBusqueda" class="form-control" placeholder="Buscar por Código de Devolución, Orden (ej. 01-AGOST), Producto, Cliente o Motivo...">
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
                                    <th>Orden de Prod.</th>
                                    <th>Producto / Talla</th>
                                    <th style="text-align: center;">Cantidad</th>
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

                <!-- VISTA 2: FORMULARIO REGISTRAR DEVOLUCIÓN (VISTA INTEGRADA) -->
                <div id="vista-crear" style="display: none;">
                    <button class="btn-volver" id="btn-volver-listado" type="button">
                        <i class="fas fa-arrow-left"></i> Volver al Listado
                    </button>

                    <div class="form-section-card">
                        <h4 style="font-weight: bold; color: #1e293b; margin-top: 0; margin-bottom: 20px; border-bottom: 2px solid #e2e8f0; padding-bottom: 10px;">
                            <i class="fas fa-undo-alt text-primary"></i> Registrar Devolución de Producto Terminado
                        </h4>

                        <form id="formNuevaDevolucion">
                            <!-- Selector de Orden de Producción -->
                            <div class="form-group">
                                <label for="selectOrdenDevolver" style="font-weight: bold; color: #1e293b; font-size: 14px;">
                                    <i class="fas fa-clipboard-list text-primary"></i> Orden de Producción <span style="color: red;">*</span>
                                </label>
                                <select id="selectOrdenDevolver" name="orden_produccion_id" class="form-control" style="width: 100%;" required>
                                    <option value="">-- Buscar por número de orden (ej. 01-AGOST) o producto --</option>
                                    <?php foreach ($ordenes as $ord): ?>
                                        <option value="<?php echo $ord['id']; ?>"
                                                data-producto="<?php echo htmlspecialchars($ord['producto_nombre']); ?>"
                                                data-talla="<?php echo htmlspecialchars($ord['talla_nombre']); ?>"
                                                data-orden="<?php echo htmlspecialchars($ord['numero_orden']); ?>"
                                                data-cantidad="<?php echo htmlspecialchars($ord['cantidad_a_producir']); ?>">
                                            <?php echo htmlspecialchars($ord['numero_orden'] . ' — ' . $ord['producto_nombre'] . ' (' . $ord['talla_nombre'] . ')'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted" style="display: block; margin-top: 5px;">
                                    <i class="fas fa-info-circle"></i> Seleccione la orden de producción a la cual pertenece el producto devuelto.
                                </small>
                            </div>

                            <!-- Tarjeta Preview del Producto y Orden -->
                            <div class="unit-preview-card" id="cardPreviewOrden">
                                <div id="previewVacio" style="text-align: center; color: #64748b; padding: 15px;">
                                    <i class="fas fa-box-open fa-2x" style="margin-bottom: 8px; color: #94a3b8;"></i>
                                    <div>Seleccione una orden de producción para ver los datos asociados.</div>
                                </div>

                                <div id="previewContenido" style="display: none;">
                                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">
                                        <div>
                                            <span class="badge-code" id="prevOrdenNumero">01-AGOST-26</span>
                                            <strong id="prevProductoNombre" style="margin-left: 8px; font-size: 15px; color: #0056b3;">Nombre Producto</strong>
                                        </div>
                                        <div>
                                            <span class="label label-info" id="prevCantTotalBadge">Total OP: -- unds</span>
                                        </div>
                                    </div>

                                    <div class="info-pill-grid">
                                        <div class="info-pill-item">
                                            <small>Talla</small>
                                            <span id="prevTalla">M</span>
                                        </div>
                                        <div class="info-pill-item">
                                            <small>Cantidad Producida</small>
                                            <span id="prevCantidadProducida">--</span>
                                        </div>
                                        <div class="info-pill-item">
                                            <small>Fecha Creación</small>
                                            <span id="prevFechaProd">--</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cantidad a Devolver y Cliente -->
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label style="font-weight: bold; color: #1e293b;">Cantidad a Devolver <span style="color: red;">*</span></label>
                                        <input type="number" step="1" min="1" id="inputCantidad" name="cantidad" class="form-control" value="1" required>
                                        <small class="text-muted">Número de unidades devueltas que reingresarán al inventario.</small>
                                    </div>
                                </div>
                                <div class="col-sm-6">
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
                                </div>
                            </div>

                            <!-- MOTIVO ESCRITO POR EL USUARIO -->
                            <div class="form-group">
                                <label style="font-weight: bold;">Motivo de la Devolución <span style="color: red;">*</span></label>
                                <textarea id="inputMotivo" name="motivo" class="form-control" rows="3" placeholder="Escriba aquí el motivo o razón por la cual se devuelve el producto..." required></textarea>
                            </div>

                            <!-- ACCIÓN EN EL INVENTARIO FIJA: SUMAR STOCK -->
                            <div class="alert alert-info" style="margin-top: 15px; margin-bottom: 15px; font-size: 13px; background-color: #e7f3fe; border-color: #b8daff; color: #004085;">
                                <i class="fas fa-boxes"></i> <strong>Efecto en Inventario:</strong> Al registrar la devolución, el sistema sumará automáticamente la cantidad ingresada al stock de producto terminado en el inventario.
                            </div>

                            <div class="form-group">
                                <label>Observaciones Adicionales (Opcional)</label>
                                <textarea id="inputObservaciones" name="observaciones" class="form-control" rows="2" placeholder="Observaciones internas adicionales..."></textarea>
                            </div>

                            <div class="row" style="margin-top: 25px; border-top: 1px solid #e2e8f0; padding-top: 18px;">
                                <div class="col-sm-12">
                                    <button type="submit" class="btn btn-primary" id="btnGuardarDevolucion" disabled>
                                        <i class="fas fa-save"></i> Guardar Devolución
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary" id="btn-cancelar-crear">
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
                        <i class="fas fa-tshirt text-primary"></i> Información del Producto Devuelto
                    </h5>
                    <div class="row">
                        <div class="col-sm-6" style="margin-bottom: 8px;">
                            <small class="text-muted" style="display:block;">PRODUCTO</small>
                            <span id="detProducto" style="font-weight: bold; font-size: 15px;">--</span>
                        </div>
                        <div class="col-sm-3" style="margin-bottom: 8px;">
                            <small class="text-muted" style="display:block;">TALLA</small>
                            <span id="detTalla" style="font-weight: bold;">--</span>
                        </div>
                        <div class="col-sm-3" style="margin-bottom: 8px;">
                            <small class="text-muted" style="display:block;">CANTIDAD DEVUELTA</small>
                            <span id="detCantidad" style="font-weight: bold; color: #0056b3; font-size: 15px;">--</span>
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
                        <i class="fas fa-check-circle"></i> <span id="detEfectoInventario">Reingreso al stock de producto terminado</span>
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

<script src="../assets/js/select2.min.js"></script>
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

    // Control de vistas (Listado / Crear)
    function mostrarVista(vista) {
        if (vista === 'crear') {
            $('#vista-listado').hide();
            $('#vista-crear').fadeIn(200);
            resetearFormularioDevolucion();
        } else {
            $('#vista-crear').hide();
            $('#vista-listado').fadeIn(200);
            cargarDevoluciones();
        }
    }

    $('#btn-ir-crear').on('click', function() {
        mostrarVista('crear');
    });

    $('#btn-volver-listado, #btn-cancelar-crear').on('click', function() {
        mostrarVista('listado');
    });

    // Inicializar Select2 en el selector de órdenes
    $('#selectOrdenDevolver').select2({
        placeholder: '-- Buscar por número de orden (ej. 01-AGOST) o producto --',
        allowClear: true,
        width: '100%',
        ajax: {
            url: 'devoluciones_data.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    action: 'buscar_ordenes_select2',
                    q: params.term || ''
                };
            },
            processResults: function (data) {
                return {
                    results: data.results || []
                };
            },
            cache: true
        },
        minimumInputLength: 0
    });

    // Al seleccionar una orden en Select2
    $('#selectOrdenDevolver').on('select2:select change', function(e) {
        const ordenId = $(this).val();
        if (ordenId) {
            const data = $(this).select2('data')[0];
            if (data && data.producto_nombre) {
                mostrarPreviewOrden(data);
            } else {
                obtenerInfoOrden(ordenId);
            }
        } else {
            limpiarPreviewOrden();
        }
    });

    $('#selectOrdenDevolver').on('select2:unselect', function() {
        limpiarPreviewOrden();
    });

    function obtenerInfoOrden(ordenId) {
        $.ajax({
            url: 'devoluciones_data.php',
            type: 'GET',
            data: { action: 'obtener_orden_info', orden_id: ordenId },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.orden) {
                    mostrarPreviewOrden(res.orden);
                } else {
                    limpiarPreviewOrden();
                    Swal.fire('Error', res.message || 'No se pudo obtener información de la orden.', 'error');
                }
            },
            error: function() {
                limpiarPreviewOrden();
            }
        });
    }

    function mostrarPreviewOrden(o) {
        $('#prevOrdenNumero').text(o.numero_orden || ('OP #' + (o.id || '')));
        $('#prevProductoNombre').text(o.producto_nombre || 'Producto');
        $('#prevTalla').text(o.talla_nombre || 'Única');
        $('#prevCantidadProducida').text((o.cantidad_a_producir || '--') + ' unds');
        $('#prevCantTotalBadge').text('Total OP: ' + (o.cantidad_a_producir || '--') + ' unds');
        $('#prevFechaProd').text(o.creado_en ? o.creado_en.substring(0, 10) : '--');

        $('#previewVacio').hide();
        $('#previewContenido').show();
        $('#cardPreviewOrden').addClass('active');
        $('#btnGuardarDevolucion').prop('disabled', false);
    }

    function limpiarPreviewOrden() {
        $('#previewContenido').hide();
        $('#previewVacio').show();
        $('#cardPreviewOrden').removeClass('active');
        $('#btnGuardarDevolucion').prop('disabled', true);
    }

    function resetearFormularioDevolucion() {
        $('#formNuevaDevolucion')[0].reset();
        $('#selectOrdenDevolver').val(null).trigger('change');
        $('#inputClienteId').val('');
        $('#inputCantidad').val('1');
        limpiarPreviewOrden();
    }

    // Guardar Devolución
    $('#formNuevaDevolucion').on('submit', function(e) {
        e.preventDefault();
        const ordenId = $('#selectOrdenDevolver').val();
        if (!ordenId) {
            Swal.fire('Atención', 'Debe seleccionar una orden de producción.', 'warning');
            return;
        }

        const cantidad = parseFloat($('#inputCantidad').val());
        if (isNaN(cantidad) || cantidad <= 0) {
            Swal.fire('Atención', 'Ingrese una cantidad válida mayor a 0.', 'warning');
            $('#inputCantidad').focus();
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
                orden_produccion_id: ordenId,
                cantidad: cantidad,
                cliente_id: $('#inputClienteId').val(),
                motivo: motivo,
                observaciones: $('#inputObservaciones').val()
            },
            dataType: 'json',
            success: function(res) {
                $('#btnGuardarDevolucion').prop('disabled', false).html('<i class="fas fa-save"></i> Guardar Devolución');
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Devolución Registrada',
                        text: res.message
                    });
                    mostrarVista('listado');
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
            const cantFormat = parseFloat(d.cantidad || 1);

            html += `
                <tr>
                    <td><span class="badge-code">${d.codigo_devolucion}</span></td>
                    <td>${fechaFormat}</td>
                    <td><span style="color: #0056b3; font-weight: bold;">${d.numero_orden || '-'}</span></td>
                    <td>
                        <div><strong>${d.producto_nombre || '-'}</strong></div>
                        <small class="text-muted">Talla: ${d.talla_nombre || 'Única'}</small>
                    </td>
                    <td style="text-align: center;"><strong>${cantFormat}</strong> <small class="text-muted">unds</small></td>
                    <td>${d.cliente_nombre ? d.cliente_nombre : '<span class="text-muted">Anónimo</span>'}</td>
                    <td><span title="${motivoTexto}" style="display: block; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;">${motivoTexto}</span></td>
                    <td><span class="label label-success" style="font-size: 12px;"><i class="fas fa-plus"></i> +${cantFormat} Stock Sumado</span></td>
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
                    const cant = parseFloat(d.cantidad || 1);

                    $('#detCodigoDevolucion').text(d.codigo_devolucion);
                    $('#detFecha').text(d.fecha || '--');
                    $('#detCliente').text(d.cliente_nombre ? (d.cliente_nombre + (d.cliente_documento ? ' (' + d.cliente_documento + ')' : '')) : 'Cliente no especificado');
                    $('#detProducto').text(d.producto_nombre || '--');
                    $('#detTalla').text(d.talla_nombre || 'Única');
                    $('#detCantidad').text(cant + ' unds');
                    $('#detOrden').text(d.numero_orden || ('OP #' + d.orden_produccion_id));
                    $('#detMotivo').text(d.motivo || '--');
                    $('#detEfectoInventario').text(`+${cant} unidad(es) reingresada(s) al stock de producto terminado`);

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
