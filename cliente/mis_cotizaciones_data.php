<?php
session_start();
require_once '../connection/connection.php';

$action = $_REQUEST['action'] ?? '';
$id_cliente = isset($_SESSION['id_cliente']) ? (int) $_SESSION['id_cliente'] : 0;

if ($id_cliente <= 0 && in_array($action, ['listar_mis_cotizaciones', 'ver_detalle_cotizacion_cliente'], true)) {
    if ($action === 'ver_detalle_cotizacion_cliente') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sesión no válida']);
    } else {
        echo '<tr><td colspan="6" class="text-center">Inicia sesión para ver tus cotizaciones.</td></tr>';
    }
    exit;
}

function etiqueta_estado_cotizacion(int $st): array
{
    switch ($st) {
        case 1:
            return ['Enviada', 'badge-warning'];
        case 2:
            return ['Aprobada', 'badge-success'];
        case 3:
            return ['Rechazada', 'badge-danger'];
        default:
            return ['Estado ' . $st, 'badge-secondary'];
    }
}

switch ($action) {
    case 'listar_mis_cotizaciones':
        $sql = "SELECT c.id_cotizacion, c.codigo_cotizacion, c.codigo_presupuesto_origen, c.total, c.status,
                DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') AS fecha
                FROM cotizaciones c
                WHERE c.id_cliente = ?
                ORDER BY c.id_cotizacion DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_cliente);
        $stmt->execute();
        $res = $stmt->get_result();
        $html = '';

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $st = (int) $row['status'];
                [$estTxt, $estCls] = etiqueta_estado_cotizacion($st);
                $orig = trim((string) ($row['codigo_presupuesto_origen'] ?? ''));
                if ($orig === '' || strtoupper($orig) === 'VENTA DIRECTA') {
                    $origHtml = '<span class="text-muted">—</span>';
                } else {
                    $origHtml = htmlspecialchars($orig);
                }
                $cod = $row['codigo_cotizacion'] ?? '';
                $codEsc = htmlspecialchars($cod, ENT_QUOTES, 'UTF-8');
                $jsonCod = json_encode($cod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                $idCot = (int) $row['id_cotizacion'];

                $html .= '<tr>'
                    . '<td><strong>' . $codEsc . '</strong></td>'
                    . '<td>' . htmlspecialchars($row['fecha']) . '</td>'
                    . '<td>' . $origHtml . '</td>'
                    . '<td><span class="badge ' . $estCls . '">' . htmlspecialchars($estTxt) . '</span></td>'
                    . '<td><strong>$' . number_format((float) $row['total'], 2, '.', ',') . '</strong></td>'
                    . '<td style="white-space:nowrap">'
                    . '<button type="button" class="btn btn-sm btn-primary" onclick=\'verDetalleCotizacion(' . $idCot . ', ' . $jsonCod . ')\'>Ver</button> '
                    . '<a href="../formatos/ver_cotizacion.php?id=' . $idCot . '" target="_blank" class="btn btn-sm btn-info">Imprimir</a>'
                    . '</td>'
                    . '</tr>';
            }
        } else {
            $html = '<tr><td colspan="6" class="text-center">Aún no tienes cotizaciones registradas.</td></tr>';
        }
        $stmt->close();
        echo $html;
        break;

    case 'ver_detalle_cotizacion_cliente':
        header('Content-Type: application/json; charset=utf-8');
        $id_cotizacion = (int) ($_GET['id'] ?? 0);
        if ($id_cotizacion <= 0) {
            echo json_encode(['error' => 'Cotización no válida']);
            exit;
        }

        $stmtCab = $conn->prepare(
            'SELECT codigo_cotizacion, codigo_presupuesto_origen, total, status,
             DATE_FORMAT(fecha_registro, \'%d/%m/%Y\') AS fecha
             FROM cotizaciones WHERE id_cotizacion = ? AND id_cliente = ?'
        );
        $stmtCab->bind_param('ii', $id_cotizacion, $id_cliente);
        $stmtCab->execute();
        $rc = $stmtCab->get_result();
        if (!$rc || $rc->num_rows === 0) {
            $stmtCab->close();
            echo json_encode(['error' => 'Cotización no encontrada']);
            exit;
        }
        $cabRow = $rc->fetch_assoc();
        $stmtCab->close();

        $st = (int) ($cabRow['status'] ?? 0);
        [$estTxt, $estCls] = etiqueta_estado_cotizacion($st);
        $orig = trim((string) ($cabRow['codigo_presupuesto_origen'] ?? ''));
        if ($orig === '' || strtoupper($orig) === 'VENTA DIRECTA') {
            $orig = '';
        }

        $cabecera = [
            'codigo_cotizacion' => (string) ($cabRow['codigo_cotizacion'] ?? ''),
            'fecha' => (string) ($cabRow['fecha'] ?? ''),
            'presupuesto_origen' => $orig,
            'total' => (float) ($cabRow['total'] ?? 0),
            'estado_texto' => $estTxt,
            'estado_class' => $estCls,
        ];

        $sql = "SELECT cd.cantidad, cd.precio_unitario, cd.subtotal,
                p.nombre AS producto, rt.nombre_rango AS talla
                FROM cotizacion_detalles cd
                INNER JOIN recetas r ON r.id = cd.id_receta
                INNER JOIN productos p ON p.id = r.producto_id
                LEFT JOIN rangos_tallas rt ON rt.id = cd.id_talla
                WHERE cd.id_cotizacion = ?
                ORDER BY cd.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_cotizacion);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        echo json_encode(['cabecera' => $cabecera, 'items' => $items]);
        break;
}
