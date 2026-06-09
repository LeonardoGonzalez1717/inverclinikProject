<?php
require_once __DIR__ . '/../template/header.php';
require_once __DIR__ . '/../connection/connection.php';

$conn->query(
    'CREATE TABLE IF NOT EXISTS formas_pago (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(60) NOT NULL UNIQUE,
        activo TINYINT(1) NOT NULL DEFAULT 1,
        creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
);

$formasPago = [];
$rfp = $conn->query('SELECT id, nombre FROM formas_pago WHERE activo = 1 ORDER BY nombre ASC');
if ($rfp) {
    while ($rowFp = $rfp->fetch_assoc()) {
        $formasPago[] = $rowFp;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cuentas por cobrar - INVERCLINIK</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Cuentas por cobrar</h2>

                <div class="row mb-3">
                    <label for="filtro-estado" class="col-md-1">Mostrar</label>
                    <div class="col-md-4">
                        <select id="filtro-estado" class="form-control">
                            <option value="pendiente">Solo pendientes</option>
                            <option value="pagada">Solo pagadas</option>
                            <option value="todas">Todas</option>
                        </select>
                    </div>
                </div>
                <p class="text-muted"> Ventas financiadas con pago inicial del cliente. Registre abonos hasta cubrir el total de la venta. </p>


                <div class="table-container">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Cliente / Venta</th>
                                <th>Total</th>
                                <th>Pagado</th>
                                <th>Saldo</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-cxc"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-abono" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                    <h5 class="modal-title">Registrar abono</h5>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="abono-cuenta-id" value="">
                    <p id="abono-resumen" class="text-muted"></p>
                    <div class="form-group">
                        <label>Monto del abono <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" id="abono-monto" class="form-control" required>
                        <small class="text-muted">Saldo pendiente: <strong id="abono-saldo-label">$0.00</strong></small>
                    </div>
                    <div class="form-group">
                        <label>Forma de pago <span class="text-danger">*</span></label>
                        <select id="abono-forma-pago" class="form-control" required>
                            <option value="">Seleccione...</option>
                            <?php foreach ($formasPago as $fp) { ?>
                                <option value="<?php echo (int) $fp['id']; ?>">
                                    <?php echo htmlspecialchars($fp['nombre']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Referencia / Nº comprobante</label>
                        <input type="text" id="abono-referencia" class="form-control" maxlength="120">
                    </div>
                    <div class="form-group">
                        <label>Observaciones</label>
                        <input type="text" id="abono-observaciones" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn-guardar-abono">Guardar abono</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modal-pagos" tabindex="-1">
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
                            <tbody id="tbody-historial-pagos">
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
    function cargarCuentas() {
        $.get('cuentas_por_cobrar_data.php', {
            action: 'listar_cuentas',
            filtro: $('#filtro-estado').val()
        }, function(html) {
            $('#tbody-cxc').html(html);
        });
    }

    $('#filtro-estado').on('change', cargarCuentas);

    $(document).on('click', '.btn-registrar-pago', function() {
        var btn = $(this);
        var saldo = parseFloat(btn.data('saldo')) || 0;
        $('#abono-cuenta-id').val(btn.data('id'));
        $('#abono-monto').attr('max', saldo.toFixed(2)).val(saldo.toFixed(2));
        $('#abono-saldo-label').text('$' + saldo.toFixed(2));
        $('#abono-resumen').text('Cliente: ' + (btn.data('cliente') || '') + ' · Venta #' + (btn.data('venta') || ''));
        $('#abono-forma-pago').val('');
        $('#abono-referencia').val('');
        $('#abono-observaciones').val('');
        $('#modal-abono').modal('show');
    });

    $('#btn-guardar-abono').on('click', function() {
        var cuentaId = $('#abono-cuenta-id').val();
        var monto = parseFloat($('#abono-monto').val());
        var saldoMax = parseFloat($('#abono-monto').attr('max')) || 0;
        var formaId = $('#abono-forma-pago').val();

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

        $.post('cuentas_por_cobrar_data.php', {
            action: 'registrar_pago',
            cuenta_id: cuentaId,
            monto: monto,
            forma_pago_id: formaId,
            referencia: $('#abono-referencia').val(),
            observaciones: $('#abono-observaciones').val()
        }, function(resp) {
            if (resp && resp.success) {
                $('#modal-abono').modal('hide');
                Swal.fire({ icon: 'success', text: resp.mensaje });
                cargarCuentas();
            } else {
                Swal.fire({ icon: 'error', text: (resp && resp.mensaje) ? resp.mensaje : 'No se pudo registrar el abono.' });
            }
        }, 'json').fail(function() {
            Swal.fire({ icon: 'error', text: 'Error de comunicación con el servidor.' });
        });
    });

    $(document).on('click', '.btn-ver-pagos', function() {
        var cuentaId = $(this).data('id');
        $('#tbody-historial-pagos').html('<tr><td colspan="5" class="text-center">Cargando...</td></tr>');
        $('#modal-pagos').modal('show');

        $.getJSON('cuentas_por_cobrar_data.php', { action: 'listar_pagos', cuenta_id: cuentaId }, function(resp) {
            if (resp.error) {
                $('#tbody-historial-pagos').html('<tr><td colspan="5" class="text-danger text-center">' + resp.error + '</td></tr>');
                return;
            }
            var pagos = resp.pagos || [];
            if (!pagos.length) {
                $('#tbody-historial-pagos').html('<tr><td colspan="5" class="text-center">Sin pagos registrados.</td></tr>');
                return;
            }
            var html = '';
            pagos.forEach(function(p) {
                var tipo = p.es_pago_inicial ? 'Pago inicial (cliente)' : (p.origen === 'cliente' ? 'Cliente' : 'Interno');
                html += '<tr>';
                html += '<td>' + (p.fecha || '') + '</td>';
                html += '<td>$' + parseFloat(p.monto).toFixed(2) + '</td>';
                html += '<td>' + (p.forma_pago || '—') + '</td>';
                html += '<td>' + (p.referencia || '—') + '</td>';
                html += '<td>' + tipo + '</td>';
                html += '</tr>';
            });
            $('#tbody-historial-pagos').html(html);
        });
    });

    cargarCuentas();
});
</script>
<?php require_once __DIR__ . '/../template/footer.php'; ?>
