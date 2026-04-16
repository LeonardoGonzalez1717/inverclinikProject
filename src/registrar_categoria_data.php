<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';

// Evitar que warnings rompan el JSON
error_reporting(E_ERROR | E_PARSE);

$rawIn = file_get_contents('php://input');
$data = $_POST;
if ($rawIn !== '') {
    $parsed = json_decode($rawIn, true);
    if (is_array($parsed)) {
        $data = array_merge($data, $parsed);
    }
}
$action = $data['action'] ?? '';

try {
    // Listado de categorías en HTML
    if ($action === 'listar_html') {
        $sql = "SELECT id, nombre, descripcion FROM categorias ORDER BY id DESC";
        $result = $conn->query($sql);

        if ($result) {
            $i = 0;
            while ($row = $result->fetch_assoc()) {
                $i++;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($row['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($row['descripcion']) . '</td>';
                $onclickEdit = 'editarCategoria('
                    . (int) $row['id'] . ', '
                    . json_encode($row['nombre'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ', '
                    . json_encode($row['descripcion'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE)
                    . ')';
                echo '<td>'
                    . '<button type="button" class="btn btn-sm btn-primary" onclick=\'' . $onclickEdit . '\'>Editar</button> '
                    . '<button type="button" class="btn btn-sm btn-danger" onclick="eliminarCategoria(' . (int) $row['id'] . ')">Eliminar</button>'
                    . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="4" class="text-center">No se encontraron categorías</td></tr>';
        }
        $conn->close();
        exit;
    }

    // Crear nueva categoría
    if ($action === 'crear') {
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');

        if (!$nombre) {
            throw new Exception("El nombre de la categoría es obligatorio");
        }

        $stmt = $conn->prepare("INSERT INTO categorias (nombre, descripcion) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $descripcion);
        $stmt->execute();
        $newId = $conn->insert_id;
        $stmt->close();

        Auditoria::registrar(
            $conn,
            'Categoría creada: id ' . (int) $newId . ' — ' . $nombre,
            'Categorías'
        );

        echo json_encode(['success' => true, 'message' => 'Categoría registrada exitosamente']);
        $conn->close();
        exit;
    }

    // Editar categoría
    if ($action === 'editar') {
        $id = intval($data['id'] ?? 0);
        $nombre = trim($data['nombre'] ?? '');
        $descripcion = trim($data['descripcion'] ?? '');

        if (!$id || !$nombre) {
            throw new Exception("Datos inválidos para actualizar");
        }

        $stmt = $conn->prepare("UPDATE categorias SET nombre = ?, descripcion = ? WHERE id = ?");
        $stmt->bind_param("ssi", $nombre, $descripcion, $id);
        $stmt->execute();
        $stmt->close();

        Auditoria::registrar(
            $conn,
            'Categoría actualizada: id ' . $id . ' — ' . $nombre,
            'Categorías'
        );

        echo json_encode(['success' => true, 'message' => 'Categoría actualizada exitosamente']);
        $conn->close();
        exit;
    }

    // Eliminar categoría
    if ($action === 'eliminar') {
        $id = intval($data['id'] ?? 0);
        if (!$id) throw new Exception("ID inválido para eliminar");

        $stmt = $conn->prepare("DELETE FROM categorias WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        Auditoria::registrar($conn, 'Categoría eliminada: id ' . $id, 'Categorías');

        echo json_encode(['success' => true, 'message' => 'Categoría eliminada']);
        $conn->close();
        exit;
    }

    throw new Exception("Acción no válida");

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>