<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/AbastecimientoService.php';

AbastecimientoService::asegurarTablas($conn);

$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$requestJson = null;

if (empty($action)) {
    $raw = file_get_contents('php://input');
    $requestJson = json_decode($raw, true);
    $action = is_array($requestJson) ? ($requestJson['action'] ?? '') : '';
}

try {
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

        if ($estado !== '' && is_numeric($estado)) {
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
                r.observaciones,
                r.estado,
                r.estado_orden,
                r.motivo_rechazo,
                COALESCE(u.username, 'Sistema') AS solicitante_nombre,
                COUNT(rd.id) AS total_items,
                COALESCE(SUM(rd.cantidad), 0) AS cantidad_total
            FROM requisiciones r
            LEFT JOIN users u ON u.id = r.usuario_id
            LEFT JOIN requisicion_detalles rd ON rd.requisicion_id = r.id
            {$fil}
            GROUP BY r.id, r.codigo_requisicion, r.fecha_solicitud, r.justificacion, r.observaciones,
                     r.estado, r.estado_orden, r.motivo_rechazo, u.username, r.creado_en
        ";

        $total = Pagination::countFromSubquery($conn, $sqlBase);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = $sqlBase . " ORDER BY r.id DESC " . $pg->limitClause();
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
                if (mb_strlen($justCorto) > 65) {
                    $justCorto = mb_substr($justCorto, 0, 65) . '...';
                }

                $codEsc = htmlspecialchars($r['codigo_requisicion'], ENT_QUOTES, 'UTF-8');
                $esPendiente = ($estadoInt === AbastecimientoService::REQ_PENDIENTE && $estadoOrdenInt === AbastecimientoService::OC_PROC_NINGUNO);

                echo '<tr>';
                echo '<td>' . htmlspecialchars((string) $i) . '</td>';
                echo '<td nowrap><strong>' . $codEsc . '</strong></td>';
                echo '<td nowrap>' . date('d/m/Y H:i', strtotime($r['fecha_solicitud'])) . '</td>';
                echo '<td>' . htmlspecialchars($r['solicitante_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($justCorto ?: '—') . '</td>';
                echo '<td class="text-center">' . (int) $r['total_items'] . ' (' . number_format((float) $r['cantidad_total'], 2, '.', ',') . ')</td>';
                echo '<td><span style="' . htmlspecialchars($estStyle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($estTxt) . '</span></td>';
                echo '<td nowrap>';
                echo '<button type="button" class="btn btn-sm btn-info" onclick="verDetalleRequisicion(' . $idReq . ')" title="Ver Detalle"><i class="fas fa-eye"></i> Ver</button> ';

                if ($esPendiente && !esLector()) {
                    echo '<button type="button" class="btn btn-sm btn-primary" onclick="editarRequisicion(' . $idReq . ')" title="Editar Requisición"><i class="fas fa-edit"></i></button> ';
                    echo '<button type="button" class="btn btn-sm btn-danger" onclick="eliminarRequisicion(' . $idReq . ', \'' . $codEsc . '\')" title="Eliminar Requisición"><i class="fas fa-trash"></i></button>';
                } else {
                    echo '<span class="badge badge-secondary" style="padding: 5px 8px; background:#e2e8f0; color:#475569;" title="Solo se permite editar o eliminar requisiciones en estado PENDIENTE"><i class="fas fa-lock"></i> Bloqueada</span>';
                }

                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8" class="text-center">No se encontraron requisiciones registradas</td></tr>';
        }

        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_detalle') {
        header('Content-Type: application/json; charset=utf-8');
        $requisicion_id = (int) ($_POST['requisicion_id'] ?? $_GET['requisicion_id'] ?? 0);
        if ($requisicion_id <= 0) {
            throw new Exception("ID de requisición requerido");
        }

        $sqlCab = "
            SELECT 
                r.*,
                COALESCE(u.username, 'Sistema') AS solicitante_nombre,
                u2.username AS aprobador_nombre
            FROM requisiciones r
            LEFT JOIN users u ON u.id = r.usuario_id
            LEFT JOIN users u2 ON u2.id = r.usuario_aprobacion_id
            WHERE r.id = ?
            LIMIT 1
        ";
        $stmt = $conn->prepare($sqlCab);
        $stmt->bind_param('i', $requisicion_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $req = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$req) {
            throw new Exception("Requisición no encontrada");
        }

        [$estTxt, $estStyle, $estBadge] = AbastecimientoService::etiquetaEstadoRequisicion(
            (int) $req['estado'],
            (int) ($req['estado_orden'] ?? 0)
        );
        $req['estado_texto'] = $estTxt;
        $req['estado_style'] = $estStyle;
        $req['estado_badge'] = '<span style="' . htmlspecialchars($estStyle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($estTxt) . '</span>';
        $req['fecha_formateada'] = date('d/m/Y H:i', strtotime($req['fecha_solicitud']));
        $req['fecha_input'] = date('Y-m-d', strtotime($req['fecha_solicitud']));
        $req['fecha_aprobacion_fmt'] = !empty($req['fecha_aprobacion'])
            ? date('d/m/Y H:i', strtotime($req['fecha_aprobacion']))
            : null;

        $joinInv = $tieneInventarioNuevo
            ? "LEFT JOIN inventario inv ON inv.tipo_item = 'insumo' AND inv.tipo_item_id = i.id"
            : "LEFT JOIN inventario inv ON inv.insumo_id = i.id";

        $sqlDet = "
            SELECT 
                rd.id,
                rd.insumo_id,
                rd.cantidad,
                rd.cantidad_pedida,
                GREATEST(0, rd.cantidad - rd.cantidad_pedida) AS cantidad_pendiente,
                rd.observaciones,
                i.nombre AS insumo_nombre,
                i.costo_unitario,
                i.stock_minimo,
                i.stock_maximo,
                COALESCE(inv.stock_actual, 0) AS stock_actual,
                um.codigo AS unidad_medida,
                COALESCE(um.permite_movimiento_decimal, 1) AS permite_decimal,
                COALESCE(p.nombre, 'Sin proveedor asignado') AS proveedor_nombre
            FROM requisicion_detalles rd
            INNER JOIN insumos i ON i.id = rd.insumo_id
            INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
            LEFT JOIN proveedores p ON p.id = i.proveedor_id
            {$joinInv}
            WHERE rd.requisicion_id = ?
            ORDER BY rd.id ASC
        ";
        $stmtD = $conn->prepare($sqlDet);
        $stmtD->bind_param('i', $requisicion_id);
        $stmtD->execute();
        $resD = $stmtD->get_result();
        $detalles = [];
        while ($row = $resD->fetch_assoc()) {
            $detalles[] = $row;
        }
        $stmtD->close();

        // Órdenes de compra asociadas a esta requisición
        $sqlOcs = "
            SELECT DISTINCT oc.id, oc.codigo_oc, oc.fecha, oc.estado, oc.total, p.nombre AS proveedor_nombre
            FROM ordenes_compra oc
            INNER JOIN proveedores p ON p.id = oc.proveedor_id
            LEFT JOIN orden_compra_detalles ocd ON ocd.orden_compra_id = oc.id
            WHERE ocd.requisicion_id = ?
               OR FIND_IN_SET(?, REPLACE(IFNULL(oc.requisiciones_ids, ''), ' ', '')) > 0
            ORDER BY oc.id DESC
        ";
        $stmtOc = $conn->prepare($sqlOcs);
        $reqStr = (string) $requisicion_id;
        $stmtOc->bind_param('is', $requisicion_id, $reqStr);
        $stmtOc->execute();
        $resOc = $stmtOc->get_result();
        $ordenes = [];
        while ($oc = $resOc->fetch_assoc()) {
            $ordenes[] = $oc;
        }
        $stmtOc->close();

        echo json_encode([
            'success' => true,
            'requisicion' => $req,
            'detalles' => $detalles,
            'ordenes_asociadas' => $ordenes
        ]);
        $conn->close();
        exit;
    }

    restringirEscritura();
    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'guardar') {
        $data = is_array($requestJson) ? $requestJson : $_POST;
        $id_requisicion = (int) ($data['id'] ?? 0);
        $fecha_solicitud = trim((string) ($data['fecha_solicitud'] ?? date('Y-m-d')));
        $justificacion = trim((string) ($data['justificacion'] ?? ''));
        $observaciones = trim((string) ($data['observaciones'] ?? ''));
        $insumos = $data['insumos'] ?? [];
        if (is_string($insumos)) {
            $insumos = json_decode($insumos, true) ?: [];
        }

        if ($justificacion === '') {
            throw new Exception("La justificación o motivo de la requisición es obligatoria.");
        }

        if (empty($insumos) || !is_array($insumos)) {
            throw new Exception("Debe agregar al menos un insumo a la requisición.");
        }

        $stmtPerm = $conn->prepare(
            "SELECT COALESCE(um.permite_movimiento_decimal, 1) AS p
             FROM insumos i
             LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
             WHERE i.id = ? LIMIT 1"
        );

        $lineasNormalizadas = [];
        foreach ($insumos as $item) {
            $insId = (int) ($item['insumo_id'] ?? 0);
            $cant  = (float) ($item['cantidad'] ?? 0);
            $obsItem = trim((string) ($item['observaciones'] ?? ''));

            if ($insId <= 0 || $cant <= 0) {
                throw new Exception("Datos inválidos en la lista de insumos solicitados.");
            }

            $stmtPerm->bind_param('i', $insId);
            $stmtPerm->execute();
            $rp = $stmtPerm->get_result()->fetch_assoc();
            if (!$rp) {
                throw new Exception("El insumo seleccionado (ID {$insId}) no existe.");
            }
            $permite = (int) ($rp['p'] ?? 1) === 1;
            try {
                $cant = inv_normalizar_cantidad_movimiento_manual($cant, $permite);
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }

            if (isset($lineasNormalizadas[$insId])) {
                $lineasNormalizadas[$insId]['cantidad'] += $cant;
                if ($obsItem !== '' && $lineasNormalizadas[$insId]['observaciones'] === '') {
                    $lineasNormalizadas[$insId]['observaciones'] = $obsItem;
                }
            } else {
                $lineasNormalizadas[$insId] = [
                    'insumo_id' => $insId,
                    'cantidad' => $cant,
                    'observaciones' => $obsItem !== '' ? mb_substr($obsItem, 0, 255) : null
                ];
            }
        }
        $stmtPerm->close();

        $usuario_id = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
        $fechaHoraSolicitud = $fecha_solicitud . ' ' . date('H:i:s');

        $conn->begin_transaction();
        try {
            $esEdicion = $id_requisicion > 0;
            $codigoReq = '';

            if ($esEdicion) {
                // REGLA DE NEGOCIO ESTRICTA: Solo se permite EDITAR si su estado es 1 = PENDIENTE
                $stmtChk = $conn->prepare("SELECT id, codigo_requisicion, estado, estado_orden FROM requisiciones WHERE id = ? FOR UPDATE");
                $stmtChk->bind_param('i', $id_requisicion);
                $stmtChk->execute();
                $actual = $stmtChk->get_result()->fetch_assoc();
                $stmtChk->close();

                if (!$actual) {
                    throw new Exception("La requisición que intenta editar no existe.");
                }

                if ((int) $actual['estado'] !== AbastecimientoService::REQ_PENDIENTE || (int) $actual['estado_orden'] !== AbastecimientoService::OC_PROC_NINGUNO) {
                    throw new Exception("Regla de integridad: Solo se permite editar una requisición si su estado es PENDIENTE.");
                }

                $codigoReq = (string) $actual['codigo_requisicion'];

                $stmtUpd = $conn->prepare("
                    UPDATE requisiciones
                    SET fecha_solicitud = ?, justificacion = ?, observaciones = ?
                    WHERE id = ? AND estado = 1
                ");
                $obsNull = $observaciones !== '' ? $observaciones : null;
                $stmtUpd->bind_param('sssi', $fechaHoraSolicitud, $justificacion, $obsNull, $id_requisicion);
                $stmtUpd->execute();
                $stmtUpd->close();

                $stmtDel = $conn->prepare("DELETE FROM requisicion_detalles WHERE requisicion_id = ?");
                $stmtDel->bind_param('i', $id_requisicion);
                $stmtDel->execute();
                $stmtDel->close();
            } else {
                $codigoReq = AbastecimientoService::generarCodigoRequisicion($conn);
                $estadoInicial = AbastecimientoService::REQ_PENDIENTE; // 1 = PENDIENTE
                $obsNull = $observaciones !== '' ? $observaciones : null;

                $stmtIns = $conn->prepare("
                    INSERT INTO requisiciones (codigo_requisicion, usuario_id, fecha_solicitud, justificacion, observaciones, estado, estado_orden)
                    VALUES (?, ?, ?, ?, ?, ?, 0)
                ");
                $stmtIns->bind_param('sisssi', $codigoReq, $usuario_id, $fechaHoraSolicitud, $justificacion, $obsNull, $estadoInicial);
                $stmtIns->execute();
                $id_requisicion = (int) $conn->insert_id;
                $stmtIns->close();
            }

            $stmtDet = $conn->prepare("
                INSERT INTO requisicion_detalles (requisicion_id, insumo_id, cantidad, cantidad_pedida, observaciones)
                VALUES (?, ?, ?, 0.00, ?)
            ");
            foreach ($lineasNormalizadas as $linea) {
                $stmtDet->bind_param(
                    'iids',
                    $id_requisicion,
                    $linea['insumo_id'],
                    $linea['cantidad'],
                    $linea['observaciones']
                );
                $stmtDet->execute();
            }
            $stmtDet->close();

            $conn->commit();

            Auditoria::registrar(
                $conn,
                ($esEdicion ? "Requisición actualizada: " : "Requisición creada: ") . "{$codigoReq} (ID #{$id_requisicion}) con " . count($lineasNormalizadas) . " insumo(s).",
                'Requisiciones'
            );

            echo json_encode([
                'success' => true,
                'message' => $esEdicion
                    ? "Requisición {$codigoReq} actualizada exitosamente."
                    : "Requisición {$codigoReq} creada exitosamente en estado PENDIENTE.",
                'requisicion_id' => $id_requisicion,
                'codigo_requisicion' => $codigoReq
            ]);
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }
        $conn->close();
        exit;
    }

    if ($action === 'eliminar') {
        $data = is_array($requestJson) ? $requestJson : $_POST;
        $id_requisicion = (int) ($data['id'] ?? $data['requisicion_id'] ?? 0);

        if ($id_requisicion <= 0) {
            throw new Exception("ID de requisición inválido.");
        }

        $conn->begin_transaction();
        try {
            // REGLA DE NEGOCIO ESTRICTA: Solo se permite ELIMINAR si su estado es 1 = PENDIENTE
            $stmtChk = $conn->prepare("SELECT id, codigo_requisicion, estado, estado_orden FROM requisiciones WHERE id = ? FOR UPDATE");
            $stmtChk->bind_param('i', $id_requisicion);
            $stmtChk->execute();
            $req = $stmtChk->get_result()->fetch_assoc();
            $stmtChk->close();

            if (!$req) {
                throw new Exception("La requisición no existe.");
            }

            if ((int) $req['estado'] !== AbastecimientoService::REQ_PENDIENTE || (int) $req['estado_orden'] !== AbastecimientoService::OC_PROC_NINGUNO) {
                throw new Exception("Regla de integridad: Solo se permite eliminar una requisición si su estado es PENDIENTE.");
            }

            $codigo = (string) $req['codigo_requisicion'];

            $stmtDel = $conn->prepare("DELETE FROM requisiciones WHERE id = ? AND estado = 1");
            $stmtDel->bind_param('i', $id_requisicion);
            $stmtDel->execute();
            $stmtDel->close();

            $conn->commit();

            Auditoria::registrar(
                $conn,
                "Requisición eliminada: {$codigo} (ID #{$id_requisicion}).",
                'Requisiciones'
            );

            echo json_encode([
                'success' => true,
                'message' => "Requisición {$codigo} eliminada correctamente."
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
