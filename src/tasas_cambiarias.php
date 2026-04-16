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
                <p class="text-muted">Las tasas se registran automaticamente desde BCV por tarea programada, y tambien puedes cargar una tasa manual.</p>
                <div class="row mb-3">
                    <div class="col-md-12">
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

    $('#btn-registrar-manual').on('click', function() {
        $('#input-tasa').val('');
        $('#input-fecha-hora').val('');
        $('#modal-registrar').modal('show');
    });

    $('#btn-guardar-manual').on('click', function() {
        var tasa = $('#input-tasa').val().trim().replace(',', '.');
        var fechaHora = $('#input-fecha-hora').val();
        if (!tasa || parseFloat(tasa) <= 0) {
            Swal.fire({ icon: 'warning', text: 'Ingrese una tasa válida mayor que 0.' });
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
                Swal.fire({ icon: 'success', text: res.message });
                cargarListado();
            } else {
                Swal.fire({ icon: 'error', text: res && res.message ? res.message : 'Error al registrar.' });
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
            Swal.fire({ icon: 'error', text: msg });
        });
    });

    $(document).on('click', '.btn-borrar-tasa', function() {
        var id = $(this).data('id');
        Swal.fire({
            icon: 'question',
            text: '¿Eliminar esta tasa?',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function(r) {
            if (!r.isConfirmed) return;
            $.post('tasas_cambiarias_data.php', { action: 'borrar', id: id }, function(res) {
                if (res && res.success) {
                    cargarListado();
                } else {
                    Swal.fire({ icon: 'error', text: 'Error: ' + (res && res.message ? res.message : '') });
                }
            }, 'json').fail(function() { Swal.fire({ icon: 'error', text: 'Error de conexión.' }); });
        });
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
