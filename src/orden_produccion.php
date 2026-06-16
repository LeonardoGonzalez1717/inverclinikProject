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

// Para llenar dinámicamente el selector de categorías del filtro
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
                                    <label for="filtro-producto">Producto</label>
                                    <input type="text" id="filtro-producto" class="form-control clase-filtro" placeholder="Buscar nombre de producto...">
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
                                        <option value="finalizado">Finalizado</option>
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
                                        <th>#</th>
                                        <th>Producto</th>
                                        <th>Talla</th>
                                        <th>Categoría</th>
                                        <th>Cantidad</th>
                                        <th>Costo por Unidad</th>
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

                        <form id="form-crear">
                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Guia de corte</label>
                                    <select name="receta_id" id="receta_id" class="form-control" required>
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
                                <div class="col-sm-6" id="contenedor-talla" style="display: none;">
                                    <label class="form-label">Talla a producir <span style="color: red;">*</span></label>
                                    <select name="talla_id" id="talla_id" class="form-control" required>
                                        <option value="">Seleccione la talla</option>
                                    </select>
                                    <small class="text-muted">Las tallas dependen del rango del producto</small>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Cantidad a Producir</label>
                                    <input type="number" step="1" min="1" name="cantidad_a_producir" id="cantidad_a_producir" class="form-control" required>
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
                                    <small class="text-muted">Costo total = Costo por Unidad × Cantidad a Producir</small>
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

<script>
var tasaCambiariaActual = <?php echo $tasa_actual !== null ? json_encode($tasa_actual) : 'null'; ?>;
var tasaParaEquivalenteOrden = tasaCambiariaActual;

// MODIFICADO: Ahora captura los filtros y los manda globalmente en el objeto data
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

// MANEJO DE EVENTOS PARA LOS FILTROS
$(document).ready(function() {
    // Alternar visibilidad del panel de filtros
    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    // Evento al escribir en el buscador de producto (página 1)
    $('#filtro-producto').on('keyup', function() {
        cargarListado(1);
    });

    // Eventos al cambiar selectores o fechas (página 1)
    $('#filtro-categoria, #filtro-estado-orden, #filtro-desde, #filtro-hasta').on('change', function() {
        cargarListado(1);
    });

    // Botón para limpiar filtros por completo
    $('#btn-limpiar-filtros').on('click', function() {
        $('#filtro-producto').val('');
        $('#filtro-categoria').val('');
        $('#filtro-estado-orden').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarListado(1);
    });

    // Manejo de la vista de creación
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
});

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
    
    if (recetaId && cantidad > 0) {
        var costoUnitario = parseFloat($('#receta_id option:selected').data('costo')) || 0;
        
        if (costoUnitario > 0) {
            $('#costo_por_unidad').val('$' + costoUnitario.toFixed(2));
            var costoTotal = costoUnitario * cantidad;
            $('#costo_total_produccion').val('$' + costoTotal.toFixed(2));
            actualizarEquivalenteBs();
        } else {
            obtenerCostoReceta(recetaId, cantidad);
        }
    } else {
        $('#costo_por_unidad').val('');
        $('#costo_total_produccion').val('');
        $('#contenedor-equivalente-bs').hide();
    }
}

function obtenerCostoReceta(recetaId, cantidad) {
    $.post('orden_produccion_data.php', {
        action: 'obtener_costo_receta',
        receta_id: recetaId
    }, function(resp) {
        if (resp && resp.success) {
            var costoUnitario = parseFloat(resp.costo_por_unidad) || 0;
            if (costoUnitario > 0) {
                $('#costo_por_unidad').val('$' + costoUnitario.toFixed(2));
                var costoTotal = costoUnitario * cantidad;
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
});

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#receta_id').val('');
    $('#talla_id').html('<option value="">Seleccione la talla</option>');
    $('#contenedor-talla').hide();
    $('#cantidad_a_producir').val('');
    $('#costo_por_unidad').val('');
    $('#costo_total_produccion').val('');
    $('#contenedor-equivalente-bs').hide();
    $('#obser').val('');          
    $('#editar-orden-id').val('');
    tasaParaEquivalenteOrden = tasaCambiariaActual;
}

function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-00-00') return '';
    return fecha;
}

function editarOrden(data) {
    $('#receta_id').val(data.receta_id);
    cargarTallasPorReceta(data.receta_id, data.talla_id || null);
    $('#cantidad_a_producir').val(data.cantidad_a_producir);
    $('#fecha_inicio').val(formatearFecha(data.fecha_inicio));
    $('#fecha_fin').val(formatearFecha(data.fecha_fin));
    $('#obser').val(data.observaciones || '');

    tasaParaEquivalenteOrden = (data.tasa_orden != null && parseFloat(data.tasa_orden) > 0) ? parseFloat(data.tasa_orden) : tasaCambiariaActual;
    $('#editar-orden-id').val(data.orden_id);
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

    if (!$('#talla_id').val()) {
        Swal.fire({ icon: 'warning', text: 'Debe seleccionar la talla a producir.' });
        return;
    }

    var datos = {
        action: idOrden ? "editar" : "crear",
        id: idOrden || null,
        receta_id: $("#receta_id").val(),
        talla_id: $("#talla_id").val(),
        cantidad_a_producir: $("#cantidad_a_producir").val(),
        fecha_inicio: fInicio || "",
        fecha_fin: fFin || "",
        observaciones: $("#obser").val() || "",
        orden_id: $('#editar-orden-id').val() || ""
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

// Inicialización de la tabla
cargarListado(1);
bindCrudPagination('#paginacion-orden-produccion', cargarListado);
</script>

<?php require_once "../template/footer.php"; ?>