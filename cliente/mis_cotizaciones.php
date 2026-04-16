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
                                <th>Comprobante</th>
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

    <div class="modal fade" id="modalComprobanteCot" tabindex="-1" role="dialog" aria-labelledby="modalComprobanteTitulo">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalComprobanteTitulo">Comprobante de pago <span id="modal-comp-cod" class="badge badge-secondary"></span></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Puede indicar el <strong>número de referencia</strong> del pago y/o <strong>adjuntar</strong> una imagen o PDF del comprobante (máx. 8&nbsp;MB).</p>
                    <div id="comp-estado-previo" class="alert alert-light border small mb-3" style="display:none;"></div>
                    <form id="form-comprobante-cot" enctype="multipart/form-data">
                        <input type="hidden" name="id_cotizacion" id="form-comp-id" value="">
                        <div class="form-group">
                            <label for="comp-referencia">Número o referencia del pago</label>
                            <input type="text" class="form-control" id="comp-referencia" name="comprobante_referencia" maxlength="120" placeholder="Ej. referencia del banco, últimos dígitos, etc." autocomplete="off">
                        </div>
                        <div class="form-group mb-0">
                            <label for="comp-archivo">Archivo del comprobante (opcional si ya indicó referencia)</label>
                            <input type="file" class="form-control-file" id="comp-archivo" name="archivo_comprobante" accept=".pdf,.jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp,application/pdf">
                            <small class="form-text text-muted">PDF, JPG, PNG o WEBP.</small>
                        </div>
                    </form>
                    <div id="comp-mensaje" class="mt-2 small" style="display:none;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-guardar-comprobante">Guardar</button>
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
        window.cargarListaMisCotizaciones = cargarLista;

        $('#btn-guardar-comprobante').on('click', function() {
            var id = $('#form-comp-id').val();
            var $msg = $('#comp-mensaje');
            $msg.hide().removeClass('text-danger text-success').text('');
            var fd = new FormData();
            fd.append('action', 'guardar_comprobante_cotizacion');
            fd.append('id_cotizacion', id);
            fd.append('comprobante_referencia', $('#comp-referencia').val() || '');
            var fileInput = document.getElementById('comp-archivo');
            if (fileInput && fileInput.files && fileInput.files.length) {
                fd.append('archivo_comprobante', fileInput.files[0]);
            }
            $.ajax({
                url: 'mis_cotizaciones_data.php',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.error) {
                        $msg.addClass('text-danger').text(resp.error).show();
                        return;
                    }
                    if (resp && resp.ok) {
                        $('#modalComprobanteCot').modal('hide');
                        cargarLista();
                    } else {
                        $msg.addClass('text-danger').text('Respuesta inesperada.').show();
                    }
                },
                error: function(xhr) {
                    var t = 'Error al guardar.';
                    try {
                        var j = xhr.responseJSON;
                        if (j && j.error) t = j.error;
                    } catch (e) {}
                    $msg.addClass('text-danger').text(t).show();
                }
            });
        });
    });

    function abrirModalComprobante(id, codigo) {
        $('#modal-comp-cod').text(codigo);
        $('#form-comp-id').val(id);
        $('#comp-referencia').val('');
        $('#comp-archivo').val('');
        $('#comp-mensaje').hide().text('');
        $('#comp-estado-previo').hide().empty();
        $.ajax({
            url: 'mis_cotizaciones_data.php',
            type: 'GET',
            data: { action: 'datos_comprobante_cotizacion', id: id },
            dataType: 'json',
            success: function(d) {
                if (d && d.error) {
                    $('#comp-estado-previo').removeClass('alert-warning').addClass('alert-danger').html($('<div>').text(d.error).html()).show();
                    $('#modalComprobanteCot').modal('show');
                    return;
                }
                if (d) {
                    $('#comp-referencia').val(d.comprobante_referencia != null ? d.comprobante_referencia : '');
                }
                var prev = '';
                if (d.comprobante_fecha) {
                    prev += '<div>Última actualización: ' + $('<div>').text(d.comprobante_fecha).html() + '</div>';
                }
                if (d.tiene_archivo) {
                    prev += '<div class="mt-1"><a target="_blank" href="descargar_comprobante_cotizacion.php?id=' + parseInt(id, 10) + '">Ver archivo actual</a></div>';
                }
                if (prev) {
                    $('#comp-estado-previo').removeClass('alert-danger').addClass('alert-light border').html(prev).show();
                }
                $('#modalComprobanteCot').modal('show');
            },
            error: function() {
                $('#comp-estado-previo').removeClass('alert-light').addClass('alert-danger').html('No se pudieron cargar los datos.').show();
                $('#modalComprobanteCot').modal('show');
            }
        });
    }

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
                    var refC = (c.comprobante_referencia || '').trim();
                    var archC = (c.comprobante_archivo || '').trim();
                    var fechaComp = (c.comprobante_fecha || '').trim();
                    if (refC || archC) {
                        info += '<div class="col-md-12 mt-2 pt-2 border-top"><strong>Comprobante de pago</strong>';
                        if (fechaComp) info += ' <span class="text-muted small">(' + $('<div>').text(fechaComp).html() + ')</span>';
                        info += '<div class="small mt-1">';
                        if (refC) info += '<div><span class="text-muted">Referencia:</span> ' + $('<div>').text(refC).html() + '</div>';
                        if (archC) info += '<div><a target="_blank" href="descargar_comprobante_cotizacion.php?id=' + parseInt(id, 10) + '" class="btn btn-sm btn-outline-primary mt-1">Ver archivo adjunto</a></div>';
                        info += '</div></div>';
                    }
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
