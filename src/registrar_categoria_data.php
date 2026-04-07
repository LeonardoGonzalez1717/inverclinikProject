<?php
require_once "../connection/connection.php";

// Evitar que warnings rompan el JSON
error_reporting(E_ERROR | E_PARSE);

$action = $_POST['action'] ?? '';

if (empty($action)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    $action = $data['action'] ?? '';
}

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
                echo '<td>
                        <button class="btn btn-sm btn-primary" onclick="editarCategoria(' . $row['id'] . ')">Editar</button>
                        <button class="btn btn-sm btn-danger" onclick="eliminarCategoria(' . $row['id'] . ')">Eliminar</button>
                      </td>';
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
        $stmt->close();

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