<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../template/navbar.php";
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de movimientos</title>
    <link rel="stylesheet" href="../css/movimientos_inventario.css">
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Historial de movimientos</h2>
                <p class="text-muted">Todos los movimientos registrados en inventario (insumos y productos terminados).</p>

                <div id="vista-listado">
                    <!-- <h5 class="subtitle">Listado de movimientos</h5> -->
                    <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Tipo ítem</th>
                                    <th>Ítem</th>
                                    <th>Último Movimiento</th>
                                    <th>Origen del Movimiento</th>
                                    <th>Cantidad</th>
                                    <th>Observaciones</th>
                                    <!-- <th>Orden prod.</th> -->
                                </tr>
                            </thead>
                            <tbody id="tbody-historial">
                            </tbody>
                        </table>
                    </div>
                    <div id="paginacion-historial"></div>
                </div>
            </div>
        </div>
    </div>

<script>
function cargarHistorial(page) {
    crudPostListadoPaginado(
        'historial_movimientos_data.php',
        { action: 'listar_html' },
        '#tbody-historial',
        '#paginacion-historial',
        page || 1
    );
}

$(function() {
    cargarHistorial(1);
    bindCrudPagination('#paginacion-historial', cargarHistorial);
});
</script>
</body>
</html>
