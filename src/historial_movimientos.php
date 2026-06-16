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
                <h2 class="main-title">Historial de Movimientos</h2>
                <p class="text-muted">Todos los movimientos registrados en inventario (insumos y productos terminados).</p>
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
                                    <label for="filtro-item">Producto / Insumo</label>
                                    <input type="text" id="filtro-item" class="form-control clase-filtro" placeholder="Buscar por nombre...">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-tipo-item">Tipo de Ítem</label>
                                    <select id="filtro-tipo-item" class="form-control clase-filtro">
                                        <option value="">Todos</option>
                                        <option value="insumo">Insumo</option>
                                        <option value="producto">Producto</option>
                                    </select>
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-tipo-movimiento">Movimiento</label>
                                    <select id="filtro-tipo-movimiento" class="form-control clase-filtro">
                                        <option value="">Todos</option>
                                        <option value="entrada">Entrada</option>
                                        <option value="salida">Salida</option>
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
                                        <th style="width: 14%;">Fecha</th>
                                        <th style="width: 10%;" nowrap>Tipo ítem</th>
                                        <th style="width: 25%;">Ítem</th>
                                        <th style="width: 12%; text-align: center;" nowrap>Último Mov.</th>
                                        <th style="width: 13%; text-align: center;" nowrap>Origen Mov.</th>
                                        <th style="width: 10%;">Cantidad</th>
                                        <th style="width: 15%;">Observaciones</th>
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
    </div>

<script>
function cargarHistorial(page) {
    let params = { 
        action: 'listar_html',
        buscar_item: $('#filtro-item').val(),
        tipo_item: $('#filtro-tipo-item').val(),
        tipo_movimiento: $('#filtro-tipo-movimiento').val(),
        fecha_desde: $('#filtro-desde').val(),
        fecha_hasta: $('#filtro-hasta').val()
    };

    crudPostListadoPaginado(
        'historial_movimientos_data.php',
        params,
        '#tbody-historial',
        '#paginacion-historial',
        page || 1
    );
}

$(function() {

    cargarHistorial(1);
    bindCrudPagination('#paginacion-historial', cargarHistorial);

    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    $('.clase-filtro').on('keyup change', function() {
        cargarHistorial(1);
    });

    $('#btn-limpiar-filtros').on('click', function() {
        $('#filtro-item').val('');
        $('#filtro-tipo-item').val('');
        $('#filtro-tipo-movimiento').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        cargarHistorial(1);
    });
});
</script>
</body>
</html>