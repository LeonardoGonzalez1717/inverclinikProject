<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['id_cliente'])) {
    header('Location: ../index.php');
    exit;
}
require_once '../template/header.php';
?>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Mis cotizaciones</h2>
                <!--  -->
                <div class="table-container">
                    <table class="recipe-table" id="tabla-mis-cotizaciones">
                        <thead class="thead-light">
                            <tr>
                                <th>Cotización</th>
                                <th>Fecha</th>
                                <th>Presupuesto origen</th>
                                <th>Estado</th>
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

    <div class="modal fade" id="modalDetalleCot" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalle de la cotización <span id="modal-cot-cod" class="badge badge-primary"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div id="modal-cot-cabecera" class="mb-3 p-3 bg-light rounded border small" style="display:none;"></div>
                    <h6 class="mb-2 text-muted">Artículos</h6>
                    <table class="table table-sm table-bordered">
                        <thead class="thead-light">
                            <tr>
                                <th>Producto</th>
                                <th>Talla</th>
                                <th>Cant.</th>
                                <th>P. unit. ($)</th>
                                <th>Subtotal ($)</th>
                            </tr>
                        </thead>
                        <tbody id="detalle-cot-contenido"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
    $(document).ready(function() {
        cargarLista();
        function cargarLista() {
            $.ajax({
                url: 'mis_cotizaciones_data.php',
                type: 'GET',
                data: { action: 'listar_mis_cotizaciones' },
                success: function(html) {
                    $('#tabla-mis-cotizaciones tbody').html(html);
                }
            });
        }
    });

    function verDetalleCotizacion(id, codigo) {
        $('#modal-cot-cod').text(codigo);
        $('#modal-cot-cabecera').hide().empty();
        $.ajax({
            url: 'mis_cotizaciones_data.php',
            type: 'GET',
            data: { action: 'ver_detalle_cotizacion_cliente', id: id },
            dataType: 'json',
            success: function(resp) {
                if (resp && resp.error) {
                    $('#detalle-cot-contenido').html('<tr><td colspan="5" class="text-danger">' + $('<div>').text(resp.error).html() + '</td></tr>');
                    $('#modalDetalleCot').modal('show');
                    return;
                }
                var items = (resp && resp.items) ? resp.items : (Array.isArray(resp) ? resp : null);
                if (!items) {
                    $('#detalle-cot-contenido').html('<tr><td colspan="5" class="text-danger">No se pudo cargar el detalle.</td></tr>');
                    $('#modalDetalleCot').modal('show');
                    return;
                }
                if (resp.cabecera) {
                    var c = resp.cabecera;
                    var po = c.presupuesto_origen ? $('<div>').text(c.presupuesto_origen).html() : '<span class="text-muted">—</span>';
                    var info = '<div class="row">';
                    info += '<div class="col-md-6 mb-2"><strong>Cotización:</strong> ' + $('<div>').text(c.codigo_cotizacion || '').html() + '</div>';
                    info += '<div class="col-md-6 mb-2"><strong>Fecha:</strong> ' + $('<div>').text(c.fecha || '').html() + '</div>';
                    info += '<div class="col-md-6 mb-2"><strong>Presupuesto origen:</strong> ' + po + '</div>';
                    info += '<div class="col-md-6 mb-2"><strong>Estado:</strong> <span class="badge ' + (c.estado_class || 'badge-secondary') + '">' + $('<div>').text(c.estado_texto || '').html() + '</span></div>';
                    info += '<div class="col-md-12 mb-0"><strong>Total:</strong> $' + parseFloat(c.total || 0).toFixed(2) + '</div>';
                    info += '</div>';
                    $('#modal-cot-cabecera').html(info).show();
                }
                var html = '';
                items.forEach(function(item) {
                    html += '<tr>'
                        + '<td>' + $('<div>').text(item.producto || '').html() + '</td>'
                        + '<td>' + $('<div>').text(item.talla || '—').html() + '</td>'
                        + '<td>' + $('<div>').text(item.cantidad != null ? String(item.cantidad) : '').html() + '</td>'
                        + '<td>$' + parseFloat(item.precio_unitario || 0).toFixed(2) + '</td>'
                        + '<td>$' + parseFloat(item.subtotal || 0).toFixed(2) + '</td>'
                        + '</tr>';
                });
                if (!html) {
                    html = '<tr><td colspan="5" class="text-center">Sin líneas en esta cotización.</td></tr>';
                }
                $('#detalle-cot-contenido').html(html);
                $('#modalDetalleCot').modal('show');
            }
        });
    }
</script>
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once '../template/footer.php';
?>
