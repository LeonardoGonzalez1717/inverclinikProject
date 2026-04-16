<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';

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
        $sql = "SELECT id, nombre, codigo, activo FROM almacenes ORDER BY nombre ASC";
        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $act = !empty($row['activo']) ? 'Sí' : 'No';
                $cod = $row['codigo'] !== null && $row['codigo'] !== '' ? htmlspecialchars($row['codigo']) : '—';
                $onclick = 'editarAlmacen('
                    . (int) $row['id'] . ', '
                    . json_encode($row['nombre'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ', '
                    . json_encode($row['codigo'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ', '
                    . (!empty($row['activo']) ? 1 : 0)
                    . ')';
                echo '<tr>';
                echo '<td>' . (int) $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['nombre']) . '</td>';
                echo '<td>' . $cod . '</td>';
                // echo '<td>' . $act . '</td>';
                echo '<td>'
                    . '<button type="button" class="btn btn-sm btn-primary" onclick=\'' . $onclick . '\'>Editar</button> '
                    . '<button type="button" class="btn btn-sm btn-danger" onclick="eliminarAlmacen(' . (int) $row['id'] . ')">Eliminar</button>'
                    . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="5" class="text-center">No hay almacenes registrados</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'crear') {
        $nombre = trim($data['nombre'] ?? '');
        $codigo = trim($data['codigo'] ?? '');
        $activo = !empty($data['activo']) ? 1 : 0;

        if ($nombre === '') {
            throw new Exception("El nombre del almacén es obligatorio");
        }

        $stmt = $conn->prepare("INSERT INTO almacenes (nombre, codigo, activo) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $nombre, $codigo, $activo);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        Auditoria::registrar(
            $conn,
            'Almacén creado: id ' . (int) $newId . ' — ' . $nombre . ($codigo !== '' ? ' (' . $codigo . ')' : ''),
            'Almacenes'
        );

        echo json_encode(['success' => true, 'message' => 'Almacén registrado exitosamente']);
        $conn->close();
        exit;
    }

    if ($action === 'editar') {
        $id = (int) ($data['id'] ?? 0);
        $nombre = trim($data['nombre'] ?? '');
        $codigo = trim($data['codigo'] ?? '');
        $activo = !empty($data['activo']) ? 1 : 0;

        if ($id <= 0 || $nombre === '') {
            throw new Exception("Datos inválidos para actualizar");
        }

        $stmt = $conn->prepare("UPDATE almacenes SET nombre = ?, codigo = ?, activo = ? WHERE id = ?");
        $stmt->bind_param("ssii", $nombre, $codigo, $activo, $id);
        $stmt->execute();
        $stmt->close();

        Auditoria::registrar(
            $conn,
            'Almacén actualizado: id ' . $id . ' — ' . $nombre . ($codigo !== '' ? ' (' . $codigo . ')' : ''),
            'Almacenes'
        );

        echo json_encode(['success' => true, 'message' => 'Almacén actualizado exitosamente']);
        $conn->close();
        exit;
    }

    if ($action === 'eliminar') {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception("ID inválido para eliminar");
        }

        $stmt = $conn->prepare("DELETE FROM almacenes WHERE id = ?");
        $stmt->bind_param("i", $id);
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception("No se puede eliminar el almacén (puede estar en uso en inventario u otras tablas).");
        }
        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new Exception("Almacén no encontrado");
        }
        $stmt->close();

        Auditoria::registrar($conn, 'Almacén eliminado: id ' . $id, 'Almacenes');

        echo json_encode(['success' => true, 'message' => 'Almacén eliminado']);
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
        echo '<tr><td colspan="5" class="text-center text-danger">' . htmlspecialchars($e->getMessage()) . '</td></tr>';
    }
}
