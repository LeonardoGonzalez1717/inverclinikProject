<?php
require_once __DIR__ . '/../connection/connection.php';
require_once __DIR__ . '/../classes/TasaBCV.php';

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$isCli = (PHP_SAPI === 'cli');
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($isCli && $action === '') {
    $action = 'auto_bcv';
}

try {
    if ($action === 'listar_html') {
        header('Content-Type: text/html; charset=utf-8');
        $fecha = $_POST['fecha'] ?? '';
        $hora = $_POST['hora'] ?? '';

        $sql = "SELECT t.id, t.tasa, t.fecha_hora, t.origen, u.username 
                FROM tasas_cambiarias t 
                LEFT JOIN users u ON t.usuario_id = u.id 
                WHERE 1=1";
        $params = [];
        $types = '';

        if ($fecha !== '') {
            $sql .= " AND DATE(t.fecha_hora) = ?";
            $params[] = $fecha;
            $types .= 's';
        }
        if ($hora !== '') {
            $sql .= " AND HOUR(t.fecha_hora) = ?";
            $params[] = $hora;
            $types .= 'i';
        }

        $sql .= " ORDER BY t.fecha_hora DESC";

        if (!empty($params)) {
            $stmt = $conn->prepare($sql);
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = $conn->query($sql);
        }

        $rows = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = $row;
            }
        }

        foreach ($rows as $r) {
            $fh = date('d/m/Y H:i', strtotime($r['fecha_hora']));
            $origen = $r['origen'] === 'bcv' ? 'BCV' : 'Manual';
            $user = htmlspecialchars($r['username'] ?? '-');
            echo '<tr>';
            echo '<td>' . htmlspecialchars($fh) . '</td>';
            echo '<td>' . number_format((float)$r['tasa'], 4, ',', '.') . '</td>';
            echo '<td>' . $origen . '</td>';
            echo '<td>' . $user . '</td>';
            echo '<td><button type="button" class="btn btn-sm btn-danger btn-borrar-tasa" data-id="' . (int)$r['id'] . '">Borrar</button></td>';
            echo '</tr>';
        }

        if (empty($rows)) {
            echo '<tr><td colspan="5" class="text-center">No hay tasas registradas para el filtro seleccionado.</td></tr>';
        }

        if (isset($stmt)) $stmt->close();
        $conn->close();
        exit;
    }

    switch ($action) {
        case 'obtener_bcv':
            $bcv = new TasaBCV();
            $tasa = $bcv->obtenerTasa();
            if ($tasa !== null) {
                echo json_encode(['success' => true, 'tasa' => $tasa, 'message' => 'Tasa BCV obtenida']);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se pudo obtener la tasa del BCV. ' . ($bcv->getLastError() ?: 'Revisa conexión o estructura de la página.')
                ]);
            }
            break;

        case 'auto_bcv':
            $bcv = new TasaBCV();
            $tasa = $bcv->obtenerTasa();
            if ($tasa === null) {
                throw new Exception('No se pudo obtener la tasa del BCV. ' . ($bcv->getLastError() ?: 'Revisa conexión o estructura de la página.'));
            }

            $tasa_val = (float) str_replace(',', '.', (string)$tasa);
            if ($tasa_val <= 0) {
                throw new Exception('La tasa obtenida no es válida.');
            }

            $fecha_hora_sql = date('Y-m-d H:i:s');
            $fecha_solo = substr($fecha_hora_sql, 0, 10);
            $hora = (int) date('H', strtotime($fecha_hora_sql));
            $es_franja_manana = ($hora < 13);

            if ($es_franja_manana) {
                $check = $conn->prepare("SELECT id FROM tasas_cambiarias WHERE DATE(fecha_hora) = ? AND HOUR(fecha_hora) < 13 LIMIT 1");
            } else {
                $check = $conn->prepare("SELECT id FROM tasas_cambiarias WHERE DATE(fecha_hora) = ? AND HOUR(fecha_hora) >= 13 LIMIT 1");
            }
            $check->bind_param('s', $fecha_solo);
            $check->execute();
            $existe = $check->get_result();
            $check->close();
            if ($existe && $existe->num_rows > 0) {
                $franja = $es_franja_manana ? '8 AM' : '1 PM';
                throw new Exception("Ya existe una tasa registrada para el {$franja} de esta fecha.");
            }

            $origen = 'bcv';
            $stmt = $conn->prepare("INSERT INTO tasas_cambiarias (tasa, fecha_hora, origen) VALUES (?, ?, ?)");
            $stmt->bind_param('dss', $tasa_val, $fecha_hora_sql, $origen);
            $stmt->execute();
            $id = $conn->insert_id;
            $stmt->close();

            echo json_encode([
                'success' => true,
                'message' => 'Tasa BCV registrada automaticamente.',
                'tasa' => $tasa_val,
                'id' => $id
            ]);
            break;

        case 'registrar':
            $tasa_val = isset($_POST['tasa']) ? (float) str_replace(',', '.', $_POST['tasa']) : 0;
            $fecha_hora = trim($_POST['fecha_hora'] ?? '');
            $origen = $_POST['origen'] ?? 'manual';
            if (!in_array($origen, ['bcv', 'manual'])) $origen = 'manual';

            if ($tasa_val <= 0) {
                throw new Exception('La tasa debe ser mayor que 0.');
            }

            $usuario_id = !empty($_SESSION['iduser']) ? (int)$_SESSION['iduser'] : null;

            if ($fecha_hora !== '') {
                $dt = DateTime::createFromFormat('Y-m-d\TH:i', $fecha_hora);
                if (!$dt) $dt = DateTime::createFromFormat('Y-m-d H:i', $fecha_hora);
                if (!$dt) $dt = DateTime::createFromFormat('d/m/Y H:i', $fecha_hora);
                $fecha_hora_sql = $dt ? $dt->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            } else {
                $fecha_hora_sql = date('Y-m-d H:i:s');
            }

            // BCV actualiza dos veces al día: 8 AM y 1 PM. Solo una tasa por franja (mañana / tarde) por día.
            $fecha_solo = substr($fecha_hora_sql, 0, 10);
            $hora = (int) date('H', strtotime($fecha_hora_sql));
            $es_franja_manana = ($hora < 13);

            if ($es_franja_manana) {
                $check = $conn->prepare("SELECT id FROM tasas_cambiarias WHERE DATE(fecha_hora) = ? AND HOUR(fecha_hora) < 13 LIMIT 1");
            } else {
                $check = $conn->prepare("SELECT id FROM tasas_cambiarias WHERE DATE(fecha_hora) = ? AND HOUR(fecha_hora) >= 13 LIMIT 1");
            }
            $check->bind_param('s', $fecha_solo);
            $check->execute();
            $existe = $check->get_result();
            $check->close();
            if ($existe && $existe->num_rows > 0) {
                $franja = $es_franja_manana ? '8 AM' : '1 PM';
                throw new Exception("Ya existe una tasa registrada para el {$franja} de esta fecha.");
            }

            if ($usuario_id !== null) {
                $stmt = $conn->prepare("INSERT INTO tasas_cambiarias (tasa, fecha_hora, origen, usuario_id) VALUES (?, ?, ?, ?)");
                $stmt->bind_param('dssi', $tasa_val, $fecha_hora_sql, $origen, $usuario_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO tasas_cambiarias (tasa, fecha_hora, origen) VALUES (?, ?, ?)");
                $stmt->bind_param('dss', $tasa_val, $fecha_hora_sql, $origen);
            }
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Tasa registrada correctamente.', 'id' => $conn->insert_id]);
            $stmt->close();
            break;

        case 'borrar':
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido.');
            $stmt = $conn->prepare("DELETE FROM tasas_cambiarias WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Tasa eliminada.']);
            break;

        case 'borrar_todas':
            $conn->query("DELETE FROM tasas_cambiarias");
            echo json_encode(['success' => true, 'message' => 'Todas las tasas han sido eliminadas.']);
            break;

        default:
            throw new Exception('Acción no válida.');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
