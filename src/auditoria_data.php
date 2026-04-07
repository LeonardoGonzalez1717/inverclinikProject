<?php
session_start();

require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../lib/Auditoria.php';

if (empty($_SESSION['iduser']) || (int) ($_SESSION['role_id'] ?? 0) !== 1) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/**
 * @param string $types
 * @param array<int,mixed> $params
 */
function auditoria_bind_params_mysqli(mysqli_stmt $stmt, string $types, array $params): void
{
    if ($types === '' || $params === []) {
        return;
    }
    $refs = [];
    foreach ($params as $i => $v) {
        $refs[$i] = &$params[$i];
    }
    array_unshift($refs, $types);
    call_user_func_array([$stmt, 'bind_param'], $refs);
}

try {
    if ($action === 'listar_html') {
        header('Content-Type: text/html; charset=utf-8');
        $limite = (int) ($_GET['limite'] ?? $_POST['limite'] ?? 500);
        if ($limite < 1) {
            $limite = 500;
        }
        if ($limite > 2000) {
            $limite = 2000;
        }

        $fechaDesde = trim((string) ($_POST['fecha_desde'] ?? $_GET['fecha_desde'] ?? ''));
        $fechaHasta = trim((string) ($_POST['fecha_hasta'] ?? $_GET['fecha_hasta'] ?? ''));
        $tipoMovimiento = trim((string) ($_POST['tipo_movimiento'] ?? $_GET['tipo_movimiento'] ?? ''));
        $usuarioFiltro = trim((string) ($_POST['usuario'] ?? $_GET['usuario'] ?? ''));

        if ($fechaDesde !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde)) {
            throw new Exception('Fecha desde no válida');
        }
        if ($fechaHasta !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta)) {
            throw new Exception('Fecha hasta no válida');
        }
        if ($fechaDesde !== '' && $fechaHasta !== '' && $fechaDesde > $fechaHasta) {
            throw new Exception('La fecha desde no puede ser posterior a la fecha hasta');
        }

        $where = ['1=1'];
        $types = '';
        $bindParams = [];

        if ($fechaDesde !== '') {
            $where[] = 'DATE(fecha_hora) >= ?';
            $types .= 's';
            $bindParams[] = $fechaDesde;
        }
        if ($fechaHasta !== '') {
            $where[] = 'DATE(fecha_hora) <= ?';
            $types .= 's';
            $bindParams[] = $fechaHasta;
        }
        if ($tipoMovimiento !== '') {
            $where[] = 'modulo = ?';
            $types .= 's';
            $bindParams[] = $tipoMovimiento;
        }

        if ($usuarioFiltro !== '') {
            if (preg_match('/^u:(\d+)$/', $usuarioFiltro, $m)) {
                $where[] = 'id_usuario = ?';
                $types .= 'i';
                $bindParams[] = (int) $m[1];
            } elseif (preg_match('/^c:(\d+)$/', $usuarioFiltro, $m)) {
                $where[] = 'id_cliente = ?';
                $types .= 'i';
                $bindParams[] = (int) $m[1];
            } elseif (preg_match('/^n:(.+)$/', $usuarioFiltro, $m)) {
                $nom = rawurldecode($m[1]);
                $where[] = 'nombre_actor = ?';
                $types .= 's';
                $bindParams[] = $nom;
            }
        }

        $sql = 'SELECT id_evento, nombre_actor, modulo, accion, fecha_hora
                FROM auditoria
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY fecha_hora DESC, id_evento DESC
                LIMIT ?';
        $types .= 'i';
        $bindParams[] = $limite;

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            throw new Exception('No se pudo consultar la auditoría');
        }
        auditoria_bind_params_mysqli($stmt, $types, $bindParams);
        $stmt->execute();
        $res = $stmt->get_result();
        $i = 1;
        if (!$res) {
            $stmt->close();
            throw new Exception('No se pudo consultar la auditoría');
        }
        if ($res->num_rows === 0) {
            echo '<tr><td colspan="5" class="text-center text-muted">No hay registros de auditoría.</td></tr>';
        } else {
            while ($row = $res->fetch_assoc()) {
                $fecha = $row['fecha_hora'] ? date('d/m/Y H:i:s', strtotime($row['fecha_hora'])) : '';
                $mod = htmlspecialchars($row['modulo'] ?? '', ENT_QUOTES, 'UTF-8');
                $actor = htmlspecialchars($row['nombre_actor'] ?? '', ENT_QUOTES, 'UTF-8');
                $acc = htmlspecialchars($row['accion'] ?? '', ENT_QUOTES, 'UTF-8');
                echo '<tr>';
                echo '<td>' . (int) $i++. '</td>';
                echo '<td>' . $fecha . '</td>';
                echo '<td>' . $actor . '</td>';
                echo '<td>' . ($mod !== '' ? $mod : '—') . '</td>';
                echo '<td class="aud-accion">' . $acc . '</td>';
                echo '</tr>';
            }
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    throw new Exception('Acción no válida');
} catch (Exception $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
