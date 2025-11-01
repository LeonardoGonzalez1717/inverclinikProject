<?php require_once "../template/header.php"; ?>
<?php
require_once "../connection/connection.php";

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
<div class="navbar navbar-inverse navbar-fixed-top">
            <div class="adjust-nav">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".sidebar-collapse">
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="#"><i class="fa fa-square-o "></i>&nbsp;TWO PAGE</a>
                </div>
                <div class="navbar-collapse collapse">
                    <ul class="nav navbar-nav navbar-right">
                        <li><a href="#">See Website</a></li>
                        <li><a href="#">Open Ticket</a></li>
                        <li><a href="#">Report Bug</a></li>
                    </ul>
                </div>

            </div>
        </div>
        <!-- /. NAV TOP  -->
        <nav class="navbar-default navbar-side" style="top:50px" role="navigation">
            <div class="sidebar-collapse">
                <ul class="nav" id="main-menu">
                    <li class="text-center user-image-back" style="display:flex; align-items:end; cursor: pointer;" onclick="userManagement()">
                        <img src="assets/img/find_user.png" class="img-responsive" />
                        <span style="font-size:20px; font-weight:400; color:#fff">Hola, <?=$row['username']?></span>
                    </li>


                    <li>
                        <a href="index.html"><i class="fa fa-desktop "></i>Dashboard</a>
                    </li>
                    <li>
                        <a href="#"><i class="fa fa-edit "></i>UI Elements<span class="fa arrow"></span></a>
                        <ul class="nav nav-second-level">
                            <li>
                                <a href="src/nuevo_producto.php">Registrar recetas</a>
                            </li>
                            <li>
                                <a href="src/orden_produccion.php">Ordenes de produccion</a>
                            </li>
                            <li>
                                <a href="#">Free Link</a>
                            </li>
                        </ul>
                    </li>

                    <li>
                        <a href="#"><i class="fa fa-table "></i>Table Examples</a>
                    </li>
                    <li>
                        <a href="#"><i class="fa fa-edit "></i>Forms </a>
                    </li>


                    <li>
                        <a href="#"><i class="fa fa-sitemap "></i>Multi-Level Dropdown<span class="fa arrow"></span></a>
                        <ul class="nav nav-second-level">
                            <li>
                                <a href="#">Second Level Link</a>
                            </li>
                            <li>
                                <a href="#">Second Level Link</a>
                            </li>
                            <li>
                                <a href="#">Second Level Link<span class="fa arrow"></span></a>
                                <ul class="nav nav-third-level">
                                    <li>
                                        <a href="#">Third Level Link</a>
                                    </li>
                                    <li>
                                        <a href="#">Third Level Link</a>
                                    </li>
                                    <li>
                                        <a href="#">Third Level Link</a>
                                    </li>

                                </ul>

                            </li>
                        </ul>
                    </li>
                    <li>
                        <a href="#"><i class="fa fa-qrcode "></i>Tabs & Panels</a>
                    </li>
                    <li>
                        <a href="#"><i class="fa fa-bar-chart-o"></i>Mettis Charts</a>
                    </li>

                    <li>
                        <a href="#"><i class="fa fa-edit "></i>Last Link </a>
                    </li>
                    <li>
                        <a href="blank.html"><i class="fa fa-table "></i>Blank Page</a>
                    </li>
                </ul>

            </div>

        </nav>
<div id="page-wrapper">
    <div id="page-inner">
        <div class="row">
            <div class="col-md-12">
                <h2>Órdenes de Producción</h2>
            </div>
        </div>

        <div class="row mb-3" id="vista-botones">
            <div class="col-md-12">
                <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nueva Orden</button>
            </div>
        </div>

        <div id="contenedor-vistas">
            <div id="vista-listado">
                <h5>Listado de Órdenes</h5>
                <table class="table table-striped table-bordered table-hover">
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
                        
                    </tbody>
                </table>
            </div>

            <div id="vista-crear" class="hidden">
                <h4>Crear Nueva Orden</h4>
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