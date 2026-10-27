<?php
require_once __DIR__ . "/../template/header.php";
require_once __DIR__ . "/../connection/connection.php";
require_once __DIR__ . "/../lib/orden_numero.php";
require_once __DIR__ . "/../lib/DevolucionesSchema.php";

DevolucionesSchema::asegurarTablas($conn);

// Obtener órdenes de producción recientes para el selector
$ordenes = [];
$resOrdenes = $conn->query("
    SELECT op.id, op.cantidad_a_producir, op.creado_en, 
           p.id AS producto_id, p.nombre AS producto_nombre, 
           COALESCE(t.nombre, 'Única') AS talla_nombre,
           COALESCE(
               inv.stock_actual,
               (SELECT COALESCE(SUM(l.cantidad_restante), 0)
                FROM inventario_lotes l
                WHERE l.tipo_item = 'producto' 
                  AND (l.tipo_item_id = r.id OR (r.id IS NULL AND l.tipo_item_id = p.id))
                  AND l.cantidad_restante > 0),
               0
           ) AS stock_inventario
    FROM ordenes_produccion op
    INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
    INNER JOIN productos p ON p.id = rp.producto_id
    LEFT JOIN tallas t ON t.id = op.talla_id
    LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
        AND r.rango_tallas_id = rp.rango_tallas_id 
        AND r.tipo_produccion_id = rp.tipo_produccion_id
    LEFT JOIN inventario inv ON inv.tipo_item = 'producto' AND inv.tipo_item_id = r.id
    ORDER BY op.id DESC
    LIMIT 60
");
if ($resOrdenes) {
    while ($o = $resOrdenes->fetch_assoc()) {
        $o['numero_orden'] = numero_orden_produccion((int)$o['id'], $o['creado_en']);
        $stockVal = (float)$o['stock_inventario'];
        $o['stock_inventario_fmt'] = (fmod($stockVal, 1.0) == 0.0) ? number_format($stockVal, 0) : number_format($stockVal, 2, '.', ',');
        $ordenes[] = $o;
    }
}

// Obtener ventas recientes para el selector
$ventas = [];
$resVentas = $conn->query("
    SELECT v.id, v.numero_factura, v.fecha, v.total
    FROM ventas v
    WHERE v.estado != 'cancelado'
    ORDER BY v.id DESC
    LIMIT 60
");
if ($resVentas) {
    while ($v = $resVentas->fetch_assoc()) {
        $v['numero_factura'] = !empty($v['numero_factura']) ? $v['numero_factura'] : ('Venta #' . $v['id']);
        $ventas[] = $v;
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

        .devolucion-tabs {
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 20px;
        }

        .devolucion-tabs > li > a {
            font-size: 14.5px;
            font-weight: 600;
            color: #64748b;
            border: none !important;
            border-radius: 6px 6px 0 0;
            padding: 12px 20px;
            background: #f8fafc;
            border-bottom: 3px solid transparent !important;
            margin-bottom: -2px;
            transition: all 0.2s ease;
            text-align: center;
        }

        .devolucion-tabs > li > a:hover {
            color: #0056b3;
            background: #eff6ff;
        }

        .devolucion-tabs > li.active > a,
        .devolucion-tabs > li.active > a:focus,
        .devolucion-tabs > li.active > a:hover {
            color: #0056b3 !important;
            background: #ffffff !important;
            border-bottom: 3px solid #0056b3 !important;
            font-weight: 700;
        }

        .devolucion-tabs > li > a i {
            margin-right: 8px;
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
            body {
                background: #fff !important;
                margin: 0 !important;
                padding: 10px !important;
            }
            body * {
                visibility: hidden;
            }
            #printableComprobante, #printableComprobante * {
                visibility: visible !important;
            }
            #printableComprobante {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 15px !important;
                background: #fff !important;
            }
            .modal, .modal-dialog, .modal-content {
                position: static !important;
                border: none !important;
                box-shadow: none !important;
                margin: 0 !important;
                padding: 0 !important;
                width: 100% !important;
            }
            .modal-backdrop, .modal-header, .modal-footer {
                display: none !important;
            }
            #printableComprobante table.table-bordered {
                border: 1px solid #000 !important;
            }
            #printableComprobante table.table-bordered th,
            #printableComprobante table.table-bordered td {
                border: 1px solid #000 !important;
                color: #000 !important;
                background-color: transparent !important;
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
            <p class="subtitle" style="margin-bottom: 20px;">Control y registro de devoluciones por Venta o por Orden de Producción con derivación a control de calidad y material rechazado</p>

            <div id="contenedor-vistas">
                <!-- VISTA 1: LISTADO DE DEVOLUCIONES -->
                <div id="vista-listado">
                    <div class="row form-group">
                        <div class="col-sm-12">
                            <div aria-label="Acciones de devoluciones" style="display: flex; gap: 8px; flex-wrap: wrap;">
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
                            <div class="col-sm-4">
                                <label for="filtroBusqueda">Buscar</label>
                                <input type="text" id="filtroBusqueda" class="form-control" placeholder="Buscar por Código DEV, Factura, OP, Producto...">
                            </div>
                            <div class="col-sm-3">
                                <label for="filtroTipoOrigen">Tipo de Devolución</label>
                                <select id="filtroTipoOrigen" class="form-control">
                                    <option value="">Todos los orígenes</option>
                                    <option value="venta">Devolución de Venta (Factura)</option>
                                    <option value="orden_produccion">Devolución de Orden de Prod.</option>
                                </select>
                            </div>
                            <div class="col-sm-2">
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
                                    <th>Origen / Doc.</th>
                                    <th>Producto / Talla</th>
                                    <th style="text-align: center;">Cantidad</th>
                                    <th>Motivo Devolución</th>
                                    <th>Estado / Efecto</th>
                                    <th style="text-align: center; width: 1%; white-space: nowrap;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="devolucionesTableBody">
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 30px; color: #64748b;">
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
                            <!-- SELECTOR DE TIPO DE DEVOLUCIÓN: PESTAÑAS (TABS) -->
                            <div class="form-group" style="margin-bottom: 20px;">
                                <label style="font-weight: bold; color: #1e293b; font-size: 14.5px; margin-bottom: 12px; display: block;">
                                    Seleccione el Origen de la Devolución <span style="color: red;">*</span>
                                </label>
                                <ul class="nav nav-tabs nav-justified devolucion-tabs" role="tablist">
                                    <li role="presentation" class="active" id="tabItemVenta">
                                        <a href="javascript:void(0);" id="tabBtnVenta" role="tab">
                                             Devolución por Venta
                                        </a>
                                    </li>
                                    <li role="presentation" id="tabItemOP">
                                        <a href="javascript:void(0);" id="tabBtnOP" role="tab">
                                             Devolución por Orden de Producción
                                        </a>
                                    </li>
                                </ul>
                                <input type="hidden" name="tipo_devolucion" id="inputTipoDevolucion" value="venta">
                            </div>

                            <!-- SECCIÓN ORIGEN: VENTA -->
                            <div id="seccionDevolucionVenta">
                                <div class="row">
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="selectVentaDevolver" style="font-weight: bold; color: #1e293b; font-size: 14px;">
                                                
                                            Venta / Factura <span style="color: red;">*</span>
                                            </label>
                                            <select id="selectVentaDevolver" name="venta_id" class="form-control" style="width: 100%;">
                                                <option value="">-- Buscar por Factura (ej. FACT-0001) o Fecha --</option>
                                                <?php foreach ($ventas as $vnt): ?>
                                                    <option value="<?php echo $vnt['id']; ?>"
                                                            data-factura="<?php echo htmlspecialchars($vnt['numero_factura']); ?>"
                                                            data-fecha="<?php echo htmlspecialchars($vnt['fecha']); ?>"
                                                            data-total="<?php echo htmlspecialchars($vnt['total']); ?>">
                                                        <?php echo htmlspecialchars($vnt['numero_factura'] . ' [' . date('d/m/Y', strtotime($vnt['fecha'])) . ' - $' . number_format((float)$vnt['total'], 2) . ']'); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <small class="text-muted" style="display: block; margin-top: 5px;">
                                                <i class="fas fa-info-circle"></i> Seleccione la venta correspondiente a los productos que se devolverán.
                                            </small>
                                        </div>
                                    </div>
                                    <div class="col-sm-6">
                                        <div class="form-group">
                                            <label for="selectProductoVenta" style="font-weight: bold; color: #1e293b; font-size: 14px;">
                                            Producto de la Venta a Devolver <span style="color: red;">*</span>
                                            </label>
                                            <select id="selectProductoVenta" name="detalle_venta_id" class="form-control" style="width: 100%;" disabled>
                                                <option value="">-- Primero seleccione una venta --</option>
                                            </select>
                                            <small class="text-muted" style="display: block; margin-top: 5px;" id="helpSelectProdVenta">
                                                <i class="fas fa-info-circle"></i> Elija el artículo específico vendido que se devolverá.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- SECCIÓN ORIGEN: ORDEN DE PRODUCCIÓN -->
                            <div id="seccionDevolucionOP" style="display: none;">
                                <div class="form-group">
                                    <label for="selectOrdenDevolver" style="font-weight: bold; color: #1e293b; font-size: 14px;">
                                        <i class="fas fa-clipboard-list text-primary"></i> Orden de Producción <span style="color: red;">*</span>
                                    </label>
                                    <select id="selectOrdenDevolver" name="orden_produccion_id" class="form-control" style="width: 100%;">
                                        <option value="">-- Buscar por número de orden (ej. 01-AGOST) o producto --</option>
                                        <?php foreach ($ordenes as $ord): ?>
                                            <option value="<?php echo $ord['id']; ?>"
                                                    data-producto="<?php echo htmlspecialchars($ord['producto_nombre']); ?>"
                                                    data-talla="<?php echo htmlspecialchars($ord['talla_nombre']); ?>"
                                                    data-orden="<?php echo htmlspecialchars($ord['numero_orden']); ?>"
                                                    data-cantidad="<?php echo htmlspecialchars($ord['cantidad_a_producir']); ?>"
                                                    data-stock="<?php echo htmlspecialchars($ord['stock_inventario_fmt']); ?>"
                                                    data-fecha="<?php echo htmlspecialchars($ord['creado_en']); ?>">
                                                <?php echo htmlspecialchars($ord['numero_orden'] . ' — ' . $ord['producto_nombre'] . ' (' . $ord['talla_nombre'] . ') [Inventario: ' . $ord['stock_inventario_fmt'] . ' unds]'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="text-muted" style="display: block; margin-top: 5px;">
                                        <i class="fas fa-info-circle"></i> Seleccione la orden de producción a la cual pertenece el producto devuelto.
                                    </small>
                                </div>
                            </div>

                            <!-- Tarjeta Preview del Producto y Documento Origen -->
                            <div class="unit-preview-card" id="cardPreviewDevolucion">
                                <div id="previewVacio" style="text-align: center; color: #64748b; padding: 15px;">
                                    <i class="fas fa-box-open fa-2x" style="margin-bottom: 8px; color: #94a3b8;"></i>
                                    <div id="previewVacioTexto">Seleccione una venta y su producto (o una orden de producción) para ver los datos asociados.</div>
                                </div>

                                <div id="previewContenido" style="display: none;">
                                    <!-- <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px;">
                                        <div>
                                            <span class="badge-code" id="prevOrigenBadge">DOC-0001</span>
                                            <strong id="prevProductoNombre" style="margin-left: 8px; font-size: 15px; color: #0056b3;">Nombre Producto</strong>
                                        </div>
                                        <div>
                                            <span class="label label-info" id="prevBadgeTotal">Total: --</span>
                                        </div>
                                    </div> -->

                                    <div class="info-pill-grid">
                                        <div class="info-pill-item">
                                            <small>Talla</small>
                                            <span id="prevTalla">Única</span>
                                        </div>
                                        <div class="info-pill-item">
                                            <small id="prevPillCantLabel">Cantidad Vendida</small>
                                            <span id="prevPillCantValor">--</span>
                                        </div>
                                        <div class="info-pill-item" id="prevPillDisponibleContainer">
                                            <small>Disponible a Devolver</small>
                                            <span id="prevDisponibleValor" style="color: #16a34a;">--</span>
                                        </div>
                                        <div class="info-pill-item">
                                            <small id="prevPillFechaLabel">Fecha Registro</small>
                                            <span id="prevFechaDoc">--</span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Cantidad a Devolver -->
                            <div class="row">
                                <div class="col-sm-6">
                                    <div class="form-group">
                                        <label style="font-weight: bold; color: #1e293b;">Cantidad a Devolver <span style="color: red;">*</span></label>
                                        <input type="number" step="1" min="1" id="inputCantidad" name="cantidad" class="form-control" value="1" required>
                                        <small class="text-muted" id="helpCantidadTexto">Número de unidades devueltas que reingresarán al inventario.</small>
                                    </div>
                                </div>
                            </div>

                            <!-- MOTIVO ESCRITO POR EL USUARIO -->
                            <div class="form-group">
                                <label style="font-weight: bold;">Motivo de la Devolución <span style="color: red;">*</span></label>
                                <textarea id="inputMotivo" name="motivo" class="form-control" rows="3" placeholder="Escriba aquí el motivo o razón por la cual se devuelve el producto..." required></textarea>
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
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
                <h4 class="modal-title">Detalle de Devolución</h4>
            </div>
            <div class="modal-body" id="printableComprobante">
                <!-- Encabezado para impresión directa -->
                <div class="visible-print" style="text-align: center; margin-bottom: 15px;">
                    <img src="../assets/img/brand.png" alt="Inverclinik C.A." style="max-height: 75px; width: auto; object-fit: contain;">
                    <p style="margin: 2px 0 0 0; text-align: left; font-size: 11px; font-weight: bold;">RIF J-41173381-4</p>
                    <div style="border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 5px 0; margin-top: 8px;">
                        <strong style="font-size: 15px; text-transform: uppercase;">COMPROBANTE DE DEVOLUCIÓN</strong>
                    </div>
                </div>

                <table class="table table-bordered" style="margin-bottom: 0;">
                    <tbody>
                        <tr>
                            <th style="width: 35%; background-color: #f9f9f9;">Código</th>
                            <td><strong id="detCodigoDevolucion">--</strong></td>
                        </tr>
                        <tr>
                            <th style="background-color: #f9f9f9;">Fecha</th>
                            <td id="detFecha">--</td>
                        </tr>
                        <tr>
                            <th style="background-color: #f9f9f9;">Tipo de Devolución</th>
                            <td id="detTipoOrigenBadge">--</td>
                        </tr>
                        <tr>
                            <th id="detLabelOrigen" style="background-color: #f9f9f9;">Origen</th>
                            <td id="detOrden">--</td>
                        </tr>
                        <tr>
                            <th style="background-color: #f9f9f9;">Producto</th>
                            <td id="detProducto">--</td>
                        </tr>
                        <tr>
                            <th style="background-color: #f9f9f9;">Talla</th>
                            <td id="detTalla">--</td>
                        </tr>
                        <tr>
                            <th style="background-color: #f9f9f9;">Cantidad</th>
                            <td id="detCantidad">--</td>
                        </tr>
                        <tr>
                            <th style="background-color: #f9f9f9;">Motivo</th>
                            <td id="detMotivo">--</td>
                        </tr>
                        <tr>
                            <th style="background-color: #f9f9f9;">Estado / Inventario</th>
                            <td id="detEfectoInventario">--</td>
                        </tr>
                        <tr id="detContenedorObservaciones">
                            <th style="background-color: #f9f9f9;">Observaciones</th>
                            <td id="detObservaciones">--</td>
                        </tr>
                    </tbody>
                </table>

                <!-- Firmas y pie para impresión directa -->
                <div class="visible-print" style="margin-top: 45px;">
                    <table style="width: 100%; border: none;">
                        <tr>
                            <td style="width: 45%; border: none; text-align: center;">
                                <div style="border-top: 1px solid #000; padding-top: 5px; font-size: 12px;">
                                    <strong>Entregado Por</strong><br>
                                    Firma y Cédula
                                </div>
                            </td>
                            <td style="width: 10%; border: none;"></td>
                            <td style="width: 45%; border: none; text-align: center;">
                                <div style="border-top: 1px solid #000; padding-top: 5px; font-size: 12px;">
                                    <strong>Recibido / Control de Calidad</strong><br>
                                    Inverclinik C.A.
                                </div>
                            </td>
                        </tr>
                    </table>
                    <div style="margin-top: 35px; text-align: center; font-size: 10.5px; color: #555; border-top: 1px solid #ddd; padding-top: 8px;">
                        <p style="margin: 2px 0;">Inverclinik C.A. | RIF J-41173381-4 | Calle Gradizco, Galpón Nro 1, San Pablo, Turmero, Estado Aragua</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cerrar</button>
                <a href="#" target="_blank" class="btn btn-primary" id="btnImprimirModal">
                    <i class="fas fa-print"></i> Imprimir Formato
                </a>
            </div>
        </div>
    </div>
</div>

<script src="../assets/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    let paginaActual = 1;
    let tipoDevolucionActual = 'venta'; // 'venta' o 'orden_produccion'

    function formatearFecha(str) {
        if (!str) return '--';
        const parts = str.split(' ')[0].split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }
        return str;
    }

    function formatearFechaHora(str) {
        if (!str) return '--';
        const p = str.split(' ');
        if (p.length >= 1) {
            const dp = p[0].split('-');
            if (dp.length === 3) {
                let timeStr = '';
                if (p[1]) {
                    const tp = p[1].split(':');
                    let h = parseInt(tp[0], 10);
                    const m = tp[1] || '00';
                    const ampm = h >= 12 ? 'PM' : 'AM';
                    h = h % 12;
                    h = h ? h : 12;
                    const hStr = h < 10 ? '0' + h : h;
                    timeStr = ` ${hStr}:${m} ${ampm}`;
                }
                return `${dp[2]}/${dp[1]}/${dp[0]}${timeStr}`;
            }
        }
        return str;
    }

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

    $('#filtroFechaDesde, #filtroFechaHasta, #filtroTipoOrigen').on('change', function() {
        paginaActual = 1;
        cargarDevoluciones();
    });

    $('#btnLimpiarFiltros').on('click', function() {
        $('#filtroBusqueda').val('');
        $('#filtroTipoOrigen').val('');
        $('#filtroFechaDesde').val('');
        $('#filtroFechaHasta').val('');
        paginaActual = 1;
        cargarDevoluciones();
    });

    // Conmutador de Tipo de Devolución por Pestañas (Tabs)
    $('#tabBtnVenta').on('click', function(e) {
        e.preventDefault();
        setTipoDevolucion('venta');
    });

    $('#tabBtnOP').on('click', function(e) {
        e.preventDefault();
        setTipoDevolucion('orden_produccion');
    });

    function setTipoDevolucion(tipo) {
        tipoDevolucionActual = tipo;
        $('#inputTipoDevolucion').val(tipo);
        if (tipo === 'venta') {
            $('#tabItemVenta').addClass('active');
            $('#tabItemOP').removeClass('active');
            $('#seccionDevolucionVenta').show();
            $('#seccionDevolucionOP').hide();
            $('#helpCantidadTexto').text('Número de unidades devueltas (no puede exceder las unidades disponibles de la venta).');
            $('#previewVacioTexto').text('Seleccione una venta y su producto para ver los datos asociados.');
        } else {
            $('#tabItemOP').addClass('active');
            $('#tabItemVenta').removeClass('active');
            $('#seccionDevolucionVenta').hide();
            $('#seccionDevolucionOP').show();
            $('#helpCantidadTexto').text('Número de unidades devueltas que reingresarán al inventario.');
            $('#previewVacioTexto').text('Seleccione una orden de producción para ver los datos asociados.');
        }
        limpiarPreview();
    }

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

    // Inicializar Select2 en el selector de ventas
    $('#selectVentaDevolver').select2({
        placeholder: '-- Buscar por Factura (ej. FACT-0001) o Fecha --',
        allowClear: true,
        width: '100%',
        ajax: {
            url: 'devoluciones_data.php',
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return {
                    action: 'buscar_ventas_select2',
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

    // Al seleccionar una venta en Select2
    $('#selectVentaDevolver').on('select2:select change', function() {
        const ventaId = $(this).val();
        if (ventaId) {
            cargarProductosDeVenta(ventaId);
        } else {
            limpiarProductosVenta();
            limpiarPreview();
        }
    });

    $('#selectVentaDevolver').on('select2:unselect', function() {
        limpiarProductosVenta();
        limpiarPreview();
    });

    function cargarProductosDeVenta(ventaId) {
        $('#selectProductoVenta').prop('disabled', true).html('<option value="">Cargando productos de la venta...</option>');

        $.ajax({
            url: 'devoluciones_data.php',
            type: 'GET',
            data: { action: 'obtener_venta_info', venta_id: ventaId },
            dataType: 'json',
            success: function(res) {
                if (res.success && res.venta) {
                    const v = res.venta;

                    const items = res.items || [];
                    if (items.length === 0) {
                        $('#selectProductoVenta').html('<option value="">No hay productos registrados en esta venta</option>');
                        limpiarPreview();
                        return;
                    }

                    let optionsHtml = '<option value="">-- Seleccione el producto a devolver --</option>';
                    let itemSeleccionable = null;

                    items.forEach(function(it) {
                        const cantDisp = parseFloat(it.cantidad_disponible || 0);
                        const disabledAttr = cantDisp <= 0 ? 'disabled' : '';
                        const dispText = cantDisp <= 0 ? ' (Ya devuelto en su totalidad)' : ` (Disp. para devolver: ${cantDisp} unds)`;

                        optionsHtml += `
                            <option value="${it.detalle_venta_id}" 
                                    data-producto="${it.producto_nombre}"
                                    data-talla="${it.talla_nombre}"
                                    data-vendida="${it.cantidad_vendida}"
                                    data-disponible="${cantDisp}"
                                    data-precio="${it.precio_unitario}"
                                    data-factura="${v.numero_factura}"
                                    data-fecha="${v.fecha}"
                                    ${disabledAttr}>
                                ${it.producto_nombre} (${it.talla_nombre}) — Vendidas: ${it.cantidad_vendida}${dispText}
                            </option>
                        `;

                        if (cantDisp > 0 && !itemSeleccionable) {
                            itemSeleccionable = it;
                        }
                    });

                    $('#selectProductoVenta').html(optionsHtml).prop('disabled', false);

                    // Si hay exactamente un artículo disponible, preseleccionarlo
                    if (items.length === 1 && itemSeleccionable) {
                        $('#selectProductoVenta').val(itemSeleccionable.detalle_venta_id).trigger('change');
                    }
                } else {
                    limpiarProductosVenta();
                    limpiarPreview();
                    Swal.fire('Error', res.message || 'No se pudo obtener información de la venta.', 'error');
                }
            },
            error: function() {
                limpiarProductosVenta();
                limpiarPreview();
            }
        });
    }

    function limpiarProductosVenta() {
        $('#selectProductoVenta').html('<option value="">-- Primero seleccione una venta --</option>').prop('disabled', true);
    }

    // Al seleccionar producto específico de la venta
    $('#selectProductoVenta').on('change', function() {
        const detId = $(this).val();
        if (!detId) {
            limpiarPreview();
            return;
        }

        const opt = $(this).find('option:selected');
        const prodNombre = opt.data('producto');
        const tallaNombre = opt.data('talla');
        const cantVendida = parseFloat(opt.data('vendida') || 0);
        const cantDisponible = parseFloat(opt.data('disponible') || 0);
        const numFactura = opt.data('factura') || 'FACT';
        const fechaVenta = opt.data('fecha');

        if (cantDisponible <= 0) {
            Swal.fire('Atención', 'Este producto ya ha sido devuelto en su totalidad para esta factura.', 'warning');
            limpiarPreview();
            return;
        }

        // Mostrar Preview de Venta
        $('#prevOrigenBadge').html(`<i class="fas fa-shopping-cart"></i> ${numFactura}`);
        $('#prevProductoNombre').text(prodNombre);
        $('#prevBadgeTotal').text(`Vendidas: ${cantVendida}`);
        $('#prevTalla').text(tallaNombre || 'Única');
        $('#prevPillCantLabel').text('Cantidad Facturada');
        $('#prevPillCantValor').text(`${cantVendida} unds`);
        $('#prevPillDisponibleContainer').show();
        $('#prevDisponibleValor').text(`${cantDisponible} unds`);
        $('#prevPillFechaLabel').text('Fecha Venta');
        $('#prevFechaDoc').text(formatearFecha(fechaVenta));

        // Configurar límite del input cantidad
        $('#inputCantidad').attr('max', cantDisponible).val(Math.min(1, cantDisponible));
        $('#helpCantidadTexto').html(`Máximo <strong>${cantDisponible}</strong> unidad(es) disponibles para devolver de esta venta.`);

        $('#previewVacio').hide();
        $('#previewContenido').show();
        $('#cardPreviewDevolucion').addClass('active');
        $('#btnGuardarDevolucion').prop('disabled', false);
    });

    // Al seleccionar una orden en Select2
    $('#selectOrdenDevolver').on('select2:select change', function() {
        const ordenId = $(this).val();
        if (ordenId) {
            const data = $(this).select2('data')[0];
            if (data && data.producto_nombre) {
                mostrarPreviewOrden(data);
            } else {
                obtenerInfoOrden(ordenId);
            }
        } else {
            limpiarPreview();
        }
    });

    $('#selectOrdenDevolver').on('select2:unselect', function() {
        limpiarPreview();
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
                    limpiarPreview();
                    Swal.fire('Error', res.message || 'No se pudo obtener información de la orden.', 'error');
                }
            },
            error: function() {
                limpiarPreview();
            }
        });
    }

    function mostrarPreviewOrden(o) {
        $('#prevOrigenBadge').html(`<i class="fas fa-industry"></i> ${o.numero_orden || ('OP #' + o.id)}`);
        $('#prevProductoNombre').text(o.producto_nombre || 'Producto');
        const stockTxt = o.stock_inventario_fmt ? `${o.stock_inventario_fmt} unds` : (o.stock_inventario !== undefined ? `${o.stock_inventario} unds` : '--');
        $('#prevBadgeTotal').html(`Inventario: <strong>${stockTxt}</strong>`);
        $('#prevTalla').text(o.talla_nombre || 'Única');
        $('#prevPillCantLabel').text('Cantidad Producida');
        $('#prevPillCantValor').text(`${o.cantidad_a_producir || '--'} unds`);
        $('#prevPillDisponibleContainer').show();
        $('#prevPillDisponibleContainer small').text('En Inventario');
        $('#prevDisponibleValor').text(stockTxt);
        $('#prevPillFechaLabel').text('Fecha Creación');
        $('#prevFechaDoc').text(formatearFecha(o.creado_en));

        $('#inputCantidad').removeAttr('max');
        $('#helpCantidadTexto').text('Número de unidades devueltas que reingresarán al inventario.');

        $('#previewVacio').hide();
        $('#previewContenido').show();
        $('#cardPreviewDevolucion').addClass('active');
        $('#btnGuardarDevolucion').prop('disabled', false);
    }

    function limpiarPreview() {
        $('#previewContenido').hide();
        $('#previewVacio').show();
        $('#cardPreviewDevolucion').removeClass('active');
        $('#btnGuardarDevolucion').prop('disabled', true);
        $('#inputCantidad').removeAttr('max');
    }

    function resetearFormularioDevolucion() {
        $('#formNuevaDevolucion')[0].reset();
        $('#selectVentaDevolver').val(null).trigger('change');
        $('#selectOrdenDevolver').val(null).trigger('change');
        limpiarProductosVenta();
        $('#inputCantidad').val('1');
        setTipoDevolucion('venta');
        limpiarPreview();
    }

    // Guardar Devolución
    $('#formNuevaDevolucion').on('submit', function(e) {
        e.preventDefault();

        const postData = {
            action: 'crear_devolucion',
            tipo_devolucion: tipoDevolucionActual,
            motivo: $.trim($('#inputMotivo').val()),
            observaciones: $('#inputObservaciones').val(),
            cantidad: parseFloat($('#inputCantidad').val())
        };

        if (isNaN(postData.cantidad) || postData.cantidad <= 0) {
            Swal.fire('Atención', 'Ingrese una cantidad válida mayor a 0.', 'warning');
            $('#inputCantidad').focus();
            return;
        }

        if (tipoDevolucionActual === 'venta') {
            const ventaId = $('#selectVentaDevolver').val();
            const detId = $('#selectProductoVenta').val();
            if (!ventaId) {
                Swal.fire('Atención', 'Debe seleccionar una venta o factura.', 'warning');
                return;
            }
            if (!detId) {
                Swal.fire('Atención', 'Debe seleccionar el producto de la venta a devolver.', 'warning');
                return;
            }

            const maxDisp = parseFloat($('#inputCantidad').attr('max'));
            if (!isNaN(maxDisp) && postData.cantidad > maxDisp) {
                Swal.fire('Atención', `La cantidad a devolver (${postData.cantidad}) no puede exceder las unidades disponibles (${maxDisp}).`, 'warning');
                $('#inputCantidad').focus();
                return;
            }

            postData.venta_id = ventaId;
            postData.detalle_venta_id = detId;

        } else {
            const ordenId = $('#selectOrdenDevolver').val();
            if (!ordenId) {
                Swal.fire('Atención', 'Debe seleccionar una orden de producción.', 'warning');
                return;
            }
            postData.orden_produccion_id = ordenId;
        }

        if (!postData.motivo) {
            Swal.fire('Atención', 'Por favor escriba el motivo de la devolución.', 'warning');
            $('#inputMotivo').focus();
            return;
        }

        $('#btnGuardarDevolucion').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Guardando...');

        $.ajax({
            url: 'devoluciones_data.php',
            type: 'POST',
            data: postData,
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
                tipo_origen: $('#filtroTipoOrigen').val(),
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
                        <div style="font-size: 13px;">Utilice el botón "Registrar Devolución" para procesar el retorno de un producto de venta o de producción.</div>
                    </td>
                </tr>
            `);
            return;
        }

        let html = '';
        lista.forEach(function(d) {
            const fechaFormat = formatearFechaHora(d.fecha);
            const motivoTexto = d.motivo || '-';
            const cantFormat = parseFloat(d.cantidad || 1);
            const estado = d.estado || 'pendiente';
            const prodNombre = d.producto_nombre ? (d.producto_nombre + ' (' + (d.talla_nombre || 'Única') + ')') : 'Producto';

            // Origen Badge (Venta vs OP)
            let origenHtml = '';
            if (d.tipo_origen === 'venta') {
                origenHtml = `<span class="badge" style="background-color: #fef3c7; color: #92400e; border: 1px solid #fde68a; padding: 4px 8px; font-weight: 600;" title="Devolución de Venta"><i class="fas fa-shopping-cart"></i> ${d.numero_factura}</span>`;
            } else {
                origenHtml = `<span class="badge" style="background-color: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; padding: 4px 8px; font-weight: 600;" title="Devolución de Orden de Producción"><i class="fas fa-industry"></i> ${d.numero_orden}</span>`;
            }

            let estadoBadge = '';
            let accionesHtml = '';

            if (estado === 'pendiente') {
                estadoBadge = `<span class="label label-warning" style="font-size: 11.5px; background-color: #f59e0b; color: white; padding: 4px 7px; border-radius: 4px;"><i class="fas fa-clock"></i> Pendiente</span>`;
                accionesHtml = `
                    <button type="button" class="btn btn-sm btn-success btn-aprobar-devolucion" 
                            data-id="${d.id}" 
                            data-codigo="${d.codigo_devolucion}" 
                            data-producto="${prodNombre}" 
                            data-cantidad="${cantFormat}" 
                            title="Validar en Buen Estado y Reingresar al Stock" 
                            style=
                            "margin-right: 3px; font-weight: 600; padding: 4px 8px;">
                        <i class="fas fa-check"></i> Buen Estado
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-rechazar-devolucion" 
                            data-id="${d.id}" 
                            data-codigo="${d.codigo_devolucion}" 
                            data-producto="${prodNombre}" 
                            data-cantidad="${cantFormat}" 
                            data-tipo-origen="${d.tipo_origen}" 
                            title="Rechazar Devolución" 
                            style="margin-right: 3px; font-weight: 600; padding: 4px 8px;">
                        <i class="fas fa-ban"></i> Rechazar
                    </button>
                    <button type="button" class="btn btn-sm btn-info btnVerDetalleDevolucion" data-id="${d.id}" title="Ver Comprobante / Detalle" style="padding: 4px 8px;">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="../formatos/ver_devolucion.php?id=${d.id}" target="_blank" class="btn btn-sm btn-default" title="Imprimir Formato" style="padding: 4px 8px; margin-left: 2px;">
                        <i class="fas fa-print"></i>
                    </a>
                `;
            } else if (estado === 'aprobado') {
                estadoBadge = `<span class="label label-success" style="font-size: 11.5px; background-color: #10b981; color: white; padding: 4px 7px; border-radius: 4px;"><i class="fas fa-check-circle"></i> +${cantFormat} En Stock</span>`;
                accionesHtml = `
                    <button type="button" class="btn btn-sm btn-info btnVerDetalleDevolucion" data-id="${d.id}" title="Ver Comprobante / Detalle" style="padding: 4px 8px;">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="../formatos/ver_devolucion.php?id=${d.id}" target="_blank" class="btn btn-sm btn-default" title="Imprimir Formato" style="padding: 4px 8px; margin-left: 2px;">
                        <i class="fas fa-print"></i>
                    </a>
                `;
            } else {
                estadoBadge = `<span class="label label-danger" style="font-size: 11.5px; background-color: #ef4444; color: white; padding: 4px 7px; border-radius: 4px;"><i class="fas fa-ban"></i> Rechazado</span>`;
                accionesHtml = `
                    <button type="button" class="btn btn-sm btn-info btnVerDetalleDevolucion" data-id="${d.id}" title="Ver Comprobante / Detalle" style="padding: 4px 8px;">
                        <i class="fas fa-eye"></i>
                    </button>
                    <a href="../formatos/ver_devolucion.php?id=${d.id}" target="_blank" class="btn btn-sm btn-default" title="Imprimir Formato" style="padding: 4px 8px; margin-left: 2px;">
                        <i class="fas fa-print"></i>
                    </a>
                `;
            }

            html += `
                <tr>
                    <td><span class="badge-code">${d.codigo_devolucion}</span></td>
                    <td>${fechaFormat}</td>
                    <td>${origenHtml}</td>
                    <td>
                        <div><strong>${d.producto_nombre || '-'}</strong></div>
                        <small class="text-muted">Talla: ${d.talla_nombre || 'Única'}</small>
                    </td>
                    <td style="text-align: center;"><strong>${cantFormat}</strong> <small class="text-muted">unds</small></td>
                    <td><span title="${motivoTexto}" style="display: block; max-width: 230px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500;">${motivoTexto}</span></td>
                    <td>${estadoBadge}</td>
                    <td style="text-align: center; white-space: nowrap; width: 1%;">
                        ${accionesHtml}
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

        // Event listener para validar en Buen Estado
        $('.btn-aprobar-devolucion').on('click', function() {
            const id = $(this).data('id');
            const codigo = $(this).data('codigo') || 'DEV';
            const producto = $(this).data('producto') || 'producto';
            const cantidad = $(this).data('cantidad') || 1;

            Swal.fire({
                title: '¿Validar en Buen Estado?',
                html: `¿Desea validar la devolución <b>${codigo}</b> (${cantidad} de <i>${producto}</i>) en <b>Buen Estado</b>?<br><br><small class="text-muted">El producto reingresará inmediatamente al stock disponible del inventario.</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10b981',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-check"></i> Sí, Buen Estado (Stock)',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Reingresando producto al inventario...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.ajax({
                        url: 'devoluciones_data.php',
                        type: 'POST',
                        data: {
                            action: 'aprobar_buen_estado',
                            devolucion_id: id
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Producto Validado!',
                                    text: res.message
                                });
                                cargarDevoluciones();
                            } else {
                                Swal.fire('Error', res.message || 'No se pudo validar la devolución.', 'error');
                            }
                        },
                        error: function(xhr) {
                            let msg = 'Error en el servidor al validar la devolución.';
                            try {
                                const err = JSON.parse(xhr.responseText);
                                if (err.message) msg = err.message;
                            } catch(e) {}
                            Swal.fire('Error', msg, 'error');
                        }
                    });
                }
            });
        });

        // Event listener para Rechazar Devolución
        $('.btn-rechazar-devolucion').on('click', function() {
            const id = $(this).data('id');
            const codigo = $(this).data('codigo') || 'DEV';
            const producto = $(this).data('producto') || 'producto';
            const cantidadRech = $(this).data('cantidad') || 1;
            const tipoOrigenRech = $(this).data('tipo-origen') || '';

            let mensajeRechazo = '';
            if (tipoOrigenRech === 'orden_produccion') {
                mensajeRechazo = `¿Está seguro de rechazar la devolución <b>${codigo}</b> (${cantidadRech} de <i>${producto}</i>)?<br><br><span class="text-danger" style="font-weight:600;"><i class="fas fa-exclamation-triangle"></i> Al rechazar una devolución de Orden de Producción, se restarán <b>${cantidadRech}</b> unidad(es) del inventario de producto terminado.</span>`;
            } else {
                mensajeRechazo = `¿Está seguro de rechazar la devolución <b>${codigo}</b> (${producto})?<br><br><small class="text-muted">El producto no ingresará al inventario y se marcará como descartado/rechazado.</small>`;
            }

            Swal.fire({
                title: '¿Rechazar Devolución?',
                html: mensajeRechazo,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: '<i class="fas fa-ban"></i> Sí, Rechazar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        title: 'Procesando...',
                        text: 'Registrando rechazo...',
                        allowOutsideClick: false,
                        didOpen: () => { Swal.showLoading(); }
                    });

                    $.ajax({
                        url: 'devoluciones_data.php',
                        type: 'POST',
                        data: {
                            action: 'rechazar_material',
                            devolucion_id: id
                        },
                        dataType: 'json',
                        success: function(res) {
                            if (res.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Devolución Rechazada',
                                    text: res.message
                                });
                                cargarDevoluciones();
                            } else {
                                Swal.fire('Error', res.message || 'No se pudo rechazar la devolución.', 'error');
                            }
                        },
                        error: function(xhr) {
                            let msg = 'Error en el servidor al rechazar la devolución.';
                            try {
                                const err = JSON.parse(xhr.responseText);
                                if (err.message) msg = err.message;
                            } catch(e) {}
                            Swal.fire('Error', msg, 'error');
                        }
                    });
                }
            });
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
                    const estado = d.estado || 'pendiente';

                    $('#detCodigoDevolucion').text(d.codigo_devolucion);
                    $('#detFecha').text(formatearFechaHora(d.fecha));
                    $('#detProducto').text(d.producto_nombre || '--');
                    $('#detTalla').text(d.talla_nombre || 'Única');
                    $('#detCantidad').text(cant + '');

                    if (d.tipo_origen === 'venta') {
                        $('#detLabelOrigen').text('Factura / Venta');
                        $('#detOrden').text(d.numero_factura || '--');
                        $('#detTipoOrigenBadge').text('Devolución de Venta');
                    } else {
                        $('#detLabelOrigen').text('Orden de Producción');
                        $('#detOrden').text(d.numero_orden || '--');
                        $('#detTipoOrigenBadge').text('Devolución de Orden de Producción');
                    }

                    $('#detMotivo').text(d.motivo || '--');

                    if (estado === 'pendiente') {
                        $('#detEfectoInventario').text('Pendiente');
                    } else if (estado === 'aprobado') {
                        $('#detEfectoInventario').text(`Aprobado (+${cant} reingresado al stock)`);
                    } else {
                        if (d.tipo_origen === 'orden_produccion') {
                            $('#detEfectoInventario').text(`Rechazado (-${cant} restado del stock)`);
                        } else {
                            $('#detEfectoInventario').text('Rechazado (Sin reingreso a stock)');
                        }
                    }

                    if (d.observaciones) {
                        $('#detObservaciones').text(d.observaciones);
                        $('#detContenedorObservaciones').show();
                    } else {
                        $('#detContenedorObservaciones').hide();
                    }

                    $('#btnImprimirModal').attr('href', '../formatos/ver_devolucion.php?id=' + d.id);
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
