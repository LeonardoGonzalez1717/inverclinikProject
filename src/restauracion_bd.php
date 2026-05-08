<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['iduser']) || (int) ($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../template/header.php';
?>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Restauración de la base de datos</h2>
                <p class="subtitle">Selecciona un respaldo registrado para importarlo nuevamente.</p>

                <div class="alert alert-warning" role="alert">
                    Esta acción reemplaza la información actual de la base de datos. Se recomienda realizarla solo cuando sea necesario.
                </div>

                <div class="table-container">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Archivo</th>
                                <th>Tamaño</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-restauraciones"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
(function() {
    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(2) + ' MB';
    }

    function restaurarRespaldo(id, nombre) {
        Swal.fire({
            icon: 'warning',
            title: 'Confirmar restauración',
            text: 'Se importará el respaldo "' + nombre + '". ¿Deseas continuar?',
            showCancelButton: true,
            confirmButtonText: 'Sí, restaurar',
            cancelButtonText: 'Cancelar'
        }).then(function(r) {
            if (!r.isConfirmed) {
                return;
            }

            Swal.fire({
                title: 'Restaurando...',
                text: 'Por favor espera mientras se importa el respaldo.',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });

            $.post('respaldos_bd_data.php', {
                action: 'restaurar',
                respaldo_id: id,
                respaldo_file: nombre
            }, function(res) {
                if (res && res.success) {
                    Swal.fire({ icon: 'success', text: res.message || 'Restauración completada.' });
                } else {
                    Swal.fire({ icon: 'error', text: (res && res.message) ? res.message : 'No se pudo restaurar la base de datos.' });
                }
            }, 'json').fail(function() {
                Swal.fire({ icon: 'error', text: 'Error de conexión al restaurar.' });
            });
        });
    }

    function cargarListado() {
        $.post('respaldos_bd_data.php', { action: 'listar' }, function(res) {
            var $tb = $('#tbody-restauraciones');
            $tb.empty();

            if (!res || !res.success || !res.files || res.files.length === 0) {
                $tb.append('<tr><td colspan="4" class="text-center text-muted py-4">No hay respaldos registrados para restaurar.</td></tr>');
                return;
            }

            res.files.forEach(function(f) {
                var d = new Date(f.mtime * 1000);
                var fecha = d.toLocaleString('es-VE', { dateStyle: 'short', timeStyle: 'short' });
                var nombreRaw = String(f.basename || '');
                var nombre = $('<div>').text(nombreRaw).html();
                var nombreAttr = $('<div>').text(nombreRaw).html();
                var btn = '<button class="btn btn-sm btn-danger btn-restaurar" data-id="' + String(f.id) + '" data-name="' + nombre + '" data-file="' + nombreAttr + '">Restaurar</button>';
                var fila = '<tr>' +
                    '<td>' + fecha + '</td>' +
                    '<td>' + nombre + '</td>' +
                    '<td>' + formatBytes(f.size) + '</td>' +
                    '<td>' + btn + '</td>' +
                    '</tr>';
                $tb.append(fila);
            });
        }, 'json').fail(function() {
            $('#tbody-restauraciones').html('<tr><td colspan="4" class="text-center text-danger">Error al cargar respaldos.</td></tr>');
        });
    }

    $(document).on('click', '.btn-restaurar', function() {
        var id = parseInt($(this).attr('data-id'), 10) || 0;
        var file = $(this).attr('data-file') || '';
        if (!file) {
            Swal.fire({ icon: 'warning', text: 'Respaldo inválido.' });
            return;
        }
        restaurarRespaldo(id, file);
    });

    $(document).ready(function() {
        cargarListado();
    });
})();
</script>

<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once __DIR__ . '/../template/footer.php';
?>
