<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Pagination.php';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $buscar   = isset($_POST['buscar']) ? trim($_POST['buscar']) : '';
        $tipo_doc = isset($_POST['tipo_doc']) ? trim($_POST['tipo_doc']) : '';

        $where = [];

        if ($buscar !== '') {
            $buscarEscaped = $conn->real_escape_string($buscar);
            $where[] = "(nombre LIKE '%$buscarEscaped%' OR cedrif LIKE '%$buscarEscaped%' OR telefono LIKE '%$buscarEscaped%' OR email LIKE '%$buscarEscaped%')";
        }

        if ($tipo_doc !== '') {
            $docEscaped = $conn->real_escape_string($tipo_doc);
            $where[] = "cedrif LIKE '$docEscaped%'";
        }

        $fil = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $total = (int) ($conn->query("SELECT COUNT(*) AS c FROM proveedores" . $fil)->fetch_assoc()['c'] ?? 0);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = "
            SELECT 
                id,
                nombre,
                telefono,
                email,
                direccion, 
                cedrif
            FROM proveedores
            " . $fil . "
            ORDER BY nombre ASC
        " . $pg->limitClause();

        $result = $conn->query($sql);
        $proveedores = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $proveedores[] = $row;
            }
        }
        
        ob_start();
        $i = $pg->rowNumberStart() - 1;
        if (!empty($proveedores)) {
            foreach ($proveedores as $p) {
                $i++;
                
                $badgeColor = (strpos(strtoupper($p['cedrif']), 'J') === 0) ? 'badge-primary' : 'badge-secondary';
                $cedrifFormat = '<span class="badge ' . $badgeColor . '" style="padding: 4px 6px; font-family: monospace;">' . htmlspecialchars($p['cedrif']) . '</span>';
                
                echo '<tr>';
                echo '<td>' . $i . '</td>';
                echo '<td>' . $p['cedrif'] . '</td>';
                echo '<td><strong>' . htmlspecialchars($p['nombre']) . '</strong></td>';
                echo '<td nowrap>' . htmlspecialchars($p['telefono'] ?? '—') . '</td>';
                echo '<td>' . htmlspecialchars($p['email'] ?? '—') . '</td>';
                echo '<td style="white-space: nowrap;">';
                echo '  <button class="btn btn-sm btn-primary" title="Editar" onclick="editarProveedor(' . htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') . ')" style="margin-right: 5px;"><i class="fas fa-edit"></i></button>';
                echo '  <button class="btn btn-sm btn-danger" title="Eliminar" onclick="eliminarProveedor(' . (int)$p['id'] . ')"><i class="fas fa-trash"></i></button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr>';
            echo '  <td colspan="6" class="text-center text-muted" style="padding: 30px;">';
            echo '      <i class="fas fa-truck-loading" style="font-size: 24px; opacity: 0.3; margin-bottom: 7px;"></i><br>';
            echo '      No se encontraron proveedores que coincidan con la búsqueda.';
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
            $nombre = trim($_POST['nombre'] ?? '');
            $documento = trim($_POST['documento'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del proveedor es obligatorio");
            }

            if ($documento === '') {
                throw new Exception("El documento (RIF/Cédula) del proveedor es obligatorio");
            }

            $documentoNorm = strtoupper(preg_replace('/\s+/', '', $documento));

            $checkSql = "SELECT id FROM proveedores WHERE UPPER(REPLACE(TRIM(cedrif), ' ', '')) = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $documentoNorm);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe un proveedor con el mismo documento (RIF/Cédula).");
            }
            $checkStmt->close();

            $stmt = $conn->prepare("
                INSERT INTO proveedores (nombre, cedrif, telefono, email, direccion)
                VALUES (?, ?, ?, ?, ?)
            ");

            $telefono = empty($telefono) ? null : $telefono;
            $email = empty($email) ? null : $email;
            $direccion = empty($direccion) ? null : $direccion;

            $stmt->bind_param("sssss", $nombre, $documento, $telefono, $email, $direccion);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Proveedor creado exitosamente', 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de proveedor requerido");

            $nombre = trim($_POST['nombre'] ?? '');
            $documento = trim($_POST['documento'] ?? '');
            $telefono = trim($_POST['telefono'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $direccion = trim($_POST['direccion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del proveedor es obligatorio");
            }

            if ($documento === '') {
                throw new Exception("El documento (RIF/Cédula) del proveedor es obligatorio");
            }

            $documentoNorm = strtoupper(preg_replace('/\s+/', '', $documento));

            $checkSql = "SELECT id FROM proveedores WHERE id != ? AND UPPER(REPLACE(TRIM(cedrif), ' ', '')) = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("is", $id, $documentoNorm);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otro proveedor con el mismo documento (RIF/Cédula).");
            }
            $checkStmt->close();

            $stmt = $conn->prepare("
                UPDATE proveedores 
                SET nombre = ?, 
                    cedrif = ?,
                    telefono = ?, 
                    email = ?, 
                    direccion = ?
                WHERE id = ?
            ");

            $telefono = empty($telefono) ? null : $telefono;
            $email = empty($email) ? null : $email;
            $direccion = empty($direccion) ? null : $direccion;

            $stmt->bind_param("sssssi", $nombre, $documento, $telefono, $email, $direccion, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Proveedor actualizado exitosamente']);
            break;

        case 'eliminar':
            $id = $_POST['id'] ?? null;
            if (!$id) {
                throw new Exception("ID de proveedor requerido");
            }

            $stmt = $conn->prepare("DELETE FROM proveedores WHERE id = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'Proveedor eliminado exitosamente']);
            $stmt->close();
            break;

        default:
            throw new Exception("Acción no válida");
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
?>

