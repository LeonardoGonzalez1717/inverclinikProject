<?php 
require_once "../template/header.php"; 

$checkRecetas = $conn->query("SELECT COUNT(*) as count FROM recetas");
$row = $checkRecetas->fetch_assoc();
if ($row['count'] == 0) {
    $sqlInsertRecetas = "
        INSERT IGNORE INTO recetas (producto_id, rango_tallas_id, tipo_produccion_id, observaciones)
        SELECT DISTINCT producto_id, rango_tallas_id, tipo_produccion_id, NULL
        FROM recetas_productos
    ";
    $conn->query($sqlInsertRecetas);
}

$sqlRecetas = "
    SELECT 
        r.id, 
        p.nombre AS producto_nombre, 
        rt.nombre_rango AS rango_tallas_nombre,
        r.producto_id,
        r.rango_tallas_id,
        r.tipo_produccion_id,
        COALESCE(SUM(rp.cantidad_por_unidad * i.costo_unitario), 0) AS costo_por_unidad
    FROM recetas r
    INNER JOIN productos p ON r.producto_id = p.id
    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
    LEFT JOIN recetas_productos rp ON rp.producto_id = r.producto_id 
        AND rp.rango_tallas_id = r.rango_tallas_id 
        AND rp.tipo_produccion_id = r.tipo_produccion_id
    LEFT JOIN insumos i ON rp.insumo_id = i.id
    GROUP BY r.id, r.producto_id, r.rango_tallas_id, r.tipo_produccion_id
    ORDER BY p.nombre, rt.nombre_rango
";
$resultRecetas = $conn->query($sqlRecetas);
$recetas = [];
if ($resultRecetas) {
    while ($row = $resultRecetas->fetch_assoc()) {
        $recetas[] = $row;
    }
}

$chkCostoTaller = $conn->query("SHOW COLUMNS FROM talleres LIKE 'costo'");
if (!$chkCostoTaller || $chkCostoTaller->num_rows === 0) {
    $conn->query("ALTER TABLE talleres ADD COLUMN costo DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER nombre");
}

$talleres_disponibles = [];
$resTalleres = $conn->query("SELECT id, nombre, costo FROM talleres WHERE activo = 1 ORDER BY nombre ASC");
if ($resTalleres) {
    while($t = $resTalleres->fetch_assoc()) { $talleres_disponibles[] = $t; }
}

$categorias = [];
$resCat = $conn->query("SELECT DISTINCT categoria FROM productos WHERE categoria IS NOT NULL AND categoria != ''");
if ($resCat) {
    while($c = $resCat->fetch_assoc()) { $categorias[] = $c['categoria']; }
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
    <title>Órdenes de Producción</title>

    <style>
        /* Tabla */
        .orders-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .orders-table th {
            background-color: #0056b3;
            color: white;
            font-weight: bold;
            padding: 14px;
            text-align: left;
        }

        .orders-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .orders-table tr:nth-child(even) {
            background-color: #f2f7ff;
        }

        .orders-table tr:hover {
            background-color: #e7f3ff;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        .hidden {
            display: none;
        }

        .btn-volver { 
            background: #6c757d; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 5px; 
            cursor: pointer; 
            margin-bottom: 15px; 
        }

        /* NUEVO: Estilos estéticos para las secciones de los Talleres dentro del Modal */
        .seccion-taller-card {
            background: #f8f9fa;
            border-left: 4px solid #6c757d;
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 0 8px 8px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .seccion-taller-card.activa {
            border-left-color: #0275d8;
            background: #f0f7ff;
        }
        .seccion-taller-card.completada {
            border-left-color: #5cb85c;
            background: #f4faf4;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Órdenes de Producción</h2>
                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div aria-label="Acciones de cotización">
                                    <button class="btn btn-success" id="btn-ir-crear" style="margin-bottom: 0px !important;" title="Crear Nueva Orden de Producción" data-toggle="tooltip">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-info" id="btn-toggle-filtros" title="Filtros" data-toggle="tooltip">
                                        <i class="fas fa-filter"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-sm-4">
                                    <label for="filtro-producto">Producto o N° orden</label>
                                    <input type="text" id="filtro-producto" class="form-control clase-filtro" placeholder="Nombre o N° (ej. 5-AGOST-26)">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-categoria">Categoría</label>
                                    <select id="filtro-categoria" class="form-control clase-filtro">
                                        <option value="">Todas las categorías</option>
                                        <?php foreach($categorias as $cat): ?>
                                            <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-estado-orden">Estado de Orden</label>
                                    <select id="filtro-estado-orden" class="form-control clase-filtro">
                                        <option value="">Todos los estados</option>
                                        <option value="pendiente">Pendiente</option>
                                        <option value="en_proceso">En Proceso</option>
                                        <option value="taller">En Taller</option> <option value="revision">En Revisión</option> <option value="finalizado">Finalizado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <label for="filtro-desde">Fecha Inicio (Desde)</label>
                                    <input type="date" id="filtro-desde" class="form-control clase-filtro">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-hasta">Fecha Inicio (Hasta)</label>
                                    <input type="date" id="filtro-hasta" class="form-control clase-filtro">
                                </div>
                                <div class="col-sm-4" style="margin-top: 25px;">
                                    <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros">
                                        <i class="fas fa-eraser"></i> Limpiar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="table-container">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>N° Orden</th>
                                        <th>Producto</th>
                                        <th>Talla</th>
                                        <th>Categoría</th>
                                        <th>Cantidad</th>
                                        <th>Costo por Unidad</th>
                                        <th>Talleres</th>
                                        <th>Costo Total</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                        <th>Estatus</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                        <div id="paginacion-orden-produccion"></div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <button class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>

                        <form id="form-crear" novalidate>
                            <div class="row form-group" id="contenedor-numero-orden" style="display: none;">
                                <div class="col-sm-6">
                                    <label class="form-label">N° de orden</label>
                                    <input type="text" id="numero_orden" class="form-control" readonly>
                                </div>
                            </div>
                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Guia de corte <span style="color: red;">*</span></label>
                                    <select name="receta_id" id="receta_id" class="form-control">
                                        <option value=""></option>
                                        <?php foreach ($recetas as $r): ?>
                                            <option value="<?php echo htmlspecialchars($r['id']); ?>" 
                                                    data-costo="<?php echo htmlspecialchars($r['costo_por_unidad'] ?? 0); ?>"
                                                    data-rango-tallas-id="<?php echo htmlspecialchars($r['rango_tallas_id']); ?>">
                                                <?php echo htmlspecialchars($r['producto_nombre'] . ' - ' . $r['rango_tallas_nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Cantidad a Producir</label>
                                    <input type="number" step="1" min="1" name="cantidad_a_producir" id="cantidad_a_producir" class="form-control" required>
                                </div>
                            </div>

                            <div class="row form-group" id="contenedor-talla" style="display: none;">
                                <div class="col-sm-6">
                                    <label class="form-label">Talla a producir <span style="color: red;">*</span></label>
                                    <select name="talla_id" id="talla_id" class="form-control">
                                        <option value="">Seleccione la talla</option>
                                    </select>
                                    <small class="text-muted">Las tallas dependen del rango del producto</small>
                                </div>
                            </div>

                            <!-- SECCIÓN NUEVA: TALLERES CON COSTO -->
                            <div class="form-group" style="background: #f8f9fa; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 18px;">
                                <label class="form-label" style="font-weight: 700; color: #0056b3; margin-bottom: 6px; display: block;">
                                    <i class="fas fa-warehouse"></i> Talleres Involucrados en la Producción
                                </label>
                                <p class="text-muted" style="font-size: 13px; margin-bottom: 12px;">
                                    Marque los talleres por los que pasará esta orden. El costo de cada taller es por unidad y se multiplica por la cantidad a producir.
                                </p>
                                <div class="row">
                                    <?php if (!empty($talleres_disponibles)): ?>
                                        <?php foreach ($talleres_disponibles as $t): ?>
                                            <div class="col-sm-6 col-md-4" style="margin-bottom: 10px;">
                                                <div class="custom-control custom-checkbox" style="background: #fff; padding: 10px 12px 10px 32px; border-radius: 6px; border: 1px solid #ced4da; transition: all 0.2s ease;">
                                                    <input type="checkbox" class="custom-control-input check-taller" 
                                                           id="taller_check_<?php echo $t['id']; ?>" 
                                                           name="talleres[]" 
                                                           value="<?php echo $t['id']; ?>" 
                                                           data-costo="<?php echo htmlspecialchars($t['costo'] ?? 0); ?>" 
                                                           data-nombre="<?php echo htmlspecialchars($t['nombre']); ?>">
                                                    <label class="custom-control-label" for="taller_check_<?php echo $t['id']; ?>" style="cursor: pointer; font-weight: 600; font-size: 14px; user-select: none;">
                                                        <?php echo htmlspecialchars($t['nombre']); ?>
                                                        <span class="badge badge-info" style="margin-left: 6px; font-size: 11px; font-weight: bold; background-color: #0056b3;">
                                                            +$<?php echo number_format((float)($t['costo'] ?? 0), 2, '.', ','); ?> / ud
                                                        </span>
                                                    </label>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="col-sm-12">
                                            <em class="text-muted">No hay talleres activos registrados.</em>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Costo por Unidad ($)</label>
                                    <input type="text" id="costo_por_unidad" class="form-control" readonly style="background-color: #e9ecef;">
                                    <small class="text-muted">Costo de la guia de corte por unidad de producto</small>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Costo Total de Production ($)</label>
                                    <input type="text" id="costo_total_produccion" class="form-control" readonly style="background-color: #e9ecef; font-weight: bold; font-size: 16px; color: #0056b3;">
                                    <small class="text-muted">Costo total = (Costo guía + Costo talleres/ud) × Cantidad</small>
                                </div>
                            </div>
                            
                            <div class="row form-group">
                                <div class="col-sm-6" id="contenedor-equivalente-bs" style="display: none;">
                                    <label class="form-label">Equivalente en Bs.</label>
                                    <input type="text" id="equivalente_bs" class="form-control" readonly style="background-color: #e9ecef;">
                                    <small class="text-muted" id="texto-tasa-informativa"></small>
                                </div>
                                <div class="col-sm-6" id="stock-insumos-container" style="display: none;">
                                    <label class="form-label">Stock Actual de Insumos</label>
                                    <div id="stock-insumos-list" style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; max-height: 200px; overflow-y: auto;">
                                    </div>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Fecha Inicio</label>
                                    <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Fecha Fin</label>
                                    <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                                </div>
                            </div>

                            <div class="form-group">
                                <div class="mb-3">
                                    <label class="form-label">Observaciones</label>
                                    <textarea name="observaciones" id="obser" class="form-control" rows="2"></textarea>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">Crear Orden</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-orden-id" name="id" value="">
                            <input type="hidden" id="action" value="">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalTalleres" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h5 class="modal-title" id="modalTalleresTitle">Asignación de Talleres</h5>
                </div>
                <div class="modal-body">
                    <div id="cronologia-talleres"></div>
                    <div id="seccion-nuevo-taller" style="display:none; border: 1px dashed #0056b3; padding: 15px; border-radius: 8px; margin-top: 15px; background: #fafcff;">
                        <h6 style="color: #0056b3; font-weight: bold;"><i class="fas fa-plus-circle"></i>&nbsp; Asignar Siguiente Taller</h6>
                        <hr style="margin-top: 5px; margin-bottom: 10px;">
                        <form id="form-nuevo-taller">
                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label>Seleccionar Taller</label>
                                    <select id="taller_id_select" class="form-control" required>
                                        <option value="">Seleccione un Taller</option>
                                        <?php foreach($talleres_disponibles as $taller): ?>
                                            <option value="<?php echo $taller['id']; ?>"><?php echo htmlspecialchars($taller['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Descripción del Trabajo</label>
                                <textarea id="descripcion_trabajo_input" class="form-control" rows="2" placeholder="Especificaciones detalladas para el taller..."></textarea>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm" id="btn-guardar-envio-taller">
                                Enviar al Taller
                            </button>
                        </form>
                    </div>
                </div>
                <div class="modal-footer" style=" background: #f1f1f1;">
                    <div>
                        <button type="button" class="btn btn-info" id="btn-anexar-otro-taller" style="display:none;">
                            <i class="fas fa-plus"></i> Siguiente Taller 
                        </button>
                        <button type="button" class="btn btn-success" id="btn-marcar-orden-lista" style="display:none;">
                            <i class="fas fa-check-double"></i> Marcar Orden como Lista
                        </button>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Ver Unidades e Identificadores Únicos de la Orden -->
    <div class="modal fade" id="modalUnidadesOrden" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header" style="background: #f8fafc; border-bottom: 1px solid #dee2e6;">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <h5 class="modal-title" id="modalUnidadesOrdenTitle" style="font-weight: bold; color: #1e293b;">
                        <i class="fas fa-barcode text-primary"></i> Identificadores de Piezas Producidas
                    </h5>
                </div>
                <div class="modal-body" id="modalUnidadesOrdenBody">
                    <div id="cargando-unidades-orden" class="text-center py-4 text-muted">
                        <i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Cargando identificadores...
                    </div>
                    <div id="tabla-unidades-orden-wrapper" style="display:none;">
                        <p class="text-muted" style="font-size: 13px; margin-bottom: 15px;">
                            Cada producto producido en esta orden cuenta con su propio identificador único para trazabilidad y gestión de devoluciones.
                        </p>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover" style="font-size: 14px;">
                                <thead style="background-color: #0056b3; color: white;">
                                    <tr>
                                        <th style="width: 60px; text-align: center;">#</th>
                                        <th>Número Identificador</th>
                                        <th>Producto</th>
                                        <th>Talla</th>
                                        <th style="text-align: center;">Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-unidades-orden">
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer" style="background: #f1f1f1;">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

<script>
var tasaCambiariaActual = <?php echo $tasa_actual !== null ? json_encode($tasa_actual) : 'null'; ?>;
var tasaParaEquivalenteOrden = tasaCambiariaActual;
var ordenActualId = null;

function verUnidadesOrden(ordenId, numeroOrden) {
    if (!ordenId) return;
    $('#modalUnidadesOrdenTitle').html('<i class="fas fa-barcode text-primary"></i> Identificadores de Piezas — Orden ' + (numeroOrden || ('#' + ordenId)));
    $('#cargando-unidades-orden').show();
    $('#tabla-unidades-orden-wrapper').hide();
    $('#tbody-unidades-orden').html('');
    $('#modalUnidadesOrden').modal('show');

    $.ajax({
        url: 'orden_produccion_data.php',
        type: 'GET',
        data: { action: 'listar_unidades_orden', orden_id: ordenId },
        dataType: 'json',
        success: function(resp) {
            $('#cargando-unidades-orden').hide();
            if (resp && resp.success && resp.unidades && resp.unidades.length > 0) {
                let html = '';
                resp.unidades.forEach(function(u) {
                    let badgeEstado = '';
                    if (u.estado === 'disponible') {
                        badgeEstado = '<span class="badge badge-success" style="background-color: #28a745;">Disponible</span>';
                    } else if (u.estado === 'devuelto') {
                        badgeEstado = '<span class="badge badge-warning" style="background-color: #ffc107; color: #212529;">Devuelto</span>';
                    } else if (u.estado === 'en_revision') {
                        badgeEstado = '<span class="badge badge-info" style="background-color: #17a2b8;">En Revisión</span>';
                    } else if (u.estado === 'baja') {
                        badgeEstado = '<span class="badge badge-danger" style="background-color: #dc3545;">De Baja</span>';
                    } else {
                        badgeEstado = `<span class="badge badge-secondary">${u.estado}</span>`;
                    }

                    html += `<tr>
                        <td style="text-align: center; font-weight: bold; color: #64748b;">${u.numero_secuencia}</td>
                        <td>
                            <span style="font-family: monospace; font-size: 14px; font-weight: bold; background: #e2e8f0; padding: 4px 8px; border-radius: 4px; border: 1px solid #cbd5e1; color: #0f172a;">
                                ${u.numero_identificador}
                            </span>
                        </td>
                        <td><strong>${u.producto_nombre}</strong></td>
                        <td>${u.talla_nombre || 'Única'}</td>
                        <td style="text-align: center;">${badgeEstado}</td>
                    </tr>`;
                });
                $('#tbody-unidades-orden').html(html);
                $('#tabla-unidades-orden-wrapper').show();
            } else {
                $('#tbody-unidades-orden').html('<tr><td colspan="5" class="text-center text-muted py-4">No se encontraron unidades registradas para esta orden.</td></tr>');
                $('#tabla-unidades-orden-wrapper').show();
            }
        },
        error: function() {
            $('#cargando-unidades-orden').hide();
            $('#tbody-unidades-orden').html('<tr><td colspan="5" class="text-center text-danger py-4">Error al cargar las unidades de la orden.</td></tr>');
            $('#tabla-unidades-orden-wrapper').show();
        }
    });
}

function cargarListado(page) {
    let filtros = {
        action: 'listar_html',
        buscar_producto: $('#filtro-producto').val(),
        buscar_categoria: $('#filtro-categoria').val(),
        buscar_estado: $('#filtro-estado-orden').val(),
        fecha_desde: $('#filtro-desde').val(),
        fecha_hasta: $('#filtro-hasta').val()
    };

    crudPostListadoPaginado(
        'orden_produccion_data.php',
        filtros,
        '#vista-listado tbody',
        '#paginacion-orden-produccion',
        page || 1
    );
    limpiarFormulario();
}

$(document).ready(function() {
    // Alternar visibilidad del panel de filtros
    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    $('#filtro-producto').on('keyup', function() {
        cargarListado(1);
    });

    $('#filtro-categoria, #filtro-estado-orden, #filtro-desde, #filtro-hasta').on('change', function() {
        cargarListado(1);
    });

    $('#btn-limpiar-filtros').on('click', function() {
        $('#filtro-producto').val('');
        $('#filtro-categoria').val('');
        $('#filtro-estado-orden').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarListado(1);
    });

    $('#btn-ir-crear').on('click', function() {
        limpiarFormulario();
        mostrarVista('crear');
    });
    
    $('#btn-volver-listado').on('click', function() {
        mostrarVista('listado');
    });

    $('#receta_id').on('change', function() {
        calcularCostoTotal();
        cargarStockInsumos($(this).val());
    });
    
    $('#cantidad_a_producir').on('input', function() {
        calcularCostoTotal();
    });

    $('#btn-anexar-otro-taller').on('click', function() {
        $('#form-nuevo-taller')[0].reset();
        $('#seccion-nuevo-taller').slideDown(250);
        $(this).hide(); 
    });

    $('#btn-guardar-envio-taller').on('click', function() {
        var tallerId = $('#taller_id_select').val();
        var descripcion = $('#descripcion_trabajo_input').val(); 

        if(!tallerId) {
            Swal.fire({ icon: 'warning', text: 'Por favor seleccione un taller.' });
            return;
        }

        $.post('orden_produccion_data.php', {
            action: 'enviar_a_taller',
            orden_id: ordenActualId,
            taller_id: tallerId,
            observaciones: descripcion
        }, function(resp) {
            if(resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message || 'Enviado al taller correctamente.' });
                $('#seccion-nuevo-taller').hide();
                abrirModalTalleres(ordenActualId); 
                cargarListado(); 
            } else {
                Swal.fire({ icon: 'error', text: resp.message || 'Error al enviar al taller.' });
            }
        }, 'json');
    });

    $('#btn-marcar-orden-lista').on('click', function() {
        $('#modalTalleres').modal('hide');
        aceptarFinalizacionOrden(ordenActualId);
    });
});

function abrirModalTalleres(orden) {
    if(!orden) return;
    
    var ordenId = (typeof orden === 'object') ? orden.orden_id : orden;
    var numeroOrden = (typeof orden === 'object' && orden.numero_orden) ? orden.numero_orden : '';
    ordenActualId = ordenId;
    
    $('#btn-anexar-otro-taller').hide();
    $('#btn-marcar-orden-lista').hide();
    $('#seccion-nuevo-taller').hide();
    $('#cronologia-talleres').html('<p class="text-center"><i class="fas fa-spinner fa-spin"></i>&nbsp; Cargando historial de la orden...</p>');
    $('#modalTalleresTitle').text(numeroOrden ? 'Asignación de Talleres — ' + numeroOrden : 'Asignación de Talleres');

    $('#modalTalleres').modal('show');

    $.post('orden_produccion_data.php', {
        action: 'obtener_historial_talleres',
        orden_id: ordenId
    }, function(resp) {
        if(resp && resp.success) {
            if (resp.numero_orden) {
                $('#modalTalleresTitle').text('Asignación de Talleres — ' + resp.numero_orden);
            }
            var historial = resp.historial || [];
            var ordenEstado = resp.orden_status;
            var html = '';

            if(historial.length === 0) {
                html = `<div class="alert alert-info text-center">
                            <i class="fas fa-info-circle"></i>&nbsp;  Esta orden no ha sido enviada a ningún taller aún.
                        </div>`;
                $('#btn-anexar-otro-taller').text('Asignar Taller').show();
            } else {
                var totalElementos = historial.length;
                var ultimoTrabajoCompletado = true;

                historial.forEach(function(item, index) {
                    var esUltimo = (index === totalElementos - 1);
                    var cardClass = 'seccion-taller-card';
                    var badgeStatus = '';
                    var botonAccion = '';

                    if(item.fecha_retorno) {
                        cardClass += ' completada';
                        badgeStatus = '<span class="badge badge-success float-right"><i class="fas fa-check"></i> Trabajo Completado</span>';
                    } else {
                        cardClass += ' activa';
                        badgeStatus = '<span class="badge badge-success float-right"><i class="fas fa-clock"></i> En Proceso</span>';
                        ultimoTrabajoCompletado = false;

                        botonAccion = `
                            <div class="text-right">
                                <button type="button" class="btn btn-success btn-sm" onclick="registrarRetornoTaller(${item.id})">
                                    <i class="fas fa-undo"></i> Recepion de Mercancía
                                </button>
                            </div>`;
                    }

                    html += `
                    <div class="${cardClass}">
                        ${badgeStatus}
                        <h6 style="font-weight:bold; color:#333;">Taller ${index + 1}: ${item.taller_nombre}</h6>
                        <p style="margin-bottom:5px; font-size:14px;"><strong>Especificaciones:</strong> ${item.observaciones || '<i>Sin observaciones</i>'}</p>
                        <small class="text-muted">Despachado: ${item.fecha_despacho} ${item.fecha_retorno ? ' | Retornado: ' + item.fecha_retorno : ''}</small>
                        ${botonAccion}
                    </div>`;
                });

                // REGLA CLAVE: Si el último taller ya retornó mercancía, habilitamos los caminos finales en el footer
                if(ultimoTrabajoCompletado) {
                    $('#btn-anexar-otro-taller').text('Asignar Taller').show();
                    $('#btn-marcar-orden-lista').show();
                }
            }

            $('#cronologia-talleres').html(html);
        } else {
            $('#cronologia-talleres').html('<div class="alert alert-danger">Error al cargar la información.</div>');
        }
    }, 'json');
}

// NUEVO: Cambiar estado del registro intermedio a Recibido
function registrarRetornoTaller(historialId) {
    if(!historialId) return;

    Swal.fire({
        title: '¿Confirmar recepción?',
        text: 'Al aceptar registrará el retorno físico de las prendas a la empresa y pasará a revisión.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonText: 'Sí, recibir',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if(result.isConfirmed) {
            $.post('orden_produccion_data.php', {
                action: 'registrar_retorno_taller',
                historial_id: historialId,
                orden_id: ordenActualId
            }, function(resp) {
                if(resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message || 'Retorno registrado con éxito.' });
                    abrirModalTalleres(ordenActualId); // Refresca el modal para mutar a modo lectura y activar el "+" o "Marcar Lista"
                    cargarListado(); // Sincroniza la tabla de fondo
                } else {
                    Swal.fire({ icon: 'error', text: resp.message || 'Error al procesar el retorno.' });
                }
            }, 'json');
        }
    });
}

function actualizarEquivalenteBs() {
    var costoTotalDolares = parseFloat($('#costo_total_produccion').val().replace(/[^0-9.-]/g, '')) || 0;
    var contenedor = $('#contenedor-equivalente-bs');
    var tasa = tasaParaEquivalenteOrden;
    if (!tasa || tasa <= 0 || costoTotalDolares <= 0) {
        contenedor.hide();
        return;
    }
    var equivalenteBs = costoTotalDolares * tasa;
    $('#equivalente_bs').val('Bs. ' + equivalenteBs.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    $('#texto-tasa-informativa').text('Tasa informativa: ' + tasa.toFixed(4) + ' Bs/USD');
    contenedor.show();
}

function mostrarVista(vista) {
    $('#vista-listado, #vista-crear').addClass('hidden').hide();
    $('#vista-' + vista).removeClass('hidden').fadeIn(250);
}
function cargarListado(page) {
    crudPostListadoPaginado(
        'orden_produccion_data.php',
        { action: 'listar_html' },
        '#vista-listado tbody',
        '#paginacion-orden-produccion',
        page || 1
    );
    limpiarFormulario();
}

function cargarTallasPorReceta(recetaId, tallaIdSeleccionada) {
    var $contenedor = $('#contenedor-talla');
    var $select = $('#talla_id');

    if (!recetaId) {
        $contenedor.hide();
        $select.html('<option value="">Seleccione la talla</option>');
        return;
    }

    var rangoId = $('#receta_id option:selected').data('rango-tallas-id');
    if (!rangoId) {
        $contenedor.hide();
        $select.html('<option value="">Seleccione la talla</option>');
        return;
    }

    $.post('orden_produccion_data.php', {
        action: 'obtener_tallas',
        rango_tallas_id: rangoId
    }, function(resp) {
        if (!resp || !resp.success || !resp.tallas || resp.tallas.length === 0) {
            $contenedor.hide();
            $select.html('<option value="">Sin tallas configuradas</option>');
            return;
        }

        var html = '<option value="">Seleccione la talla</option>';
        resp.tallas.forEach(function(t) {
            var selected = tallaIdSeleccionada && String(tallaIdSeleccionada) === String(t.id) ? ' selected' : '';
            html += '<option value="' + t.id + '"' + selected + '>' + t.nombre + '</option>';
        });
        $select.html(html);
        $contenedor.show();

        if (resp.tallas.length === 1 && !tallaIdSeleccionada) {
            $select.val(String(resp.tallas[0].id));
        }
    }, 'json');
}

function calcularCostoTotal() {
    var recetaId = $('#receta_id').val();
    var cantidad = parseFloat($('#cantidad_a_producir').val()) || 0;

    var costoTallerPorUnidad = 0;
    $('.check-taller:checked').each(function() {
        var c = parseFloat($(this).data('costo')) || 0;
        costoTallerPorUnidad += c;
    });
    var totalCostosTalleres = costoTallerPorUnidad * cantidad;

    if (recetaId && cantidad > 0) {
        var costoUnitario = parseFloat($('#receta_id option:selected').data('costo')) || 0;
        
        if (costoUnitario > 0) {
            $('#costo_por_unidad').val('$' + costoUnitario.toFixed(2));
            var costoBase = costoUnitario * cantidad;
            var costoTotal = costoBase + totalCostosTalleres;
            $('#costo_total_produccion').val('$' + costoTotal.toFixed(2));
            actualizarEquivalenteBs();
        } else {
            obtenerCostoReceta(recetaId, cantidad, totalCostosTalleres);
        }
    } else {
        $('#costo_por_unidad').val('');
        if (totalCostosTalleres > 0) {
            $('#costo_total_produccion').val('$' + totalCostosTalleres.toFixed(2));
            actualizarEquivalenteBs();
        } else {
            $('#costo_total_produccion').val('');
            $('#contenedor-equivalente-bs').hide();
        }
    }
}

function obtenerCostoReceta(recetaId, cantidad, totalCostosTalleres) {
    totalCostosTalleres = totalCostosTalleres || 0;
    $.post('orden_produccion_data.php', {
        action: 'obtener_costo_receta',
        receta_id: recetaId
    }, function(resp) {
        if (resp && resp.success) {
            var costoUnitario = parseFloat(resp.costo_por_unidad) || 0;
            if (costoUnitario > 0) {
                $('#costo_por_unidad').val('$' + costoUnitario.toFixed(2));
                var costoBase = costoUnitario * cantidad;
                var costoTotal = costoBase + totalCostosTalleres;
                $('#costo_total_produccion').val('$' + costoTotal.toFixed(2));
                actualizarEquivalenteBs();
            }
        }
    }, 'json');
}

function cargarStockInsumos(recetaId) {
    if (!recetaId) {
        $('#stock-insumos-container').hide();
        return;
    }
    
    $.post('orden_produccion_data.php', {
        action: 'obtener_stock_insumos',
        receta_id: recetaId
    }, function(resp) {
        if (resp && resp.success && resp.insumos) {
            var html = '<table style="width: 100%; font-size: 14px;">';
            html += '<thead><tr style="background-color: #0056b3; color: white;"><th style="padding: 8px;">Insumo</th><th style="padding: 8px;">Cantidad por Unidad</th><th style="padding: 8px;">Stock Actual</th></tr></thead>';
            html += '<tbody>';
            
            resp.insumos.forEach(function(insumo) {
                var stockClass = parseFloat(insumo.stock_actual) > 0 ? 'color: #28a745; font-weight: bold;' : 'color: #dc3545; font-weight: bold;';
                html += '<tr>';
                html += '<td style="padding: 5px;">' + insumo.insumo_nombre + ' (' + insumo.unidad_medida + ')</td>';
                html += '<td style="padding: 5px;">' + parseFloat(insumo.cantidad_por_unidad).toFixed(4) + '</td>';
                html += '<td style="padding: 5px; ' + stockClass + '">' + parseFloat(insumo.stock_actual).toFixed(2) + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody></table>';
            $('#stock-insumos-list').html(html);
            $('#stock-insumos-container').show();
        } else {
            $('#stock-insumos-container').hide();
        }
    }, 'json');
}

$(document).ready(function() {
    $('#receta_id').on('change', function() {
        calcularCostoTotal();
        cargarStockInsumos($(this).val());
        cargarTallasPorReceta($(this).val(), null);
    });
    
    $('#cantidad_a_producir').on('input', function() {
        calcularCostoTotal();
    });

    $(document).on('change', '.check-taller', function() {
        calcularCostoTotal();
    });
});

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('.check-taller').prop('checked', false);
    $('#receta_id').val('');
    $('#talla_id').html('<option value="">Seleccione la talla</option>');
    $('#contenedor-talla').hide();
    $('#cantidad_a_producir').val('');
    $('#costo_por_unidad').val('');
    $('#costo_total_produccion').val('');
    $('#contenedor-equivalente-bs').hide();
    $('#obser').val('');          
    $('#editar-orden-id').val('');
    $('#numero_orden').val('');
    $('#contenedor-numero-orden').hide();
    tasaParaEquivalenteOrden = tasaCambiariaActual;
}

function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-00-00') return '';
    return fecha;
}

function editarOrden(data) {
    $('.check-taller').prop('checked', false);
    if (data.talleres_ids && Array.isArray(data.talleres_ids)) {
        data.talleres_ids.forEach(function(tid) {
            $('#taller_check_' + tid).prop('checked', true);
        });
    } else if (data.talleres_ids_csv) {
        var ids = String(data.talleres_ids_csv).split(',');
        ids.forEach(function(tid) {
            var cleanId = tid.trim();
            if (cleanId) {
                $('#taller_check_' + cleanId).prop('checked', true);
            }
        });
    }

    $('#receta_id').val(data.receta_id);
    cargarTallasPorReceta(data.receta_id, data.talla_id || null);
    $('#cantidad_a_producir').val(data.cantidad_a_producir);
    $('#fecha_inicio').val(formatearFecha(data.fecha_inicio));
    $('#fecha_fin').val(formatearFecha(data.fecha_fin));
    $('#obser').val(data.observaciones || '');

    tasaParaEquivalenteOrden = (data.tasa_orden != null && parseFloat(data.tasa_orden) > 0) ? parseFloat(data.tasa_orden) : tasaCambiariaActual;
    $('#editar-orden-id').val(data.orden_id);
    $('#numero_orden').val(data.numero_orden || '');
    if (data.numero_orden) {
        $('#contenedor-numero-orden').show();
    } else {
        $('#contenedor-numero-orden').hide();
    }
    calcularCostoTotal();
    cargarStockInsumos(data.receta_id);
    mostrarVista('crear');
}

function aceptarFinalizacionOrden(ordenId) {
    if (!ordenId) return;

    Swal.fire({
        title: 'Confirmar finalización',
        text: 'Esta acción cambiará el estado a finalizado y generará el movimiento de inventario. ¿Desea continuar?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Sí, aceptar',
        cancelButtonText: 'Cancelar'
    }).then(function(result) {
        if (!result.isConfirmed) return;

        $.ajax({
            url: "orden_produccion_data.php",
            type: "POST",
            dataType: "json",
            data: {
                action: "aceptar_finalizacion",
                orden_id: ordenId
            },
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message || 'Orden finalizada correctamente.' });
                    cargarListado();
                } else {
                    Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Respuesta inválida") });
                }
            },
            error: function(xhr) {
                try {
                    var resp = JSON.parse(xhr.responseText);
                    Swal.fire({ icon: 'error', text: "Error: " + (resp.message || 'No se pudo finalizar la orden.') });
                } catch (e) {
                    Swal.fire({ icon: 'error', text: "Error de conexión al finalizar la orden." });
                }
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    var idOrden = $("#editar-orden-id").val();
    var fInicio = $("#fecha_inicio").val();
    var fFin = $("#fecha_fin").val();

    var recetaId = $("#receta_id").val();
    if (!recetaId) {
        Swal.fire({ icon: 'warning', text: "Debe seleccionar una guía de corte." });
        return;
    }

    if (fInicio && fFin) {
        if (new Date(fFin) < new Date(fInicio)) {
            Swal.fire({ icon: 'warning', text: "La fecha de fin no puede ser anterior a la fecha de inicio." });
            return;
        }
    }

    var cantidadProd = parseFloat($('#cantidad_a_producir').val()) || 0;
    if (cantidadProd <= 0) {
        Swal.fire({ icon: 'warning', text: 'Indique una cantidad a producir mayor a cero.' });
        return;
    }
    if (Math.abs(cantidadProd - Math.round(cantidadProd)) > 1e-9) {
        Swal.fire({ icon: 'warning', text: 'La cantidad a producir debe ser un número entero (sin decimales).' });
        return;
    }

    var talleresSeleccionados = [];
    $('.check-taller:checked').each(function() {
        talleresSeleccionados.push($(this).val());
    });

    var datos = {
        action: idOrden ? "editar" : "crear",
        id: idOrden || null,
        receta_id: $("#receta_id").val(),
        talla_id: $("#talla_id").val(),
        cantidad_a_producir: $("#cantidad_a_producir").val(),
        fecha_inicio: fInicio || "",
        fecha_fin: fFin || "",
        observaciones: $("#obser").val() || "",
        orden_id: $('#editar-orden-id').val() || "",
        talleres: talleresSeleccionados
    };

    $.ajax({
        url: "orden_produccion_data.php",
        type: "POST",
        data: datos,
        dataType: "json",
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
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp && resp.message) {
                    Swal.fire({ icon: 'error', text: "Error: " + resp.message });
                } else {
                    Swal.fire({ icon: 'error', text: "Error de conexión." });
                }
            } catch (e) {
                Swal.fire({ icon: 'error', text: "Error de conexión." });
            }
        }
    });
});

cargarListado(1);
bindCrudPagination('#paginacion-orden-produccion', cargarListado);
</script>

<?php require_once "../template/footer.php"; ?>