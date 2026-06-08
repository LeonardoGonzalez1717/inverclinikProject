<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/Pagination.php';

error_reporting(E_ERROR | E_PARSE);

$action = $_POST['action'] ?? '';
$data = [];

if (empty($action)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: [];
    $action = $data['action'] ?? '';
}

try {

    if ($action === 'listar_html') {
        $buscar = isset($_POST['buscar']) ? trim($_POST['buscar']) : '';
        $activo = isset($_POST['activo']) ? trim($_POST['activo']) : '';

        $whereClauses = [];

        if ($buscar !== '') {
            $buscarEscaped = $conn->real_escape_string($buscar);
            $whereClauses[] = "(nombre LIKE '%$buscarEscaped%' OR descripcion LIKE '%$buscarEscaped%')";
        }

        if ($activo !== '') {
            $activoEscaped = (int)$activo;
            $whereClauses[] = "activo = $activoEscaped";
        }

        $whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

        // Conteo total
        $total = (int) ($conn->query("SELECT COUNT(*) AS c FROM talleres" . $whereSql)->fetch_assoc()['c'] ?? 0);
        $pg = Pagination::fromInput($total, $_POST);

        // Consulta de registros
        $sql = "SELECT id, nombre, descripcion, activo FROM talleres " . $whereSql . " ORDER BY nombre ASC " . $pg->limitClause();
        $result = $conn->query($sql);

        ob_start();
        $i = $pg->rowNumberStart() - 1;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $i++;
                
                $desc = (!empty($row['descripcion'])) ? htmlspecialchars($row['descripcion']) : '<em class="text-muted">Sin descripción</em>';
                
                $estadoBadge = !empty($row['activo']) 
                    ? '<span style="padding: 4px 8px; background-color: #28a745; color: #fff; border-radius: 15px;">Activo</span>' 
                    : '<span style="padding: 4px 8px; background-color: #dc3545; color: #fff; border-radius: 15px;">Inactivo</span>';

                echo '<tr>';
                echo '<td>' . $i . '</td>';
                echo '<td><strong>' . htmlspecialchars($row['nombre']) . '</strong></td>';
                echo '<td>' . $desc . '</td>';
                echo '<td>' . $estadoBadge . '</td>';
                echo '<td style="white-space: nowrap;">';
                echo '  <button type="button" class="btn btn-sm btn-primary" onclick=\'editarTaller(' . json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ')\'><i class="fas fa-edit"></i></button> ';
                echo '  <button type="button" class="btn btn-sm btn-danger" onclick="eliminarTaller(' . (int) $row['id'] . ')"><i class="fas fa-trash"></i></button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr>';
            echo '  <td colspan="5" class="text-center text-muted" style="padding: 25px;">';
            echo '      No se encontraron talleres registrados.';
            echo '  </td>';
            echo '</tr>';
        }
        
        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    // ------------------------------------------------------------------
    // ACCIÓN: CREAR REGISTRO
    // ------------------------------------------------------------------
    if ($action === 'crear') {
        $nombre      = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
        $activo      = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;

        if ($nombre === '') {
            echo json_encode(['success' => false, 'message' => 'El nombre del taller es obligatorio.']);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO talleres (nombre, descripcion, activo) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $descripcion, $activo);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Taller registrado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al guardar el taller: ' . $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    // ------------------------------------------------------------------
    // ACCIÓN: EDITAR REGISTRO
    // ------------------------------------------------------------------
    if ($action === 'editar') {
        $id          = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nombre      = isset($_POST['nombre']) ? trim($_POST['nombre']) : '';
        $descripcion = isset($_POST['descripcion']) ? trim($_POST['descripcion']) : '';
        $activo      = isset($_POST['activo']) ? (int)$_POST['activo'] : 1;

        if ($id <= 0 || $nombre === '') {
            echo json_encode(['success' => false, 'message' => 'Datos insuficientes para realizar la edición.']);
            exit;
        }

        $stmt = $conn->prepare("UPDATE talleres SET nombre = ?, descripcion = ?, activo = ? WHERE id = ?");
        $stmt->bind_param("ssii", $nombre, $descripcion, $activo, $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Taller actualizado exitosamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar el taller: ' . $stmt->error]);
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    // ------------------------------------------------------------------
    // ACCIÓN: ELIMINAR REGISTRO
    // ------------------------------------------------------------------
    if ($action === 'eliminar') {
        $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID inválido.']);
            exit;
        }

        $stmt = $conn->prepare("DELETE FROM talleres WHERE id = ?");
        $stmt->bind_param("i", $id);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Taller eliminado correctamente de la base de datos.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al eliminar el taller o tiene dependencias activas.']);
        }
        $stmt->close();
        $conn->close();
        exit;
    }

    throw new Exception("Acción no válida");

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}