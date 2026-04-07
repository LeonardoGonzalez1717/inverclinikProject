<?php
session_start();
if (empty($_SESSION['iduser']) || (int) ($_SESSION['role_id'] ?? 0) !== 1) {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../lib/Auditoria.php';

$opcionesModulo = [];
$qMod = $conn->query("SELECT DISTINCT modulo FROM auditoria WHERE modulo IS NOT NULL AND TRIM(modulo) != '' ORDER BY modulo ASC");
if ($qMod) {
    while ($r = $qMod->fetch_assoc()) {
        $opcionesModulo[] = $r['modulo'];
    }
}

$opcionesActor = [];
$qAct = $conn->query(
    "SELECT DISTINCT nombre_actor, id_usuario, id_cliente FROM auditoria ORDER BY nombre_actor ASC, id_usuario ASC, id_cliente ASC"
);
$vistosActor = [];
if ($qAct) {
    while ($r = $qAct->fetch_assoc()) {
        $nom = trim((string) ($r['nombre_actor'] ?? ''));
        if ($nom === '') {
            $nom = '—';
        }
        $iu = $r['id_usuario'] !== null ? (int) $r['id_usuario'] : null;
        $ic = $r['id_cliente'] !== null ? (int) $r['id_cliente'] : null;
        if ($iu !== null && $iu > 0) {
            $valor = 'u:' . $iu;
            $etiqueta = $nom . ' (usuario interno)';
        } elseif ($ic !== null && $ic > 0) {
            $valor = 'c:' . $ic;
            $etiqueta = $nom . ' (cliente)';
        } else {
            $valor = 'n:' . rawurlencode($nom);
            $etiqueta = $nom;
        }
        if (isset($vistosActor[$valor])) {
            continue;
        }
        $vistosActor[$valor] = true;
        $opcionesActor[] = ['value' => $valor, 'label' => $etiqueta];
    }
}

require_once __DIR__ . '/../template/header.php';
require_once __DIR__ . '/../template/navbar.php';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditoría del sistema</title>
    <link rel="stylesheet" href="../css/movimientos_inventario.css">
    <style>
        .aud-accion { max-width: 420px; word-break: break-word; font-size: 0.9rem; }
        .aud-filtros {
            display: flex; flex-wrap: wrap; gap: 12px; align-items: flex-end;
            margin-bottom: 20px; padding: 14px; background: #f8f9fa; border-radius: 8px;
        }
        .aud-filtros .form-group { margin-bottom: 0; min-width: 140px; }
        .aud-filtros label { font-size: 0.85rem; font-weight: 600; margin-bottom: 4px; display: block; }
        .aud-filtros select, .aud-filtros input[type="date"] { min-width: 160px; }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Auditoría del sistema</h2>
                <form id="form-filtros-auditoria" class="aud-filtros" autocomplete="off">
                    <div class="form-group">
                        <label for="filtro-usuario">Usuario</label>
                        <select id="filtro-usuario" name="usuario" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($opcionesActor as $op): ?>
                                <option value="<?php echo htmlspecialchars($op['value'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($op['label'], ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="filtro-desde">Fecha desde</label>
                        <input type="date" id="filtro-desde" name="fecha_desde" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="filtro-hasta">Fecha hasta</label>
                        <input type="date" id="filtro-hasta" name="fecha_hasta" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="filtro-tipo">Tipo de movimiento</label>
                        <select id="filtro-tipo" name="tipo_movimiento" class="form-control">
                            <option value="">Todos</option>
                            <?php foreach ($opcionesModulo as $mod): ?>
                                <option value="<?php echo htmlspecialchars($mod, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php echo htmlspecialchars($mod, ENT_QUOTES, 'UTF-8'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <button type="submit" class="btn btn-primary">Filtrar</button>
                        <button type="button" class="btn btn-outline-secondary" id="btn-limpiar-filtros-auditoria">Limpiar</button>
                    </div>
                </form>

                <div class="table-container">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Fecha y hora</th>
                                <th>Usuario / actor</th>
                                <th>Módulo</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbody-auditoria">
                            <tr><td colspan="5" class="text-center text-muted">Cargando…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
function cargarAuditoria() {
    var data = {
        action: 'listar_html',
        usuario: $('#filtro-usuario').val() || '',
        fecha_desde: $('#filtro-desde').val() || '',
        fecha_hasta: $('#filtro-hasta').val() || '',
        tipo_movimiento: $('#filtro-tipo').val() || ''
    };
    $('#tbody-auditoria').html('<tr><td colspan="8" class="text-center text-muted">Cargando…</td></tr>');
    $.post('auditoria_data.php', data, function (html) {
        $('#tbody-auditoria').html(html);
    }).fail(function (xhr) {
        var msg = 'Error al cargar la auditoría.';
        try {
            var j = JSON.parse(xhr.responseText);
            if (j.message) msg = j.message;
        } catch (e) {}
        $('#tbody-auditoria').html('<tr><td colspan="5" class="text-center text-danger">' + msg + '</td></tr>');
    });
}

$(function () {
    $('#form-filtros-auditoria').on('submit', function (e) {
        e.preventDefault();
        cargarAuditoria();
    });
    $('#btn-limpiar-filtros-auditoria').on('click', function () {
        $('#filtro-usuario').val('');
        $('#filtro-desde').val('');
        $('#filtro-hasta').val('');
        $('#filtro-tipo').val('');
        cargarAuditoria();
    });
    cargarAuditoria();
});
</script>
</body>
</html>
