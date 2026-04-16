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
                <h2 class="main-title">Respaldos y restauración de la base de datos</h2>
                <h5 class="subtitle">Respaldos guardados</h5>
                <div class="table-container">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>Fecha y hora</th>
                                <th>Nombre del archivo</th>
                                <th>Tamaño</th>
                                <th>Origen</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-respaldos">
                        </tbody>
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

    function cargarListado() {
        $.post('respaldos_bd_data.php', { action: 'listar' }, function(res) {
            var $tb = $('#tbody-respaldos');
            $tb.empty();
            if (!res || !res.success || !res.files || res.files.length === 0) {
                $tb.append('<tr><td colspan="5" class="text-center text-muted py-4">No hay respaldos registrados. Ejecute la migración SQL si acaba de actualizar el sistema. Los nuevos respaldos quedarán listados aquí.</td></tr>');
                return;
            }
            res.files.forEach(function(f) {
                var d = new Date(f.mtime * 1000);
                var fecha = d.toLocaleString('es-VE', { dateStyle: 'short', timeStyle: 'short' });
                var nombre = $('<div>').text(f.basename).html();
                var origenTxt = (f.origen === 'manual') ? 'Manual' : 'Automático';
                var actor = $('<div>').text(f.actor || '—').html();
                var origenCell = origenTxt + (actor ? '<br><small class="text-muted">' + actor + '</small>' : '');
                var href = 'respaldos_bd_descargar.php?id=' + encodeURIComponent(f.id);
                var fila = '<tr>' +
                    '<td>' + fecha + '</td>' +
                    '<td>' + nombre + '</td>' +
                    '<td>' + formatBytes(f.size) + '</td>' +
                    '<td>' + origenCell + '</td>' +
                    '<td><a class="btn btn-sm btn-outline-primary" href="' + href + '">Descargar</a></td>' +
                    '</tr>';
                $tb.append(fila);
            });
        }, 'json').fail(function() {
            $('#tbody-respaldos').html('<tr><td colspan="5" class="text-center text-danger">Error al cargar el listado.</td></tr>');
        });
    }

    $(document).ready(function() {
        cargarListado();
    });
})();
</script>

<?php
$conn->close();
require_once __DIR__ . '/../template/footer.php';
