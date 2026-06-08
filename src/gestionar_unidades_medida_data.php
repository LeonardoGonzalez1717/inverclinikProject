<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Pagination.php';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $buscar  = isset($_POST['buscar']) ? trim($_POST['buscar']) : '';
        $decimal = isset($_POST['decimal']) ? trim($_POST['decimal']) : '';

        $where = [];

        if ($buscar !== '') {
            $buscarEscaped = $conn->real_escape_string($buscar);
            $where[] = "(codigo LIKE '%$buscarEscaped%' OR nombre LIKE '%$buscarEscaped%')";
        }

        if ($decimal !== '') {
            $decimalEscaped = (int)$decimal;
            $where[] = "permite_movimiento_decimal = $decimalEscaped";
        }

        $fil = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $countRow = $conn->query("SELECT COUNT(*) AS c FROM unidad_medida" . $fil)->fetch_assoc();
        $total = (int) ($countRow['c'] ?? 0);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = "
            SELECT id, codigo, nombre, permite_movimiento_decimal
            FROM unidad_medida
            " . $fil . "
            ORDER BY nombre ASC
        " . $pg->limitClause();
        
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
                $dec = ((int) ($r['permite_movimiento_decimal'] ?? 1)) === 1;
                $decTxt = $dec ? 'Sí' : 'No (solo enteros)';
                echo '<tr>';
                echo '<td>' . $i . '</td>';
                echo '<td>' . htmlspecialchars($r['codigo']) . '</td>';
                echo '<td><strong>' . htmlspecialchars($r['nombre']) . '</strong></td>';
                echo '<td>' . $decTxt . '</td>';
                echo '<td>';
                echo '  <button type="button" class="btn btn-sm btn-primary" onclick="editarUm(' . htmlspecialchars(json_encode($r), ENT_QUOTES, 'UTF-8') . ')"><i class="fas fa-edit"></i> Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr>';
            echo '  <td colspan="5" class="text-center text-muted" style="padding: 25px;">';
            echo '      <i class="fas fa-balance-scale" style="font-size: 24px; opacity: 0.3; margin-bottom: 7px;"></i><br>';
            echo '      No se encontraron unidades de medida bajo los criterios de búsqueda.';
            echo '  </td>';
            echo '</tr>';
        }
        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    restringirEscritura();

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $codigo = strtolower(trim(preg_replace('/\s+/', '_', $_POST['codigo'] ?? '')));
            $nombre = trim($_POST['nombre'] ?? '');
            $permite = isset($_POST['permite_movimiento_decimal']) ? (int) $_POST['permite_movimiento_decimal'] : 1;
            $permite = $permite === 0 ? 0 : 1;

            if ($codigo === '' || strlen($codigo) > 32) {
                throw new Exception('El código es obligatorio (máx. 32 caracteres). Use solo letras, números y guiones bajos.');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $codigo)) {
                throw new Exception('El código solo puede contener letras minúsculas, números y guión bajo.');
            }
            if ($nombre === '' || strlen($nombre) > 100) {
                throw new Exception('El nombre es obligatorio (máx. 100 caracteres).');
            }

            $chk = $conn->prepare('SELECT id FROM unidad_medida WHERE codigo = ?');
            $chk->bind_param('s', $codigo);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $chk->close();
                throw new Exception('Ya existe una unidad con ese código.');
            }
            $chk->close();

            $stmt = $conn->prepare(
                'INSERT INTO unidad_medida (codigo, nombre, permite_movimiento_decimal) VALUES (?, ?, ?)'
            );
            $stmt->bind_param('ssi', $codigo, $nombre, $permite);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Unidad de medida creada.', 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;
            if ($id <= 0) {
                throw new Exception('ID inválido');
            }
            $codigo = strtolower(trim(preg_replace('/\s+/', '_', $_POST['codigo'] ?? '')));
            $nombre = trim($_POST['nombre'] ?? '');
            $permite = isset($_POST['permite_movimiento_decimal']) ? (int) $_POST['permite_movimiento_decimal'] : 1;
            $permite = $permite === 0 ? 0 : 1;

            if ($codigo === '' || strlen($codigo) > 32) {
                throw new Exception('El código es obligatorio (máx. 32 caracteres).');
            }
            if (!preg_match('/^[a-z0-9_]+$/', $codigo)) {
                throw new Exception('El código solo puede contener letras minúsculas, números y guión bajo.');
            }
            if ($nombre === '' || strlen($nombre) > 100) {
                throw new Exception('El nombre es obligatorio (máx. 100 caracteres).');
            }

            $chk = $conn->prepare('SELECT id FROM unidad_medida WHERE codigo = ? AND id != ?');
            $chk->bind_param('si', $codigo, $id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $chk->close();
                throw new Exception('Otra unidad ya usa ese código.');
            }
            $chk->close();

            $stmt = $conn->prepare(
                'UPDATE unidad_medida SET codigo = ?, nombre = ?, permite_movimiento_decimal = ? WHERE id = ?'
            );
            $stmt->bind_param('ssii', $codigo, $nombre, $permite, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Unidad actualizada.']);
            break;

        default:
            throw new Exception('Acción no válida');
    }
} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
