<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/AbastecimientoService.php';

AbastecimientoService::asegurarTablas($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$requestJson = null;

if (empty($action)) {
    $raw = file_get_contents('php://input');
    $requestJson = json_decode($raw, true);
    $action = is_array($requestJson) ? ($requestJson['action'] ?? '') : '';
}

try {
    if ($action === 'listar_html') {
        $buscar_codigo    = isset($_POST['buscar_codigo']) ? trim($_POST['buscar_codigo']) : '';
        $buscar_proveedor = isset($_POST['buscar_proveedor']) ? trim($_POST['buscar_proveedor']) : '';
        $estado           = isset($_POST['estado']) ? trim($_POST['estado']) : '';
        $modalidad        = isset($_POST['modalidad']) ? trim($_POST['modalidad']) : '';
        $fecha_desde      = isset($_POST['fecha_desde']) ? trim($_POST['fecha_desde']) : '';
        $fecha_hasta      = isset($_POST['fecha_hasta']) ? trim($_POST['fecha_hasta']) : '';

        $where = [];

        if ($buscar_codigo !== '') {
            $s = $conn->real_escape_string($buscar_codigo);
            $where[] = "oc.codigo_oc LIKE '%$s%'";
        }

        if ($buscar_proveedor !== '') {
            $sP = $conn->real_escape_string($buscar_proveedor);
            $where[] = "(p.nombre LIKE '%$sP%' OR p.cedrif LIKE '%$sP%')";
        }

        if ($estado !== '') {
            $sE = $conn->real_escape_string($estado);
            $where[] = "oc.estado = '$sE'";
        }

        if ($modalidad !== '') {
            $sM = $conn->real_escape_string($modalidad);
            $where[] = "oc.modalidad = '$sM'";
        }

        if ($fecha_desde !== '') {
            $fD = $conn->real_escape_string($fecha_desde);
            $where[] = "oc.fecha >= '$fD'";
        }

        if ($fecha_hasta !== '') {
            $fH = $conn->real_escape_string($fecha_hasta);
            $where[] = "oc.fecha <= '$fH'";
        }

        $fil = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sqlBase = "
            SELECT 
                oc.id,
                oc.codigo_oc,
                oc.proveedor_id,
                oc.fecha,
                oc.modalidad,
                oc.requisiciones_ids,
                oc.total,
                oc.estado,
                oc.tasa_cambiaria_id,
                tc.tasa AS tasa_oc,
                p.nombre AS proveedor_nombre,
                COUNT(ocd.id) AS cantidad_items,
                COALESCE(SUM(ocd.cantidad), 0) AS cantidad_total
            FROM ordenes_compra oc
            INNER JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN tasas_cambiarias tc ON tc.id = oc.tasa_cambiaria_id
            LEFT JOIN orden_compra_detalles ocd ON ocd.orden_compra_id = oc.id
            {$fil}
            GROUP BY oc.id, oc.codigo_oc, oc.proveedor_id, oc.fecha, oc.modalidad,
                     oc.requisiciones_ids, oc.total, oc.estado, oc.tasa_cambiaria_id,
                     tc.tasa, p.nombre, oc.creado_en
        ";

        $total = Pagination::countFromSubquery($conn, $sqlBase);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = $sqlBase . " ORDER BY oc.id DESC " . $pg->limitClause();
        $result = $conn->query($sql);

        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        ob_start();
        $i = $pg->rowNumberStart() - 1;
        if (!empty($rows)) {
            foreach ($rows as $oc) {
                $i++;
                $idOc = (int) $oc['id'];
                [$estTxt, $estStyle] = AbastecimientoService::etiquetaEstadoOrdenCompra((string) $oc['estado']);

                $totalBs = null;
                if (!empty($oc['tasa_oc']) && (float) $oc['tasa_oc'] > 0) {
                    $totalBs = (float) $oc['total'] * (float) $oc['tasa_oc'];
                }
                $totalBsTexto = $totalBs !== null ? 'Bs. ' . number_format($totalBs, 2, '.', ',') : '—';

                $origenBadge = ($oc['modalidad'] === 'requisicion')
                    ? '<span class="badge badge-info" style="padding:4px 8px;">Desde Requisición</span>'
                    : '<span class="badge badge-secondary" style="padding:4px 8px;"> Directa</span>';

                $codEsc = htmlspecialchars($oc['codigo_oc'], ENT_QUOTES, 'UTF-8');

                echo '<tr>';
                echo '<td>' . htmlspecialchars((string) $i) . '</td>';
                echo '<td nowrap><strong>' . $codEsc . '</strong></td>';
                echo '<td>' . htmlspecialchars($oc['proveedor_nombre']) . '</td>';
                echo '<td nowrap>' . date('d/m/Y', strtotime($oc['fecha'])) . '</td>';
                echo '<td>' . $origenBadge . '</td>';
                echo '<td class="text-center">' . (int) $oc['cantidad_items'] . ' (' . number_format((float) $oc['cantidad_total'], 2, '.', ',') . ')</td>';
                echo '<td style="font-weight:bold; color:#0056b3;">$' . number_format((float) $oc['total'], 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($totalBsTexto) . '</td>';
                echo '<td><span style="' . htmlspecialchars($estStyle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($estTxt) . '</span></td>';
                echo '<td nowrap>';
                echo '<button type="button" class="btn btn-sm btn-primary" onclick="verDetalleOC(' . $idOc . ')" title="Ver Detalle"><i class="fas fa-eye"></i> Ver</button> ';

                if ($oc['estado'] === 'EMITIDA' && !esLector()) {
                    echo '<a href="registrar_compra.php?oc_id=' . $idOc . '&proveedor_id=' . (int) $oc['proveedor_id'] . '" class="btn btn-sm btn-success" title="Registrar Compra desde esta O.C."><i class="fas fa-cart-plus"></i> Comprar</a> ';
                    echo '<button type="button" class="btn btn-sm btn-danger" onclick="cancelarOC(' . $idOc . ', \'' . $codEsc . '\')" title="Cancelar Orden de Compra"><i class="fas fa-ban"></i></button>';
                }

                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10" class="text-center">No se encontraron Órdenes de Compra registradas</td></tr>';
        }

        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    if ($action === 'listar_requisiciones_aprobadas') {
        header('Content-Type: application/json; charset=utf-8');
        $proveedor_id = (int) ($_POST['proveedor_id'] ?? $_GET['proveedor_id'] ?? 0);

        $sql = "
            SELECT 
                r.id,
                r.codigo_requisicion,
                r.fecha_solicitud,
                r.justificacion,
                r.estado,
                r.estado_orden,
                COALESCE(u.username, 'Sistema') AS solicitante_nombre,
                COUNT(CASE WHEN (rd.cantidad - rd.cantidad_pedida) > 0.0001 THEN 1 END) AS items_pendientes,
                COUNT(CASE WHEN (rd.cantidad - rd.cantidad_pedida) > 0.0001 AND (? = 0 OR i.proveedor_id = ?) THEN 1 END) AS items_proveedor,
                GROUP_CONCAT(DISTINCT CASE WHEN (rd.cantidad - rd.cantidad_pedida) > 0.0001 THEN COALESCE(p.nombre, 'Sin proveedor') END SEPARATOR ', ') AS proveedores_involucrados
            FROM requisiciones r
            LEFT JOIN users u ON u.id = r.usuario_id
            INNER JOIN requisicion_detalles rd ON rd.requisicion_id = r.id
            INNER JOIN insumos i ON i.id = rd.insumo_id
            LEFT JOIN proveedores p ON p.id = i.proveedor_id
            WHERE r.estado IN (2, 5)
            GROUP BY r.id, r.codigo_requisicion, r.fecha_solicitud, r.justificacion, r.estado, r.estado_orden, u.username
            HAVING items_pendientes > 0
            ORDER BY r.id DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $proveedor_id, $proveedor_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $requisiciones = [];
        while ($row = $res->fetch_assoc()) {
            [$estTxt, $estStyle] = AbastecimientoService::etiquetaEstadoRequisicion((int) $row['estado'], (int) $row['estado_orden']);
            $row['estado_texto'] = $estTxt;
            $row['estado_style'] = $estStyle;
            $row['fecha_formateada'] = date('d/m/Y', strtotime($row['fecha_solicitud']));
            $requisiciones[] = $row;
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'requisiciones' => $requisiciones
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'cargar_insumos_requisiciones') {
        header('Content-Type: application/json; charset=utf-8');
        $data = is_array($requestJson) ? $requestJson : $_POST;
        $reqIds = $data['requisiciones_ids'] ?? [];
        if (is_string($reqIds)) {
            $reqIds = array_filter(array_map('intval', explode(',', $reqIds)));
        } elseif (is_array($reqIds)) {
            $reqIds = array_values(array_filter(array_map('intval', $reqIds)));
        } else {
            $reqIds = [];
        }

        $proveedor_id = (int) ($data['proveedor_id'] ?? 0);
        $solo_proveedor = !empty($data['solo_proveedor']);

        if (empty($reqIds)) {
            echo json_encode(['success' => true, 'insumos' => []]);
            $conn->close();
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($reqIds), '?'));
        $types = str_repeat('i', count($reqIds));
        $params = $reqIds;

        $whereProv = "";
        if ($solo_proveedor && $proveedor_id > 0) {
            $whereProv = " AND (i.proveedor_id = ? OR i.proveedor_id IS NULL)";
            $types .= 'i';
            $params[] = $proveedor_id;
        }

        $sql = "
            SELECT 
                rd.id AS requisicion_detalle_id,
                rd.requisicion_id,
                r.codigo_requisicion,
                rd.insumo_id,
                rd.cantidad AS cantidad_solicitada,
                rd.cantidad_pedida,
                GREATEST(0, rd.cantidad - rd.cantidad_pedida) AS cantidad_pendiente,
                rd.observaciones,
                i.nombre AS insumo_nombre,
                i.costo_unitario,
                i.proveedor_id,
                COALESCE(p.nombre, 'Sin proveedor') AS proveedor_nombre,
                um.codigo AS unidad_medida,
                COALESCE(um.permite_movimiento_decimal, 1) AS permite_decimal
            FROM requisicion_detalles rd
            INNER JOIN requisiciones r ON r.id = rd.requisicion_id
            INNER JOIN insumos i ON i.id = rd.insumo_id
            INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
            LEFT JOIN proveedores p ON p.id = i.proveedor_id
            WHERE rd.requisicion_id IN ({$placeholders})
              AND r.estado IN (2, 5)
              AND (rd.cantidad - rd.cantidad_pedida) > 0.0001
              {$whereProv}
            ORDER BY r.id ASC, rd.id ASC
        ";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $items = [];
        while ($row = $res->fetch_assoc()) {
            $items[] = [
                'requisicion_id' => (int) $row['requisicion_id'],
                'requisicion_detalle_id' => (int) $row['requisicion_detalle_id'],
                'codigo_requisicion' => $row['codigo_requisicion'],
                'insumo_id' => (int) $row['insumo_id'],
                'insumo_nombre' => $row['insumo_nombre'],
                'unidad_medida' => $row['unidad_medida'],
                'permite_decimal' => (int) $row['permite_decimal'],
                'cantidad' => (float) $row['cantidad_pendiente'],
                'cantidad_pendiente_max' => (float) $row['cantidad_pendiente'],
                'costo_unitario' => (float) $row['costo_unitario'],
                'proveedor_id' => $row['proveedor_id'] !== null ? (int) $row['proveedor_id'] : null,
                'proveedor_nombre' => $row['proveedor_nombre'],
            ];
        }
        $stmt->close();

        echo json_encode([
            'success' => true,
            'insumos' => $items
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_insumos_proveedor') {
        header('Content-Type: application/json; charset=utf-8');
        $proveedor_id = (int) ($_POST['proveedor_id'] ?? $_GET['proveedor_id'] ?? 0);
        $todos = !empty($_POST['todos_insumos']);

        if ($proveedor_id > 0 && !$todos) {
            $sql = "
                SELECT i.id, i.nombre, i.costo_unitario, um.codigo AS unidad_medida,
                       COALESCE(um.permite_movimiento_decimal, 1) AS permite_decimal,
                       COALESCE(p.nombre, 'Sin proveedor') AS proveedor_nombre
                FROM insumos i
                INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
                LEFT JOIN proveedores p ON p.id = i.proveedor_id
                WHERE i.proveedor_id = ? AND COALESCE(i.activo, 1) = 1
                ORDER BY i.nombre ASC
            ";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i', $proveedor_id);
            $stmt->execute();
            $res = $stmt->get_result();
        } else {
            $sql = "
                SELECT i.id, i.nombre, i.costo_unitario, um.codigo AS unidad_medida,
                       COALESCE(um.permite_movimiento_decimal, 1) AS permite_decimal,
                       COALESCE(p.nombre, 'Sin proveedor') AS proveedor_nombre
                FROM insumos i
                INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
                LEFT JOIN proveedores p ON p.id = i.proveedor_id
                WHERE COALESCE(i.activo, 1) = 1
                ORDER BY i.nombre ASC
            ";
            $res = $conn->query($sql);
        }

        $insumos = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $insumos[] = $row;
            }
        }
        if (isset($stmt)) {
            $stmt->close();
        }

        echo json_encode([
            'success' => true,
            'insumos' => $insumos
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_detalle') {
        header('Content-Type: application/json; charset=utf-8');
        $oc_id = (int) ($_POST['oc_id'] ?? $_GET['oc_id'] ?? 0);
        if ($oc_id <= 0) {
            throw new Exception("ID de Orden de Compra requerido");
        }

        $sqlCab = "
            SELECT 
                oc.*,
                p.nombre AS proveedor_nombre,
                p.cedrif AS proveedor_rif,
                COALESCE(u.username, 'Sistema') AS usuario_nombre,
                tc.tasa AS tasa_oc
            FROM ordenes_compra oc
            INNER JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN users u ON u.id = oc.usuario_id
            LEFT JOIN tasas_cambiarias tc ON tc.id = oc.tasa_cambiaria_id
            WHERE oc.id = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sqlCab);
        $stmt->bind_param('i', $oc_id);
        $stmt->execute();
        $oc = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$oc) {
            throw new Exception("Orden de Compra no encontrada");
        }

        [$estTxt, $estStyle] = AbastecimientoService::etiquetaEstadoOrdenCompra((string) $oc['estado']);
        $oc['estado_badge'] = '<span style="' . htmlspecialchars($estStyle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($estTxt) . '</span>';
        $oc['fecha_formateada'] = date('d/m/Y', strtotime($oc['fecha']));

        $sqlDet = "
            SELECT 
                ocd.id,
                ocd.insumo_id,
                ocd.requisicion_id,
                ocd.requisicion_detalle_id,
                ocd.cantidad,
                ocd.costo_unitario,
                ocd.subtotal,
                i.nombre AS insumo_nombre,
                um.codigo AS unidad_medida,
                r.codigo_requisicion
            FROM orden_compra_detalles ocd
            INNER JOIN insumos i ON i.id = ocd.insumo_id
            INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
            LEFT JOIN requisiciones r ON r.id = ocd.requisicion_id
            WHERE ocd.orden_compra_id = ?
            ORDER BY ocd.id ASC
        ";
        $stmtD = $conn->prepare($sqlDet);
        $stmtD->bind_param('i', $oc_id);
        $stmtD->execute();
        $resD = $stmtD->get_result();
        $detalles = [];
        while ($row = $resD->fetch_assoc()) {
            $detalles[] = $row;
        }
        $stmtD->close();

        // Compra vinculada si existe
        $stmtCmp = $conn->prepare("SELECT id, fecha, numero_factura, total, estado FROM compras WHERE id_orden_compra = ? ORDER BY id DESC LIMIT 1");
        $stmtCmp->bind_param('i', $oc_id);
        $stmtCmp->execute();
        $compraVinc = $stmtCmp->get_result()->fetch_assoc();
        $stmtCmp->close();

        echo json_encode([
            'success' => true,
            'orden_compra' => $oc,
            'detalles' => $detalles,
            'compra_vinculada' => $compraVinc
        ]);
        $conn->close();
        exit;
    }

    restringirEscritura();
    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'crear') {
        $data = is_array($requestJson) ? $requestJson : $_POST;
        $proveedor_id = (int) ($data['proveedor_id'] ?? 0);
        $fecha = trim((string) ($data['fecha'] ?? date('Y-m-d')));
        $modalidad = in_array($data['modalidad'] ?? '', ['requisicion', 'directa'], true) ? $data['modalidad'] : 'directa';
        $observaciones = trim((string) ($data['observaciones'] ?? ''));
        $requisiciones_seleccionadas = $data['requisiciones_ids'] ?? [];
        if (!is_array($requisiciones_seleccionadas)) {
            $requisiciones_seleccionadas = [];
        }
        $insumos = $data['insumos'] ?? [];

        if ($proveedor_id <= 0) {
            throw new Exception("Debe seleccionar un proveedor para la Orden de Compra.");
        }
        if ($fecha === '') {
            throw new Exception("La fecha de la Orden de Compra es obligatoria.");
        }
        if (empty($insumos) || !is_array($insumos)) {
            throw new Exception("Debe agregar al menos un insumo a la Orden de Compra.");
        }

        $stmtPerm = $conn->prepare(
            "SELECT COALESCE(um.permite_movimiento_decimal, 1) AS p
             FROM insumos i
             LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
             WHERE i.id = ? LIMIT 1"
        );

        $total = 0.0;
        $reqIdsInvolucradas = [];
        foreach ($requisiciones_seleccionadas as $rId) {
            $rIdInt = (int) $rId;
            if ($rIdInt > 0) {
                $reqIdsInvolucradas[$rIdInt] = $rIdInt;
            }
        }

        foreach ($insumos as &$item) {
            $insId = (int) ($item['insumo_id'] ?? 0);
            $cant = (float) ($item['cantidad'] ?? 0);
            $costo = (float) ($item['costo_unitario'] ?? 0);
            $reqId = !empty($item['requisicion_id']) ? (int) $item['requisicion_id'] : null;
            $reqDetId = !empty($item['requisicion_detalle_id']) ? (int) $item['requisicion_detalle_id'] : null;

            if ($insId <= 0 || $cant <= 0 || $costo <= 0) {
                throw new Exception("Cada línea de la Orden de Compra debe tener insumo, cantidad y costo unitario mayores a cero.");
            }

            $stmtPerm->bind_param('i', $insId);
            $stmtPerm->execute();
            $rp = $stmtPerm->get_result()->fetch_assoc();
            $permite = !$rp || (int) ($rp['p'] ?? 1) === 1;
            try {
                $cant = inv_normalizar_cantidad_movimiento_manual($cant, $permite);
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }

            $item['cantidad'] = $cant;
            $item['costo_unitario'] = round($costo, 2);
            $item['requisicion_id'] = $reqId;
            $item['requisicion_detalle_id'] = $reqDetId;
            $total += $cant * $item['costo_unitario'];

            if ($reqId !== null && $reqId > 0) {
                $reqIdsInvolucradas[$reqId] = $reqId;
            }
        }
        unset($item);
        $stmtPerm->close();

        if ($modalidad === 'requisicion' && empty($reqIdsInvolucradas)) {
            throw new Exception("En la modalidad 'Desde Requisición Aprobada' debe seleccionar al menos una requisición.");
        }

        $tasa_cambiaria_id = null;
        $rt = $conn->query("SELECT id FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
        if ($rt && ($row_tasa = $rt->fetch_assoc())) {
            $tasa_cambiaria_id = (int) $row_tasa['id'];
        }

        $usuario_id = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
        $reqIdsCsv = !empty($reqIdsInvolucradas) ? implode(',', array_values($reqIdsInvolucradas)) : null;
        $obsNull = $observaciones !== '' ? $observaciones : null;

        $conn->begin_transaction();
        try {
            $codigoOc = AbastecimientoService::generarCodigoOrdenCompra($conn);
            $estadoOc = 'EMITIDA';

            $stmtOc = $conn->prepare("
                INSERT INTO ordenes_compra
                    (codigo_oc, proveedor_id, usuario_id, fecha, modalidad, requisiciones_ids, total, tasa_cambiaria_id, estado, observaciones)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtOc->bind_param(
                'siisssdiss',
                $codigoOc,
                $proveedor_id,
                $usuario_id,
                $fecha,
                $modalidad,
                $reqIdsCsv,
                $total,
                $tasa_cambiaria_id,
                $estadoOc,
                $obsNull
            );
            $stmtOc->execute();
            $oc_id = (int) $conn->insert_id;
            $stmtOc->close();

            if ($oc_id <= 0) {
                throw new Exception("No se pudo crear la Orden de Compra.");
            }

            $stmtDet = $conn->prepare("
                INSERT INTO orden_compra_detalles
                    (orden_compra_id, insumo_id, requisicion_id, requisicion_detalle_id, cantidad, costo_unitario)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            foreach ($insumos as $item) {
                $insId = (int) $item['insumo_id'];
                $reqId = $item['requisicion_id'];
                $reqDetId = $item['requisicion_detalle_id'];
                $cant = (float) $item['cantidad'];
                $costo = (float) $item['costo_unitario'];

                $stmtDet->bind_param('iiiidd', $oc_id, $insId, $reqId, $reqDetId, $cant, $costo);
                $stmtDet->execute();
            }
            $stmtDet->close();

            // Recalcular el estado de todas las requisiciones involucradas (PROCESADA vs PARCIAL)
            foreach ($reqIdsInvolucradas as $rId) {
                AbastecimientoService::recalcularEstadoRequisicion($conn, (int) $rId);
            }

            $conn->commit();

            Auditoria::registrar(
                $conn,
                "Orden de Compra {$codigoOc} (ID #{$oc_id}) emitida. Proveedor #{$proveedor_id}. Modalidad: {$modalidad}. Total: \${$total}.",
                'Órdenes de Compra'
            );

            echo json_encode([
                'success' => true,
                'message' => "Orden de Compra {$codigoOc} creada exitosamente en estado EMITIDA.",
                'orden_compra_id' => $oc_id,
                'codigo_oc' => $codigoOc
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        $conn->close();
        exit;
    }

    if ($action === 'cancelar') {
        $data = is_array($requestJson) ? $requestJson : $_POST;
        $oc_id = (int) ($data['oc_id'] ?? $data['id'] ?? 0);

        if ($oc_id <= 0) {
            throw new Exception("ID de Orden de Compra inválido.");
        }

        $conn->begin_transaction();
        try {
            $stmtChk = $conn->prepare("SELECT id, codigo_oc, estado, requisiciones_ids FROM ordenes_compra WHERE id = ? FOR UPDATE");
            $stmtChk->bind_param('i', $oc_id);
            $stmtChk->execute();
            $oc = $stmtChk->get_result()->fetch_assoc();
            $stmtChk->close();

            if (!$oc) {
                throw new Exception("La Orden de Compra no existe.");
            }
            if ($oc['estado'] !== 'EMITIDA') {
                throw new Exception("Solo se pueden cancelar Órdenes de Compra en estado EMITIDA.");
            }

            $stmtUpd = $conn->prepare("UPDATE ordenes_compra SET estado = 'CANCELADA' WHERE id = ?");
            $stmtUpd->bind_param('i', $oc_id);
            $stmtUpd->execute();
            $stmtUpd->close();

            // Recolectar requisiciones vinculadas para recalcular su saldo disponible
            $reqIds = [];
            if (!empty($oc['requisiciones_ids'])) {
                foreach (explode(',', $oc['requisiciones_ids']) as $part) {
                    $rid = (int) trim($part);
                    if ($rid > 0) {
                        $reqIds[$rid] = $rid;
                    }
                }
            }
            $stmtReqD = $conn->prepare("SELECT DISTINCT requisicion_id FROM orden_compra_detalles WHERE orden_compra_id = ? AND requisicion_id IS NOT NULL");
            $stmtReqD->bind_param('i', $oc_id);
            $stmtReqD->execute();
            $resRD = $stmtReqD->get_result();
            while ($rr = $resRD->fetch_assoc()) {
                $rid = (int) $rr['requisicion_id'];
                if ($rid > 0) {
                    $reqIds[$rid] = $rid;
                }
            }
            $stmtReqD->close();

            foreach ($reqIds as $rid) {
                AbastecimientoService::recalcularEstadoRequisicion($conn, $rid);
            }

            $conn->commit();

            $codigo = (string) $oc['codigo_oc'];
            Auditoria::registrar(
                $conn,
                "Orden de Compra {$codigo} (ID #{$oc_id}) CANCELADA.",
                'Órdenes de Compra'
            );

            echo json_encode([
                'success' => true,
                'message' => "La Orden de Compra {$codigo} ha sido CANCELADA."
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        $conn->close();
        exit;
    }

    throw new Exception("Acción no válida");
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
