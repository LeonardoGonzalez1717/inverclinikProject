<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/orden_numero.php';

$action = $_POST['action'] ?? '';

if ($action !== 'listar_html') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Captura de variables desde la petición AJAX
$taller_id        = isset($_POST['taller_id']) ? (int)$_POST['taller_id'] : 0;
$orden_id         = parse_id_orden_produccion($_POST['orden_id'] ?? '');
$estatus_transito = isset($_POST['estatus_transito']) ? trim($_POST['estatus_transito']) : '';
$fecha_desde      = isset($_POST['fecha_desde']) ? trim($_POST['fecha_desde']) : '';
$fecha_hasta      = isset($_POST['fecha_hasta']) ? trim($_POST['fecha_hasta']) : '';

$where = [];

if ($taller_id > 0) {
    $where[] = "ot.taller_id = $taller_id";
}

if ($orden_id > 0) {
    $where[] = "ot.orden_produccion_id = $orden_id";
}

if ($estatus_transito !== '') {
    if ($estatus_transito === 'recibido') {
        $where[] = "ot.recibido = 1";
    } elseif ($estatus_transito === 'afuera' || $estatus_transito === 'en_taller') {
        $where[] = "ot.enviado = 1 AND ot.recibido = 0";
    } elseif ($estatus_transito === 'por_enviar') {
        $where[] = "ot.enviado = 0";
    }
}

if ($fecha_desde !== '') {
    $fDesde = $conn->real_escape_string($fecha_desde);
    $where[] = "COALESCE(ot.fecha_envio, ot.fecha_asignacion) >= '$fDesde 00:00:00'";
}

if ($fecha_hasta !== '') {
    $fHasta = $conn->real_escape_string($fecha_hasta);
    $where[] = "COALESCE(ot.fecha_envio, ot.fecha_asignacion) <= '$fHasta 23:59:59'";
}

$fil = "";
if (count($where) > 0) {
    $fil = " WHERE " . implode(" AND ", $where);
}

// Estructura de la query apuntando al flujo de talleres de confección
$sqlBody = "
    SELECT 
        ot.id,
        ot.orden_produccion_id,
        ot.taller_id,
        ot.observaciones,
        ot.fecha_asignacion,
        ot.fecha_envio,
        ot.fecha_entrega,
        ot.enviado,
        ot.recibido,
        t.nombre AS taller_nombre,
        op.creado_en,
        op.fecha_inicio,
        op.cantidad_a_producir,
        p.nombre AS producto_nombre
    FROM ordenes_talleres ot
    INNER JOIN talleres t ON ot.taller_id = t.id
    INNER JOIN ordenes_produccion op ON op.id = ot.orden_produccion_id
    LEFT JOIN recetas_productos rp ON op.receta_producto_id = rp.id
    LEFT JOIN productos p ON rp.producto_id = p.id
" . $fil;

// Inicialización de la paginación nativa de tu sistema
$total = Pagination::countFromSubquery($conn, $sqlBody);
$pg = Pagination::fromInput($total, $_POST);

$sql = $sqlBody . '
    ORDER BY ot.orden_produccion_id DESC, ot.id ASC
' . $pg->limitClause();

$result = $conn->query($sql);
$filas = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $filas[] = $row;
    }
}

ob_start();
$i = $pg->rowNumberStart() - 1;

if (!empty($filas)) {
    foreach ($filas as $r) {
        $i++;
        
        // Formateo de fechas de envío y recepción
        if ($r['enviado'] == 1 && $r['fecha_envio']) {
            $fDespacho = date('d/m/Y h:i A', strtotime($r['fecha_envio']));
        } else {
            $fDespacho = '<span class="text-muted">—</span>';
        }

        if ($r['recibido'] == 1 && $r['fecha_entrega']) {
            $fRetorno = date('d/m/Y h:i A', strtotime($r['fecha_entrega']));
        } else {
            $fRetorno = '<span class="text-muted">—</span>';
        }
        
        // Renderización estética de Badges según estatus del tránsito físico
        if ($r['recibido'] == 1) {
            $transitoHtml = '<span style="background-color: #198754; color: #ffffff; padding: 4px 10px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12px;"><i class="fas fa-check-circle"></i> Recibido</span>';
        } elseif ($r['enviado'] == 1) {
            $transitoHtml = '<span style="background-color: #0d6efd; color: #ffffff; padding: 4px 10px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12px;"><i class="fas fa-truck-moving"></i> En Taller</span>';
        } else {
            $transitoHtml = '<span style="background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; padding: 4px 10px; border-radius: 6px; font-weight: 600; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12px;"><i class="fas fa-hourglass-start"></i> Por Enviar</span>';
        }

        $productoInfo = htmlspecialchars($r['producto_nombre'] ?? '—');
        if (!empty($r['cantidad_a_producir'])) {
            $productoInfo .= '<br><small class="text-muted">' . number_format((float)$r['cantidad_a_producir'], 0) . ' uds.</small>';
        }

        // Sanitización y truncado de observaciones largas
        $observaciones = htmlspecialchars(substr($r['observaciones'] ?? '', 0, 100));
        if (strlen($r['observaciones'] ?? '') > 100) {
            $observaciones .= '…';
        }

        echo '<tr>';
        echo '<td>' . $i . '</td>';
        echo '<td><strong style="color: #0056b3;"><i class="fas fa-file-lines" style="margin-right: 4px; opacity: 0.7;"></i>' . htmlspecialchars(numero_orden_produccion((int)$r['orden_produccion_id'], $r['creado_en'] ?? $r['fecha_inicio'] ?? null)) . '</strong></td>';
        echo '<td>' . $productoInfo . '</td>';
        echo '<td><strong>' . htmlspecialchars($r['taller_nombre']) . '</strong></td>';
        echo '<td>' . $fDespacho . '</td>';
        echo '<td>' . $fRetorno . '</td>';
        echo '<td style="text-align: center; vertical-align: middle;">' . $transitoHtml . '</td>';
        echo '<td class="text-muted" style="font-size: 13px;">' . ($observaciones ?: '<i>Sin observaciones</i>') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="8" class="text-center text-muted" style="padding: 25px;">No se encontraron movimientos de talleres con los filtros aplicados.</td></tr>';
}

$rowsHtml = ob_get_clean();
Pagination::sendJsonList($rowsHtml, $pg);
$conn->close();
exit;