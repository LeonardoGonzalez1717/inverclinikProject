<?php
session_start();
require_once '../connection/connection.php';

$action = $_REQUEST['action'] ?? '';
$id_cliente = isset($_SESSION['id_cliente']) ? (int) $_SESSION['id_cliente'] : 0;

if ($id_cliente <= 0 && in_array($action, ['listar_mis_presupuestos', 'ver_detalle_cliente'], true)) {
    if ($action === 'ver_detalle_cliente') {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sesión no válida']);
    } else {
        echo '<tr><td colspan="5" class="text-center">Inicia sesión para ver tus presupuestos.</td></tr>';
    }
    exit;
}

function etiqueta_estado_presupuesto(int $st): array
{
    switch ($st) {
        case 0:
            return ['Pendiente', 'badge-warning'];
        case 2:
            return ['En cotización', 'badge-info'];
        default:
            return ['Estado ' . $st, 'badge-secondary'];
    }
}

switch ($action) {
    case 'listar_mis_presupuestos':
        $sql = "SELECT id_presupuesto, codigo_presupuesto, total, status,
                DATE_FORMAT(fecha_creacion, '%d/%m/%Y') AS fecha
                FROM presupuestos
                WHERE id_cliente = ?
                ORDER BY id_presupuesto DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        $res = $stmt->get_result();
        $html = '';

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $st = (int) $row['status'];
                [$statusLabel, $statusClass] = etiqueta_estado_presupuesto($st);
                $cod = $row['codigo_presupuesto'] ?? '';
                $codEsc = htmlspecialchars($cod, ENT_QUOTES, 'UTF-8');
                $jsonCod = json_encode($cod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);

                $html .= '<tr>'
                    . '<td>#' . $codEsc . '</td>'
                    . '<td>' . htmlspecialchars($row['fecha']) . '</td>'
                    . '<td><span class="badge ' . $statusClass . '">' . htmlspecialchars($statusLabel) . '</span></td>'
                    . '<td><strong>$' . number_format((float) $row['total'], 2) . '</strong></td>'
                    . '<td>'
                    . '<button type="button" class="btn btn-sm btn-primary" onclick=\'verDetallePresupuesto('
                    . (int) $row['id_presupuesto'] . ', ' . $jsonCod . ')\'>Ver</button>'
                    . '</td>'
                    . '</tr>';
            }
        } else {
            $html = '<tr><td colspan="5" class="text-center">Aún no has creado presupuestos desde el catálogo.</td></tr>';
        }
        $stmt->close();
        echo $html;
        break;

    case 'ver_detalle_cliente':
        header('Content-Type: application/json; charset=utf-8');
        $id_presupuesto = (int) ($_GET['id'] ?? 0);
        if ($id_presupuesto <= 0) {
            echo json_encode(['error' => 'Presupuesto no válido']);
            exit;
        }

        $stmtCab = $conn->prepare(
            'SELECT codigo_presupuesto, total, status, DATE_FORMAT(fecha_creacion, \'%d/%m/%Y\') AS fecha
             FROM presupuestos WHERE id_presupuesto = ? AND id_cliente = ?'
        );
        $stmtCab->bind_param('ii', $id_presupuesto, $id_cliente);
        $stmtCab->execute();
        $rc = $stmtCab->get_result();
        if (!$rc || $rc->num_rows === 0) {
            $stmtCab->close();
            echo json_encode(['error' => 'Presupuesto no encontrado']);
            exit;
        }
        $cabRow = $rc->fetch_assoc();
        $stmtCab->close();

        $st = (int) ($cabRow['status'] ?? 0);
        [$estTxt, $estCls] = etiqueta_estado_presupuesto($st);

        $cabecera = [
            'codigo_presupuesto' => (string) ($cabRow['codigo_presupuesto'] ?? ''),
            'fecha' => (string) ($cabRow['fecha'] ?? ''),
            'total' => (float) ($cabRow['total'] ?? 0),
            'estado_texto' => $estTxt,
            'estado_class' => $estCls,
        ];

        $sql = "SELECT pd.cantidad, pd.precio_unitario, pd.subtotal,
                pr.nombre AS producto, rt.nombre_rango AS talla
                FROM presupuesto_detalles pd
                INNER JOIN recetas r ON pd.id_producto = r.id
                INNER JOIN productos pr ON r.producto_id = pr.id
                INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                WHERE pd.id_presupuesto = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_presupuesto);
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
