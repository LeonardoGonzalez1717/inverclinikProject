<?php require_once('../template/header.php'); ?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Proveedores</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title"> Presupuestos</h2>
                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Proveedores</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Código</th>
                                        <th>Fecha</th>
                                        <th>Estatus</th>
                                        <th>Total</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetalle" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle de Presupuesto: <span id="modal-cod" class="badge badge-primary"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Talla</th>
                                <th>Cant.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody id="detalle-contenido"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
    $(document).ready(function() {
        const idClienteActual = 1;
        cargarLista(idClienteActual);

        function cargarLista(id) {
            $.ajax({
                url: 'mis_pedidos_data.php',
                type: 'GET',
                data: { action: 'listar_mis_presupuestos', id_cliente: id },
                success: function(html) {
                    $('.recipe-table tbody').html(html);
                }
            });
        }
    });

    

    function verDetallePresupuesto(id, codigo) {
        console.log("hola");
        
        $('#modal-cod').text(codigo);
        $.ajax({
            url: 'mis_pedidos_data.php',
            type: 'GET',
            data: { action: 'ver_detalle_cliente', id: id },
            dataType: 'json',
            success: function(resp) {
                let html = "";
                resp.forEach(item => {
                    html += `<tr>
                                <td>${item.producto}</td>
                                <td>${item.talla}</td>
                                <td>${item.cantidad}</td>
                                <td>$${parseFloat(item.subtotal).toFixed(2)}</td>
                            </tr>`;
                });
                $('#detalle-contenido').html(html);
                $('#modalDetalle').modal('show');
            }
        });
    }
</script>