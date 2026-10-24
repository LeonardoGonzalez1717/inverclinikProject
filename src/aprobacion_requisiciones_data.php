<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
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
    if ($action === 'obtener_resumen') {
        header('Content-Type: application/json; charset=utf-8');
        $sql = "
            SELECT 
                SUM(CASE WHEN estado = 1 THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN estado = 2 THEN 1 ELSE 0 END) AS aprobadas,
                SUM(CASE WHEN estado = 3 THEN 1 ELSE 0 END) AS rechazadas,
                SUM(CASE WHEN estado IN (4, 5) THEN 1 ELSE 0 END) AS en_orden_compra
            FROM requisiciones
        ";
        $res = $conn->query($sql);
        $totales = $res ? $res->fetch_assoc() : [];
        echo json_encode([
            'success' => true,
            'pendientes' => (int) ($totales['pendientes'] ?? 0),
            'aprobadas' => (int) ($totales['aprobadas'] ?? 0),
            'rechazadas' => (int) ($totales['rechazadas'] ?? 0),
            'en_orden_compra' => (int) ($totales['en_orden_compra'] ?? 0),
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'listar_html') {
        $buscar_texto = isset($_POST['buscar_texto']) ? trim($_POST['buscar_texto']) : '';
        $estado       = isset($_POST['estado']) ? trim($_POST['estado']) : '';
        $fecha_desde  = isset($_POST['fecha_desde']) ? trim($_POST['fecha_desde']) : '';
        $fecha_hasta  = isset($_POST['fecha_hasta']) ? trim($_POST['fecha_hasta']) : '';

        $where = [];

        if ($buscar_texto !== '') {
            $s = $conn->real_escape_string($buscar_texto);
            $where[] = "(r.codigo_requisicion LIKE '%$s%' OR r.justificacion LIKE '%$s%' OR u.username LIKE '%$s%')";
        }

        if ($estado !== '' && $estado !== 'todos' && is_numeric($estado)) {
            $estInt = (int) $estado;
            $where[] = "r.estado = {$estInt}";
        }

        if ($fecha_desde !== '') {
            $fDesde = $conn->real_escape_string($fecha_desde);
            $where[] = "DATE(r.fecha_solicitud) >= '$fDesde'";
        }

        if ($fecha_hasta !== '') {
            $fHasta = $conn->real_escape_string($fecha_hasta);
            $where[] = "DATE(r.fecha_solicitud) <= '$fHasta'";
        }

        $fil = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sqlBase = "
            SELECT 
                r.id,
                r.codigo_requisicion,
                r.fecha_solicitud,
                r.justificacion,
                r.estado,
                r.estado_orden,
                r.motivo_rechazo,
                r.fecha_aprobacion,
                COALESCE(u.username, 'Sistema') AS solicitante_nombre,
                u2.username AS aprobador_nombre,
                COUNT(rd.id) AS total_items,
                COALESCE(SUM(rd.cantidad), 0) AS cantidad_total,
                COALESCE(SUM(rd.cantidad * i.costo_unitario), 0) AS costo_estimado
            FROM requisiciones r
            LEFT JOIN users u ON u.id = r.usuario_id
            LEFT JOIN users u2 ON u2.id = r.usuario_aprobacion_id
            LEFT JOIN requisicion_detalles rd ON rd.requisicion_id = r.id
            LEFT JOIN insumos i ON i.id = rd.insumo_id
            {$fil}
            GROUP BY r.id, r.codigo_requisicion, r.fecha_solicitud, r.justificacion,
                     r.estado, r.estado_orden, r.motivo_rechazo, r.fecha_aprobacion,
                     u.username, u2.username, r.creado_en
        ";

        $total = Pagination::countFromSubquery($conn, $sqlBase);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = $sqlBase . " ORDER BY (CASE WHEN r.estado = 1 THEN 0 ELSE 1 END) ASC, r.id DESC " . $pg->limitClause();
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
            foreach ($rows as $r) {
                $i++;
                $idReq = (int) $r['id'];
                $estadoInt = (int) $r['estado'];
                $estadoOrdenInt = (int) ($r['estado_orden'] ?? 0);
                [$estTxt, $estStyle] = AbastecimientoService::etiquetaEstadoRequisicion($estadoInt, $estadoOrdenInt);

                $justCorto = trim((string) ($r['justificacion'] ?? ''));
                if (mb_strlen($justCorto) > 60) {
                    $justCorto = mb_substr($justCorto, 0, 60) . '...';
                }

                $codEsc = htmlspecialchars($r['codigo_requisicion'], ENT_QUOTES, 'UTF-8');

                echo '<tr>';
                echo '<td>' . htmlspecialchars((string) $i) . '</td>';
                echo '<td nowrap><strong>' . $codEsc . '</strong></td>';
                echo '<td nowrap>' . date('d/m/Y H:i', strtotime($r['fecha_solicitud'])) . '</td>';
                echo '<td>' . htmlspecialchars($r['solicitante_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($justCorto ?: '—') . '</td>';
                echo '<td class="text-center">' . (int) $r['total_items'] . ' ítem(s)</td>';
                echo '<td>$' . number_format((float) $r['costo_estimado'], 2, '.', ',') . '</td>';
                echo '<td><span style="' . htmlspecialchars($estStyle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($estTxt) . '</span></td>';
                echo '<td nowrap>';
                echo '<button type="button" class="btn btn-sm btn-primary" onclick="abrirModalRevision(' . $idReq . ')" title="Revisar Detalle e Insumos"><i class="fas fa-search-plus"></i> Revisar</button> ';

                if ($estadoInt === AbastecimientoService::REQ_PENDIENTE && !esLector()) {
                    echo '<button type="button" class="btn btn-sm btn-success" onclick="aprobarRequisicion(' . $idReq . ', \'' . $codEsc . '\')" title="Aprobar Requisición"><i class="fas fa-check"></i> Aprobar</button> ';
                    echo '<button type="button" class="btn btn-sm btn-danger" onclick="rechazarRequisicion(' . $idReq . ', \'' . $codEsc . '\')" title="Rechazar Requisición"><i class="fas fa-times"></i> Rechazar</button>';
                } elseif (in_array($estadoInt, [AbastecimientoService::REQ_APROBADA, AbastecimientoService::REQ_PARCIAL], true) && !esLector()) {
                    echo '<a href="ordenes_compra.php?req_id=' . $idReq . '" class="btn btn-sm btn-info" title="Crear Orden de Compra desde esta requisición"><i class="fas fa-file-invoice-dollar"></i> Crear O.C.</a>';
                }

                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="9" class="text-center">No hay requisiciones que coincidan con el filtro seleccionado</td></tr>';
        }

        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    restringirEscritura();
    header('Content-Type: application/json; charset=utf-8');

    // Validar perfil autorizado (Admin = 1, Gerencia Prod = 4, Gerencia Adm = 6)
    $role_id = isset($_SESSION['role_id']) ? (int) $_SESSION['role_id'] : 0;
    if (!in_array($role_id, [1, 4, 6], true)) {
        throw new Exception("No tiene permisos para aprobar o rechazar requisiciones de compra.");
    }

    if ($action === 'aprobar') {
        $data = is_array($requestJson) ? $requestJson : $_POST;
        $id_requisicion = (int) ($data['requisicion_id'] ?? $data['id'] ?? 0);

        if ($id_requisicion <= 0) {
            throw new Exception("ID de requisición inválido.");
        }

        $conn->begin_transaction();
        try {
            $stmtChk = $conn->prepare("SELECT id, codigo_requisicion, estado FROM requisiciones WHERE id = ? FOR UPDATE");
            $stmtChk->bind_param('i', $id_requisicion);
            $stmtChk->execute();
            $req = $stmtChk->get_result()->fetch_assoc();
            $stmtChk->close();

            if (!$req) {
                throw new Exception("La requisición no existe.");
            }

            if ((int) $req['estado'] !== AbastecimientoService::REQ_PENDIENTE) {
                throw new Exception("Solo se pueden aprobar requisiciones que se encuentren en estado PENDIENTE.");
            }

            $aprobador_id = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
            $nuevoEstado = AbastecimientoService::REQ_APROBADA; // 2 = APROBADA

            $stmtUpd = $conn->prepare("
                UPDATE requisiciones
                SET estado = ?, usuario_aprobacion_id = ?, fecha_aprobacion = NOW(), motivo_rechazo = NULL
                WHERE id = ?
            ");
            $stmtUpd->bind_param('iii', $nuevoEstado, $aprobador_id, $id_requisicion);
            $stmtUpd->execute();
            $stmtUpd->close();

            $conn->commit();

            $codigo = (string) $req['codigo_requisicion'];
            Auditoria::registrar(
                $conn,
                "Requisición {$codigo} (ID #{$id_requisicion}) APROBADA.",
                'Aprobación Requisiciones'
            );

            echo json_encode([
                'success' => true,
                'message' => "La requisición {$codigo} ha sido APROBADA exitosamente."
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        $conn->close();
        exit;
    }

    if ($action === 'rechazar') {
        $data = is_array($requestJson) ? $requestJson : $_POST;
        $id_requisicion = (int) ($data['requisicion_id'] ?? $data['id'] ?? 0);
        $motivo_rechazo = trim((string) ($data['motivo_rechazo'] ?? ''));

        if ($id_requisicion <= 0) {
            throw new Exception("ID de requisición inválido.");
        }

        $conn->begin_transaction();
        try {
            $stmtChk = $conn->prepare("SELECT id, codigo_requisicion, estado FROM requisiciones WHERE id = ? FOR UPDATE");
            $stmtChk->bind_param('i', $id_requisicion);
            $stmtChk->execute();
            $req = $stmtChk->get_result()->fetch_assoc();
            $stmtChk->close();

            if (!$req) {
                throw new Exception("La requisición no existe.");
            }

            if ((int) $req['estado'] !== AbastecimientoService::REQ_PENDIENTE) {
                throw new Exception("Solo se pueden rechazar requisiciones que se encuentren en estado PENDIENTE.");
            }

            $aprobador_id = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
            $nuevoEstado = AbastecimientoService::REQ_RECHAZADA; // 3 = RECHAZADA
            $motivoNull = $motivo_rechazo !== '' ? $motivo_rechazo : null;

            $stmtUpd = $conn->prepare("
                UPDATE requisiciones
                SET estado = ?, usuario_aprobacion_id = ?, fecha_aprobacion = NOW(), motivo_rechazo = ?
                WHERE id = ?
            ");
            $stmtUpd->bind_param('iisi', $nuevoEstado, $aprobador_id, $motivoNull, $id_requisicion);
            $stmtUpd->execute();
            $stmtUpd->close();

            $conn->commit();

            $codigo = (string) $req['codigo_requisicion'];
            $txtMotivo = $motivo_rechazo !== '' ? " Motivo: {$motivo_rechazo}" : "";
            Auditoria::registrar(
                $conn,
                "Requisición {$codigo} (ID #{$id_requisicion}) RECHAZADA.{$txtMotivo}",
                'Aprobación Requisiciones'
            );

            echo json_encode([
                'success' => true,
                'message' => "La requisición {$codigo} ha sido RECHAZADA."
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
