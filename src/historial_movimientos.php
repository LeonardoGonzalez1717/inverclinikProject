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
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Historial de movimientos</h2>
                <p class="text-muted">Todos los movimientos registrados en inventario (insumos y productos terminados).</p>

                <div id="vista-listado">
                    <h5 class="subtitle">Listado de movimientos</h5>
                    <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Tipo ítem</th>
                                    <th>Ítem</th>
                                    <th>Movimiento</th>
                                    <th>Cantidad</th>
                                    <th>Origen</th>
                                    <th>Observaciones</th>
                                    <th>Orden prod.</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-historial">
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
$(function() {
    cargarHistorial();
});

function cargarHistorial() {
    $.post('historial_movimientos_data.php', { action: 'listar_html' }, function(html) {
        $('#tbody-historial').html(html);
    }).fail(function() {
        $('#tbody-historial').html('<tr><td colspan="9" class="text-center text-danger">Error al cargar el historial.</td></tr>');
    });
}
</script>
</body>
</html>
