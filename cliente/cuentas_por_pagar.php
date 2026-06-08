<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['id_cliente'])) {
    header('Location: ../index.php');
    exit;
}
require_once __DIR__ . '/../template/header.php';
?>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Cuentas por pagar</h2>
                <p class="text-muted">
                    Saldo pendiente de sus cotizaciones financiadas. Registre sus pagos hasta completar el total de la venta.
                </p>

                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="filtro-estado-cpp">Mostrar</label>
                        <select id="filtro-estado-cpp" class="form-control">
                            <option value="pendiente">Solo pendientes</option>
                            <option value="pagada">Solo pagadas</option>
                            <option value="todas">Todas</option>
                        </select>
                    </div>
                </div>

                <div class="table-container">
                    <table class="recipe-table" id="tabla-cuentas-por-pagar">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cotización / Venta</th>
                                <th>Total</th>
                                <th>Pagado</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-cpp"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-abono-cpp" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h5 class="modal-title">Registrar pago</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="abono-cpp-cuenta-id" value="">
                    <p id="abono-cpp-resumen" class="text-muted"></p>
                    <div class="form-group">
                        <label>Monto del pago <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="abono-cpp-monto" class="form-control" required>
                        <small class="text-muted">Saldo pendiente: <strong id="abono-cpp-saldo-label">$0.00</strong></small>
                    </div>
                    <div class="form-group">
                        <label>Forma de pago <span class="text-danger">*</span></label>
                        <select id="abono-cpp-forma-pago" class="form-control" required>
                            <option value="">Seleccione...</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Referencia / Nº comprobante <span class="text-danger">*</span></label>
                        <input type="text" id="abono-cpp-referencia" class="form-control" maxlength="120">
                    </div>
                    <div class="form-group">
                        <label>Observaciones</label>
                        <input type="text" id="abono-cpp-observaciones" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn-guardar-abono-cpp">Guardar pago</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-pagos-cpp" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h5 class="modal-title">Historial de pagos</h5>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Monto</th>
                                    <th>Forma</th>
                                    <th>Referencia</th>
                                    <th>Tipo</th>
                                </tr>
                            </thead>
                            <tbody id="tbody-historial-pagos-cpp">
                                <tr><td colspan="5" class="text-center">Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
$(function() {
    function poblarFormasPago(callback) {
        $.getJSON('cuentas_por_pagar_data.php', { action: 'listar_formas_pago' }, function(resp) {
            var $sel = $('#abono-cpp-forma-pago');
            $sel.find('option:not(:first)').remove();
            (resp.formas || []).forEach(function(f) {
                $sel.append($('<option></option>').val(f.id).text(f.nombre));
            });
            if (typeof callback === 'function') callback();
        });
    }

    function cargarCuentas() {
        $.get('cuentas_por_pagar_data.php', {
            action: 'listar_cuentas',
            filtro: $('#filtro-estado-cpp').val()
        }, function(html) {
            $('#tbody-cpp').html(html);
        });
    }

    $('#filtro-estado-cpp').on('change', cargarCuentas);

    $(document).on('click', '.btn-registrar-pago-cpp', function() {
        var btn = $(this);
        var saldo = parseFloat(btn.data('saldo')) || 0;
        $('#abono-cpp-cuenta-id').val(btn.data('id'));
        $('#abono-cpp-monto').attr('max', saldo.toFixed(2)).val(saldo.toFixed(2));
        $('#abono-cpp-saldo-label').text('$' + saldo.toFixed(2));
        var cod = btn.data('codigo') || '';
        $('#abono-cpp-resumen').text((cod ? 'Cotización ' + cod + ' · ' : '') + 'Venta #' + (btn.data('venta') || ''));
        $('#abono-cpp-referencia').val('');
        $('#abono-cpp-observaciones').val('');
        poblarFormasPago(function() {
            $('#abono-cpp-forma-pago').val('');
            $('#modal-abono-cpp').modal('show');
        });
    });

    $('#btn-guardar-abono-cpp').on('click', function() {
        var cuentaId = $('#abono-cpp-cuenta-id').val();
        var monto = parseFloat($('#abono-cpp-monto').val());
        var saldoMax = parseFloat($('#abono-cpp-monto').attr('max')) || 0;
        var formaId = $('#abono-cpp-forma-pago').val();
        var referencia = ($('#abono-cpp-referencia').val() || '').trim();
        var observaciones = ($('#abono-cpp-observaciones').val() || '').trim();

        if (!cuentaId) return;
        if (isNaN(monto) || monto <= 0) {
            Swal.fire({ icon: 'warning', text: 'Indique un monto válido.' });
            return;
        }
        if (monto > saldoMax + 0.009) {
            Swal.fire({ icon: 'warning', text: 'El monto no puede superar el saldo pendiente.' });
            return;
        }
        if (!formaId) {
            Swal.fire({ icon: 'warning', text: 'Seleccione la forma de pago.' });
            return;
        }
        if (!referencia && !observaciones) {
            Swal.fire({ icon: 'warning', text: 'Indique la referencia del comprobante o una observación.' });
            return;
        }

        $.post('cuentas_por_pagar_data.php', {
            action: 'registrar_pago',
            cuenta_id: cuentaId,
            monto: monto,
            forma_pago_id: formaId,
            referencia: referencia,
            observaciones: observaciones
        }, function(resp) {
            if (resp && resp.success) {
                $('#modal-abono-cpp').modal('hide');
                Swal.fire({ icon: 'success', text: resp.mensaje });
                cargarCuentas();
            } else {
                Swal.fire({ icon: 'error', text: (resp && resp.mensaje) ? resp.mensaje : 'No se pudo registrar el pago.' });
            }
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', text: 'Error de comunicación con el servidor.' });
        });
    });

    $(document).on('click', '.btn-ver-pagos-cpp', function() {
        var cuentaId = $(this).data('id');
        $('#tbody-historial-pagos-cpp').html('<tr><td colspan="5" class="text-center">Cargando...</td></tr>');
        $('#modal-pagos-cpp').modal('show');

        $.getJSON('cuentas_por_pagar_data.php', { action: 'listar_pagos', cuenta_id: cuentaId }, function(resp) {
            if (resp.error) {
                $('#tbody-historial-pagos-cpp').html('<tr><td colspan="5" class="text-danger text-center">' + resp.error + '</td></tr>');
                return;
            }
            var pagos = resp.pagos || [];
            if (!pagos.length) {
                $('#tbody-historial-pagos-cpp').html('<tr><td colspan="5" class="text-center">Sin pagos registrados.</td></tr>');
                return;
            }
            var html = '';
            pagos.forEach(function(p) {
                var tipo = p.es_pago_inicial ? 'Pago inicial' : (p.origen === 'cliente' ? 'Su pago' : 'Registro interno');
                html += '<tr>';
                html += '<td>' + (p.fecha || '') + '</td>';
                html += '<td>$' + parseFloat(p.monto).toFixed(2) + '</td>';
                html += '<td>' + (p.forma_pago || '—') + '</td>';
                html += '<td>' + (p.referencia || '—') + '</td>';
                html += '<td>' + tipo + '</td>';
                html += '</tr>';
            });
            $('#tbody-historial-pagos-cpp').html(html);
        });
    });

    poblarFormasPago();
    cargarCuentas();
});
</script>
<?php require_once __DIR__ . '/../template/footer.php'; ?>
