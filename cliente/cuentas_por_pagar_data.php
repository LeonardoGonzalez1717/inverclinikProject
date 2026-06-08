<?php
session_start();
require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../lib/CuentasPorCobrar.php';

CuentasPorCobrar::asegurarTablas($conn);

$action = $_REQUEST['action'] ?? '';
$id_cliente = isset($_SESSION['id_cliente']) ? (int) $_SESSION['id_cliente'] : 0;

$accionesCliente = [
    'listar_cuentas',
    'listar_pagos',
    'registrar_pago',
    'listar_formas_pago',
];

if ($id_cliente <= 0 && in_array($action, $accionesCliente, true)) {
    if (in_array($action, ['listar_pagos', 'registrar_pago', 'listar_formas_pago'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sesión no válida', 'success' => false]);
    } else {
        echo '<tr><td colspan="7" class="text-center">Inicia sesión para ver tus cuentas por pagar.</td></tr>';
    }
    exit;
}

switch ($action) {
    case 'listar_formas_pago':
        header('Content-Type: application/json; charset=utf-8');
        $formas = [];
        $res = $conn->query('SELECT id, nombre FROM formas_pago WHERE activo = 1 ORDER BY nombre ASC');
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $formas[] = ['id' => (int) $row['id'], 'nombre' => (string) $row['nombre']];
            }
        }
        echo json_encode(['formas' => $formas]);
        break;

    case 'listar_cuentas':
        $filtro = trim((string) ($_GET['filtro'] ?? 'pendiente'));

        $where = 'WHERE cxc.cliente_id = ?';
        $tipos = 'i';
        $params = [$id_cliente];

        if ($filtro === 'pendiente') {
            $where .= " AND cxc.estado = 'pendiente'";
        } elseif ($filtro === 'pagada') {
            $where .= " AND cxc.estado = 'pagada'";
        }

        $sql = "SELECT cxc.id, cxc.venta_id, cxc.monto_total, cxc.monto_pagado, cxc.saldo_pendiente, cxc.estado,
                       v.fecha AS venta_fecha, v.estado AS venta_estado,
                       cot.codigo_cotizacion, cot.status AS cotizacion_status
                FROM cuentas_por_cobrar cxc
                INNER JOIN ventas v ON v.id = cxc.venta_id
                LEFT JOIN cotizaciones cot ON cot.id_cotizacion = cxc.cotizacion_id
                {$where}
                ORDER BY cxc.estado ASC, cxc.saldo_pendiente DESC, cxc.id DESC";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param($tipos, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();
        $html = '';

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $id = (int) $row['id'];
                $ventaId = (int) $row['venta_id'];
                $saldo = (float) $row['saldo_pendiente'];
                $estado = (string) ($row['estado'] ?? 'pendiente');
                $estCls = $estado === 'pagada' ? 'badge-success' : 'badge-warning';
                $estTxt = $estado === 'pagada' ? 'Pagada' : 'Pendiente';
                $codCot = trim((string) ($row['codigo_cotizacion'] ?? ''));
                $codHtml = $codCot !== '' ? htmlspecialchars($codCot) : '<span class="text-muted">—</span>';
                $cotAprobada = (int) ($row['cotizacion_status'] ?? 0) === 2;

                $btnPago = '';
                if ($estado === 'pendiente' && $saldo > 0.009) {
                    if ($cotAprobada) {
                        $btnPago = '<button type="button" class="btn btn-sm btn-success btn-registrar-pago-cpp"
                            data-id="' . $id . '"
                            data-saldo="' . number_format($saldo, 2, '.', '') . '"
                            data-venta="' . $ventaId . '"
                            data-codigo="' . htmlspecialchars($codCot, ENT_QUOTES, 'UTF-8') . '">
                            <i class="fas fa-dollar-sign"></i> Registrar pago
                        </button>';
                    } else {
                        $btnPago = '<span class="text-muted small" title="Debe esperar la aprobación del pago inicial.">'
                            . 'Pago inicial en revisión</span>';
                    }
                }

                $html .= '<tr>';
                $html .= '<td>' . $id . '</td>';
                $html .= '<td><strong>' . $codHtml . '</strong><br>'
                    . '<small class="text-muted">Venta #' . $ventaId . '</small></td>';
                $html .= '<td>$' . number_format((float) $row['monto_total'], 2, '.', ',') . '</td>';
                $html .= '<td>$' . number_format((float) $row['monto_pagado'], 2, '.', ',') . '</td>';
                $html .= '<td style="font-weight:bold;color:#c0392b;">$' . number_format($saldo, 2, '.', ',') . '</td>';
                $html .= '<td><span class="badge ' . $estCls . '">' . $estTxt . '</span></td>';
                $html .= '<td nowrap>'
                    . $btnPago
                    . ' <button type="button" class="btn btn-sm btn-outline-secondary btn-ver-pagos-cpp" data-id="' . $id . '">'
                    . '<i class="fas fa-list"></i> Pagos</button>'
                    . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="7" class="text-center">No tienes cuentas por pagar en este filtro.</td></tr>';
        }
        $stmt->close();

        echo $html;
        break;

    case 'listar_pagos':
        header('Content-Type: application/json; charset=utf-8');
        $cuentaId = (int) ($_GET['cuenta_id'] ?? 0);
        if ($cuentaId <= 0) {
            echo json_encode(['error' => 'Cuenta no válida']);
            exit;
        }

        $cuenta = CuentasPorCobrar::obtenerCuentaParaCliente($conn, $cuentaId, $id_cliente);
        if (!$cuenta) {
            echo json_encode(['error' => 'Cuenta no encontrada']);
            exit;
        }

        $stmt = $conn->prepare(
            'SELECT p.id, p.monto, p.referencia, p.observaciones, p.es_pago_inicial, p.origen,
                    DATE_FORMAT(p.fecha_pago, \'%d/%m/%Y %H:%i\') AS fecha_fmt,
                    fp.nombre AS forma_pago
             FROM cuentas_por_cobrar_pagos p
             LEFT JOIN formas_pago fp ON fp.id = p.forma_pago_id
             WHERE p.cuenta_id = ?
             ORDER BY p.fecha_pago ASC, p.id ASC'
        );
        $stmt->bind_param('i', $cuentaId);
        $stmt->execute();
        $res = $stmt->get_result();
        $pagos = [];
        while ($row = $res->fetch_assoc()) {
            $pagos[] = [
                'id' => (int) $row['id'],
                'monto' => (float) $row['monto'],
                'referencia' => (string) ($row['referencia'] ?? ''),
                'observaciones' => (string) ($row['observaciones'] ?? ''),
                'forma_pago' => (string) ($row['forma_pago'] ?? '—'),
                'fecha' => (string) ($row['fecha_fmt'] ?? ''),
                'es_pago_inicial' => (int) ($row['es_pago_inicial'] ?? 0) === 1,
                'origen' => (string) ($row['origen'] ?? ''),
            ];
        }
        $stmt->close();

        echo json_encode(['pagos' => $pagos]);
        break;

    case 'registrar_pago':
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'mensaje' => 'Método no permitido']);
            exit;
        }

        $cuentaId = (int) ($_POST['cuenta_id'] ?? 0);
        $monto = isset($_POST['monto']) ? (float) str_replace(',', '.', (string) $_POST['monto']) : 0.0;
        $formaId = (int) ($_POST['forma_pago_id'] ?? 0);
        $referencia = trim((string) ($_POST['referencia'] ?? ''));
        $observaciones = trim((string) ($_POST['observaciones'] ?? ''));

        if ($cuentaId <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'Cuenta no válida']);
            exit;
        }

        $cuenta = CuentasPorCobrar::obtenerCuentaParaCliente($conn, $cuentaId, $id_cliente);
        if (!$cuenta) {
            echo json_encode(['success' => false, 'mensaje' => 'Cuenta no encontrada']);
            exit;
        }

        if ((int) ($cuenta['cotizacion_status'] ?? 0) !== 2) {
            echo json_encode([
                'success' => false,
                'mensaje' => 'El pago inicial aún no ha sido aprobado. Espere la confirmación del equipo.',
            ]);
            exit;
        }

        if ($referencia === '' && $observaciones === '') {
            echo json_encode(['success' => false, 'mensaje' => 'Indique la referencia o un comentario del pago.']);
            exit;
        }

        $conn->begin_transaction();
        try {
            CuentasPorCobrar::registrarPago(
                $conn,
                $cuentaId,
                $monto,
                $formaId,
                $referencia,
                $observaciones,
                'cliente'
            );

            $stmt = $conn->prepare(
                'SELECT venta_id, saldo_pendiente, estado FROM cuentas_por_cobrar WHERE id = ? LIMIT 1'
            );
            $stmt->bind_param('i', $cuentaId);
            $stmt->execute();
            $info = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $conn->commit();

            $mensaje = 'Pago registrado correctamente.';
            if ((float) ($info['saldo_pendiente'] ?? 0) <= 0.009) {
                $mensaje .= ' Su cuenta quedó pagada en su totalidad.';
            }

            echo json_encode([
                'success' => true,
                'mensaje' => $mensaje,
                'saldo_pendiente' => (float) ($info['saldo_pendiente'] ?? 0),
                'estado_cuenta' => (string) ($info['estado'] ?? ''),
            ]);
        } catch (Throwable $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'mensaje' => $e->getMessage()]);
        }
        break;

    default:
        echo '';
        break;
}
