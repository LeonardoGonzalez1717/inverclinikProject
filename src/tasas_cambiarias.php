<?php
require_once __DIR__ . '/../template/header.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasas cambiarias - INVERCLINIK</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Tasas cambiarias</h2>
                <p class="text-muted">Registrar tasa manual o desde BCV y borrar registros individuales.</p>

                <div class="row mb-3">
                    <div class="col-md-12">
                        <button type="button" class="btn btn-success" id="btn-obtener-bcv">Obtener tasa BCV y registrar</button>
                        <button type="button" class="btn btn-primary" id="btn-registrar-manual">Registrar tasa manual</button>
                    </div>
                </div>

                <div class="table-container">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Tasa (BS/USD)</th>
                                <th>Origen</th>
                                <th>Usuario</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-tasas">
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Registrar tasa manual -->
    <div class="modal fade" id="modal-registrar" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar tasa manual</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tasa (BS por 1 USD) <span class="text-danger">*</span></label>
                        <input type="text" id="input-tasa" class="form-control" placeholder="Ej: 433.1664" required>
                    </div>
                    <div class="form-group">
                        <label>Fecha y hora (opcional; si se deja vacío se usa la actual)</label>
                        <input type="datetime-local" id="input-fecha-hora" class="form-control">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btn-guardar-manual">Guardar</button>
                </div>
            </div>
        </div>
    </div>

<script>
(function() {
    function cargarListado() {
        $.post('tasas_cambiarias_data.php', { action: 'listar_html' }, function(html) {
            $('#tbody-tasas').html(html);
        });
    }

    $('#btn-obtener-bcv').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).text('Obteniendo...');
        $.post('tasas_cambiarias_data.php', { action: 'obtener_bcv' }, function(res) {
            if (res && res.success && res.tasa) {
                $.post('tasas_cambiarias_data.php', {
                    action: 'registrar',
                    tasa: res.tasa,
                    origen: 'bcv'
                }, function(r2) {
                    if (r2 && r2.success) {
                        alert('Tasa BCV registrada: ' + res.tasa);
                        cargarListado();
                    } else {
                        alert(r2 && r2.message ? r2.message : 'Error al registrar.');
                    }
                }, 'json').fail(function(xhr) {
                    var msg = 'Error de conexión.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg = xhr.responseJSON.message;
                    } else if (xhr.responseText) {
                        try {
                            var o = JSON.parse(xhr.responseText);
                            if (o.message) msg = o.message;
                        } catch (e) {}
                    }
                    alert(msg);
                });
            } else {
                alert('Error: ' + (res && res.message ? res.message : 'No se pudo obtener la tasa del BCV.'));
            }
        }, 'json').fail(function() {
            alert('Error de conexión.');
        }).always(function() {
            $btn.prop('disabled', false).text('Obtener tasa BCV y registrar');
        });
    });

    $('#btn-registrar-manual').on('click', function() {
        $('#input-tasa').val('');
        $('#input-fecha-hora').val('');
        $('#modal-registrar').modal('show');
    });

    $('#btn-guardar-manual').on('click', function() {
        var tasa = $('#input-tasa').val().trim().replace(',', '.');
        var fechaHora = $('#input-fecha-hora').val();
        if (!tasa || parseFloat(tasa) <= 0) {
            alert('Ingrese una tasa válida mayor que 0.');
            return;
        }
        $.post('tasas_cambiarias_data.php', {
            action: 'registrar',
            tasa: tasa,
            fecha_hora: fechaHora,
            origen: 'manual'
        }, function(res) {
            if (res && res.success) {
                $('#modal-registrar').modal('hide');
                alert(res.message);
                cargarListado();
            } else {
                alert(res && res.message ? res.message : 'Error al registrar.');
            }
        }, 'json').fail(function(xhr) {
            var msg = 'Error de conexión.';
            if (xhr.responseJSON && xhr.responseJSON.message) {
                msg = xhr.responseJSON.message;
            } else if (xhr.responseText) {
                try {
                    var o = JSON.parse(xhr.responseText);
                    if (o.message) msg = o.message;
                } catch (e) {}
            }
            alert(msg);
        });
    });

    $(document).on('click', '.btn-borrar-tasa', function() {
        var id = $(this).data('id');
        if (!confirm('¿Eliminar esta tasa?')) return;
        $.post('tasas_cambiarias_data.php', { action: 'borrar', id: id }, function(res) {
            if (res && res.success) {
                cargarListado();
            } else {
                alert('Error: ' + (res && res.message ? res.message : ''));
            }
        }, 'json').fail(function() { alert('Error de conexión.'); });
    });

    $(document).ready(function() {
        cargarListado();
    });
})();
</script>

<?php
$conn->close();
require_once __DIR__ . '/../template/footer.php';
?>
</body>
</html>
