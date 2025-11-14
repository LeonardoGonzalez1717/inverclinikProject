<?php 
// require_once "../template/header.php"; 
?> 
<?php
require_once "../connection/connection.php";
require_once "../template/navbar.php"; // Incluir la navbar

$sqlRecetas = "
    SELECT rp.id, p.nombre AS producto_nombre, rt.nombre_rango AS rango_tallas_nombre
    FROM recetas_productos rp
    INNER JOIN productos p ON rp.producto_id = p.id
    INNER JOIN rangos_tallas rt ON rp.rango_tallas_id = rt.id
    ORDER BY p.nombre, rt.nombre_rango
";
$resultRecetas = $conn->query($sqlRecetas);
$recetas = [];
if ($resultRecetas) {
    while ($row = $resultRecetas->fetch_assoc()) {
        $recetas[] = $row;
    }
}

// Consultar órdenes de producción con nombres de producto
$sqlOrdenes = "
    SELECT 
        op.id AS orden_id,
        p.nombre AS producto_nombre,
        op.cantidad_a_producir,
        op.fecha_inicio,
        op.fecha_fin,
        op.estado,
        op.observaciones, rp.id
    FROM ordenes_produccion op
    INNER JOIN recetas_productos rp ON op.receta_producto_id = rp.id
    INNER JOIN productos p ON rp.producto_id = p.id
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
    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="../css/navbar.css" />
    <script src="../assets/js/jquery-3.7.1.min.js"></script>

    <style>
        /* Estilos generales */
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
                                        <th>Cantidad</th>
                                        <th>Inicio</th>
                                        <th>Fin</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Los datos se cargarán dinámicamente -->
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
                                        <option value="<?php echo htmlspecialchars($r['id']); ?>">
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
function limpiarFormulario() {
    $('#form-crear')[0].reset();

    $('#receta_id').val('');
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