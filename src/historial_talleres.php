<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../template/navbar.php";

// Obtenemos los talleres disponibles para el filtro desplegable
$talleres_disponibles = [];
$resTalleres = $conn->query("SELECT id, nombre FROM talleres ORDER BY nombre ASC");
if ($resTalleres) {
    while ($t = $resTalleres->fetch_assoc()) {
        $talleres_disponibles[] = $t;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Movimientos en Talleres</title>
    <link rel="stylesheet" href="../css/movimientos_inventario.css">
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Historial de Talleres Externos</h2>
                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div aria-label="Acciones de Historial">
                                    <button class="btn btn-info" id="btn-toggle-filtros" title="Filtros" data-toggle="tooltip">
                                        <i class="fas fa-filter"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.05); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-sm-4">
                                    <label for="filtro-taller">Taller Destino</label>
                                    <select id="filtro-taller" class="form-control clase-filtro">
                                        <option value=""> Todos los Talleres </option>
                                        <?php foreach($talleres_disponibles as $taller): ?>
                                            <option value="<?php echo $taller['id']; ?>"><?php echo htmlspecialchars($taller['nombre']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-orden">N° Orden de Producción</label>
                                    <input type="number" id="filtro-orden" class="form-control clase-filtro">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-estatus">Estatus en Tránsito</label>
                                    <select id="filtro-estatus" class="form-control clase-filtro">
                                        <option value="">Todos</option>
                                        <option value="afuera">En Taller (Afuera)</option>
                                        <option value="recibido">Recibido (En Planta)</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-4">
                                    <label for="filtro-desde">Fecha Desde</label>
                                    <input type="date" id="filtro-desde" class="form-control clase-filtro">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-hasta">Fecha Hasta</label>
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
                            <table class="recipe-table" style="width: 100%;">
                                <thead>
                                    <tr>
                                        <th style="width: 1%;">#</th>
                                        <th style="width: 8%;" nowrap>N° Orden</th>
                                        <th style="width: 18%;">Taller</th>
                                        <th style="width: 14%;">Fecha Despacho</th>
                                        <th style="width: 14%;">Fecha Retorno</th>
                                        <th style="width: 15%; text-align: center;">Estatus Tránsito</th>
                                        <th style="width: 30%;">Especificaciones / Observaciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-historial-talleres">
                                </tbody>
                            </table>
                        </div>
                        <div id="paginacion-historial-talleres"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
function cargarHistorialTalleres(page) {
    let params = { 
        action: 'listar_html',
        taller_id: $('#filtro-taller').val(),
        orden_id: $('#filtro-orden').val(),
        estatus_transito: $('#filtro-estatus').val(),
        fecha_desde: $('#filtro-desde').val(),
        fecha_hasta: $('#filtro-hasta').val()
    };

    // Reutiliza tu función global de peticiones paginadas
    crudPostListadoPaginado(
        'historial_talleres_data.php',
        params,
        '#tbody-historial-talleres',
        '#paginacion-historial-talleres',
        page || 1
    );
}

$(function() {
    // Inicialización del listado
    cargarHistorialTalleres(1);
    bindCrudPagination('#paginacion-historial-talleres', cargarHistorialTalleres);

    // Animación de filtros
    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    // Filtros en tiempo real
    $('.clase-filtro').on('keyup change', function() {
        cargarHistorialTalleres(1);
    });

    // Limpiador del formulario
    $('#btn-limpiar-filtros').on('click', function() {
        $('#filtro-taller').val('');
        $('#filtro-orden').val('');
        $('#filtro-estatus').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarHistorialTalleres(1);
    });
});
</script>
</body>
</html>