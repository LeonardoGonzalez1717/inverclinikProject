<?php 
require_once "../template/header.php"; 

$sqlRecetas = "
    SELECT 
        rp.id, 
        p.nombre AS producto_nombre, 
        rt.nombre_rango AS rango_tallas_nombre,
        rp.producto_id,
        rp.rango_tallas_id,
        rp.tipo_produccion_id,
        COALESCE(SUM(rp2.cantidad_por_unidad * i.costo_unitario), 0) AS costo_por_unidad
    FROM recetas_productos rp
    INNER JOIN productos p ON rp.producto_id = p.id
    INNER JOIN rangos_tallas rt ON rp.rango_tallas_id = rt.id
    LEFT JOIN recetas_productos rp2 ON rp2.producto_id = rp.producto_id 
        AND rp2.rango_tallas_id = rp.rango_tallas_id 
        AND rp2.tipo_produccion_id = rp.tipo_produccion_id
    LEFT JOIN insumos i ON rp2.insumo_id = i.id
    GROUP BY rp.id, rp.producto_id, rp.rango_tallas_id, rp.tipo_produccion_id
    ORDER BY p.nombre, rt.nombre_rango
";
$resultRecetas = $conn->query($sqlRecetas);
$recetas = [];
if ($resultRecetas) {
    while ($row = $resultRecetas->fetch_assoc()) {
        $recetas[] = $row;
    }
}

$sqlOrdenes = "
    SELECT 
        op.id AS orden_id,
        p.nombre AS producto_nombre,
        p.categoria AS producto_categoria,
        op.cantidad_a_producir,
        op.fecha_inicio,
        op.fecha_fin,
        op.estado,
        op.observaciones,
        rp.id AS receta_id,
        rp.producto_id,
        rp.rango_tallas_id,
        rp.tipo_produccion_id,
        COALESCE(SUM(rp2.cantidad_por_unidad * i.costo_unitario), 0) AS costo_por_unidad
    FROM ordenes_produccion op
    INNER JOIN recetas_productos rp ON op.receta_producto_id = rp.id
    INNER JOIN productos p ON rp.producto_id = p.id
    LEFT JOIN recetas_productos rp2 ON rp2.producto_id = rp.producto_id 
        AND rp2.rango_tallas_id = rp.rango_tallas_id 
        AND rp2.tipo_produccion_id = rp.tipo_produccion_id
    LEFT JOIN insumos i ON rp2.insumo_id = i.id
    GROUP BY op.id, rp.id, rp.producto_id, rp.rango_tallas_id, rp.tipo_produccion_id
    ORDER BY op.creado_en DESC
";
$resultOrdenes = $conn->query($sqlOrdenes);
$ordenes = [];
if ($resultOrdenes) {
    while ($row = $resultOrdenes->fetch_assoc()) {
        $ordenes[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Órdenes de Producción</title>

    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        /* Contenedor principal */
        .container-wrapper {
            min-height: 100vh;
            padding: 20px;
        }

        .container-inner {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Encabezados */
        .main-title {
            color: #0056b3; /* Azul principal */
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
            border-bottom: 3px solid #ffc107; /* Amarillo */
            padding-bottom: 15px;
        }

        .subtitle {
            color: #004085; /* Azul oscuro */
            font-size: 18px;
            text-align: center;
            margin-top: 0;
            margin-bottom: 25px;
        }

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
            background-color: #0056b3; /* Azul principal */
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
            background-color: #f2f7ff; /* Azul claro */
        }

        .orders-table tr:hover {
            background-color: #e7f3ff; /* Azul muy claro */
        }

        /* Mensaje cuando no hay datos */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        /* Estilo para el contenedor de la tabla */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }

        /* Botones */
        .btn-success {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .btn-success:hover {
            background-color: #218838;
        }

        /* Formulario */
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #495057;
        }

        .mb-3 {
            margin-bottom: 15px;
        }

        /* Botones del formulario */
        .btn-primary {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            margin-right: 10px;
        }

        .btn-primary:hover {
            background-color: #0056b3;
        }

        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background-color: #545b62;
        }

        /* Vista oculta */
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Órdenes de Producción</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nueva Orden</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Listado de Órdenes</h5>
                        <div class="table-container">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Producto</th>
                                        <th>Categoría</th>
                                        <th>Cantidad</th>
                                        <th>Costo por Unidad</th>
                                        <th>Costo Total</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
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
                        <h4 class="main-title">Crear Nueva Orden</h4>
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Receta</label>
                                <select name="receta_id" id="receta_id" class="form-control" required>
                                    <option value="">-- Seleccione una receta --</option>
                                    <?php foreach ($recetas as $r): ?>
                                        <option value="<?php echo htmlspecialchars($r['id']); ?>" 
                                                data-costo="<?php echo htmlspecialchars($r['costo_por_unidad'] ?? 0); ?>">
                                            <?php echo htmlspecialchars($r['producto_nombre'] . ' - ' . $r['rango_tallas_nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Cantidad a Producir</label>
                                <input type="number" step="0.01" min="0.01" name="cantidad_a_producir" id="cantidad_a_producir" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Costo por Unidad ($)</label>
                                <input type="text" id="costo_por_unidad" class="form-control" readonly style="background-color: #e9ecef;">
                                <small class="text-muted">Costo de la receta por unidad de producto</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Costo Total de Producción ($)</label>
                                <input type="text" id="costo_total_produccion" class="form-control" readonly style="background-color: #e9ecef; font-weight: bold; font-size: 16px; color: #0056b3;">
                                <small class="text-muted">Costo total = Costo por Unidad × Cantidad a Producir</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Fecha Inicio</label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Fecha Fin</label>
                                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" id="obser" class="form-control" rows="2"></textarea>
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
    $.post('orden_produccion_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
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
        } else {
            // Si no tiene costo en el data, obtenerlo del servidor
            obtenerCostoReceta(recetaId, cantidad);
        }
    } else {
        $('#costo_por_unidad').val('');
        $('#costo_total_produccion').val('');
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
            }
        }
    }, 'json');
}

$(document).ready(function() {
    $('#receta_id').on('change', function() {
        calcularCostoTotal();
    });
    
    $('#cantidad_a_producir').on('input', function() {
        calcularCostoTotal();
    });
});

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#receta_id').val('');
    $('#cantidad_a_producir').val('');
    $('#costo_por_unidad').val('');
    $('#costo_total_produccion').val('');
    $('#obser').val('');          
    $('#editar-orden-id').val('');
}
function formatearFecha(fecha) {
    if (!fecha || fecha === '0000-00-00') return '';
    return fecha;
}

function editarOrden(data) {
    console.log("asd")
    $('#id').val(data.id);
    $('#receta_id').val(data.id);
    $('#cantidad_a_producir').val(data.cantidad_a_producir);
    $('#fecha_inicio').val(formatearFecha(data.fecha_inicio));
    $('#fecha_fin').val(formatearFecha(data.fecha_fin));
    $('#estado').val(data.estado);
    $('#observaciones').val(data.observaciones || '');

    $('#editar-orden-id').val(data.orden_id);
    mostrarVista('crear');
        
}
document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
});


$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    var idOrden = $("#editar-orden-id").val();

        var datos = {
            action: idOrden ? "editar" : "crear",
            id: idOrden || null,
            receta_id: $("#receta_id").val(),
            cantidad_a_producir: $("#cantidad_a_producir").val(),
            fecha_inicio: $("#fecha_inicio").val() || "",
            fecha_fin: $("#fecha_fin").val() || "",
            observaciones: $("#obser").val() || "",
            orden_id: $('#editar-orden-id').val() || ""
        };

    $.ajax({
        url: "orden_produccion_data.php",
        type: "POST",
        data: datos,
        success: function(resp) {
            if (resp && resp.success) {
                console.log(resp,"resp")
                alert(resp.message);
                mostrarVista("listado");
                cargarListado();
            } else {
                alert("Error: " + (resp ? resp.message : "Respuesta inválida"));
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            alert("Error de conexión.");
        }
    });
});
cargarListado();

</script>

<?php require_once "../template/footer.php"; ?>