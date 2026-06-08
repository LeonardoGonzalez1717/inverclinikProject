<?php
session_start();
require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../lib/CuentasPorCobrar.php';
require_once __DIR__ . '/../lib/Auditoria.php';

CuentasPorCobrar::asegurarTablas($conn);

$action = $_REQUEST['action'] ?? '';

switch ($action) {
    case 'listar_cuentas':
        $filtro = trim((string) ($_GET['filtro'] ?? 'pendiente'));

        $where = '';
        if ($filtro === 'pendiente') {
            $where = "WHERE cxc.estado = 'pendiente'";
        } elseif ($filtro === 'pagada') {
            $where = "WHERE cxc.estado = 'pagada'";
        }

        $sql = "SELECT cxc.id, cxc.venta_id, cxc.monto_total, cxc.monto_pagado, cxc.saldo_pendiente, cxc.estado,
                       cxc.creado_en,
                       v.fecha AS venta_fecha, v.estado AS venta_estado,
                       cl.nombre AS cliente_nombre,
                       cot.codigo_cotizacion
                FROM cuentas_por_cobrar cxc
                INNER JOIN ventas v ON v.id = cxc.venta_id
                INNER JOIN clientes cl ON cl.id = cxc.cliente_id
                LEFT JOIN cotizaciones cot ON cot.id_cotizacion = cxc.cotizacion_id
                {$where}
                ORDER BY cxc.estado ASC, cxc.saldo_pendiente DESC, cxc.id DESC";

        $res = $conn->query($sql);
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
                $ventaEstado = htmlspecialchars((string) ($row['venta_estado'] ?? ''));

                $btnPago = '';
                if ($estado === 'pendiente' && $saldo > 0.009) {
                    $btnPago = '<button type="button" class="btn btn-sm btn-success btn-registrar-pago"
                        data-id="' . $id . '"
                        data-saldo="' . number_format($saldo, 2, '.', '') . '"
                        data-cliente="' . htmlspecialchars((string) $row['cliente_nombre'], ENT_QUOTES, 'UTF-8') . '"
                        data-venta="' . $ventaId . '">
                        <i class="fas fa-dollar-sign"></i> Registrar abono
                    </button>';
                }

                $html .= '<tr>';
                $html .= '<td>' . $id . '</td>';
                $html .= '<td><strong>' . htmlspecialchars((string) $row['cliente_nombre']) . '</strong><br>'
                    . '<small class="text-muted">Venta #' . $ventaId . ' · ' . $codHtml . '</small></td>';
                $html .= '<td>$' . number_format((float) $row['monto_total'], 2, '.', ',') . '</td>';
                $html .= '<td>$' . number_format((float) $row['monto_pagado'], 2, '.', ',') . '</td>';
                $html .= '<td style="font-weight:bold;color:#c0392b;">$' . number_format($saldo, 2, '.', ',') . '</td>';
                $html .= '<td><span class="badge ' . $estCls . '">' . $estTxt . '</span><br>'
                    . '<small class="text-muted">Venta: ' . $ventaEstado . '</small></td>';
                $html .= '<td nowrap>'
                    . $btnPago
                    . ' <button type="button" class="btn btn-sm btn-info btn-ver-pagos" data-id="' . $id . '">'
                    . '<i class="fas fa-list"></i> Pagos</button>'
                    . '</td>';
                $html .= '</tr>';
            }
        } else {
            $html = '<tr><td colspan="7" class="text-center">No hay cuentas por cobrar en este filtro.</td></tr>';
        }

        echo $html;
        break;

    case 'listar_pagos':
        header('Content-Type: application/json; charset=utf-8');
        $cuentaId = (int) ($_GET['cuenta_id'] ?? 0);
        if ($cuentaId <= 0) {
            echo json_encode(['error' => 'Cuenta no válida']);
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
        restringirEscritura();

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

        $conn->begin_transaction();
        try {
            CuentasPorCobrar::registrarPago(
                $conn,
                $cuentaId,
                $monto,
                $formaId,
                $referencia,
                $observaciones,
                'interno'
            );

            $stmt = $conn->prepare(
                'SELECT venta_id, saldo_pendiente, estado FROM cuentas_por_cobrar WHERE id = ? LIMIT 1'
            );
            $stmt->bind_param('i', $cuentaId);
            $stmt->execute();
            $info = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $conn->commit();

            Auditoria::registrar(
                $conn,
                'Abono cuenta por cobrar #' . $cuentaId . ' (venta #' . (int) ($info['venta_id'] ?? 0)
                    . '). Monto: ' . $monto . '. Saldo: ' . ($info['saldo_pendiente'] ?? 0) . '.',
                'Cuentas por cobrar'
            );

            $mensaje = 'Abono registrado correctamente.';
            if ((float) ($info['saldo_pendiente'] ?? 0) <= 0.009) {
                $mensaje .= ' La cuenta quedó pagada y la venta fue marcada como aprobada.';
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
