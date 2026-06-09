<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Pagination.php';

$action = $_POST['action'] ?? '';

if ($action !== 'listar_html') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Captura de variables desde la petición AJAX
$taller_id        = isset($_POST['taller_id']) ? (int)$_POST['taller_id'] : 0;
$orden_id         = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;
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
    } else {
        $where[] = "ot.recibido = 0";
    }
}

if ($fecha_desde !== '') {
    $fDesde = $conn->real_escape_string($fecha_desde);
    $where[] = "ot.fecha_asignacion >= '$fDesde 00:00:00'";
}

if ($fecha_hasta !== '') {
    $fHasta = $conn->real_escape_string($fecha_hasta);
    $where[] = "ot.fecha_asignacion <= '$fHasta 23:59:59'";
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
        ot.fecha_entrega,
        ot.recibido,
        t.nombre AS taller_nombre
    FROM ordenes_talleres ot
    INNER JOIN talleres t ON ot.taller_id = t.id
" . $fil;

// Inicialización de la paginación nativa de tu sistema
$total = Pagination::countFromSubquery($conn, $sqlBody);
$pg = Pagination::fromInput($total, $_POST);

$sql = $sqlBody . '
    ORDER BY ot.id DESC
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
        
        // Formateo de fechas de control
        $fDespacho = $r['fecha_asignacion'] ? date('d/m/Y h:i A', strtotime($r['fecha_asignacion'])) : '—';
        $fRetorno  = $r['fecha_entrega'] ? date('d/m/Y h:i A', strtotime($r['fecha_entrega'])) : '<i class="text-muted">No retornado</i>';
        
        // Renderización estética de Badges según estatus del tránsito físico
        if ($r['recibido'] == 1) {
            $transitoHtml = '<span style="background-color: #198754; color: #ffffff; padding: 4px 8px; border-radius: 6px; font-weight: bold; display: inline-block; width: 140px; text-align: center;"><i class="fas fa-check-circle"></i> Recibido</span>';
        } else {
            $transitoHtml = '<span style="background-color: #0d6efd; color: #ffffff; padding: 4px 8px; border-radius: 6px; font-weight: bold; display: inline-block; width: 140px; text-align: center;"><i class="fas fa-truck-moving"></i> Taller</span>';
        }

        // Sanitización y truncado de observaciones largas
        $observaciones = htmlspecialchars(substr($r['observaciones'] ?? '', 0, 100));
        if (strlen($r['observaciones'] ?? '') > 100) {
            $observaciones .= '…';
        }

        echo '<tr>';
        echo '<td>' . $i . '</td>';
        echo '<td><strong class="text-primary">#' . $r['orden_produccion_id'] . '</strong></td>';
        echo '<td>' . htmlspecialchars($r['taller_nombre']) . '</td>';
        echo '<td>' . $fDespacho . '</td>';
        echo '<td>' . $fRetorno . '</td>';
        echo '<td style="text-align: center; vertical-align: middle;">' . $transitoHtml . '</td>';
        echo '<td class="text-muted" style="font-size: 13px;">' . ($observaciones ?: '<i>Sin observaciones</i>') . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr><td colspan="7" class="text-center text-muted" style="padding: 25px;">No se encontraron movimientos de talleres con los filtros aplicados.</td></tr>';
}

$rowsHtml = ob_get_clean();
Pagination::sendJsonList($rowsHtml, $pg);
$conn->close();
exit;