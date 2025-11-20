<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                id,
                nombre,
                categoria,
                tipo_genero,
                descripcion,
                fecha_creacion
            FROM productos
            ORDER BY fecha_creacion DESC, nombre ASC
        ";

        $result = $conn->query($sql);
        $productos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $productos[] = $row;
            }
        }

        if (!empty($productos)) {
            foreach ($productos as $p) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($p['id']) . '</td>';
                echo '<td>' . htmlspecialchars($p['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($p['categoria'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($p['tipo_genero'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars(substr($p['descripcion'] ?? '', 0, 50)) . (strlen($p['descripcion'] ?? '') > 50 ? '...' : '') . '</td>';
                echo '<td>' . ($p['fecha_creacion'] ? date('d/m/Y H:i', strtotime($p['fecha_creacion'])) : '-') . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="editarProducto(' . htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') . ')">Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7" class="text-center">No se encontraron productos registrados</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $categoria = trim($_POST['categoria'] ?? '');
            $tipo_genero = trim($_POST['tipo_genero'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del producto es obligatorio");
            }

            $checkSql = "SELECT id FROM productos WHERE nombre = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $nombre);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe un producto con el nombre: " . $nombre);
            }

            $stmt = $conn->prepare("
                INSERT INTO productos (nombre, categoria, tipo_genero, descripcion)
                VALUES (?, ?, ?, ?)
            ");

            $categoria = empty($categoria) ? null : $categoria;
            $tipo_genero = empty($tipo_genero) ? null : $tipo_genero;
            $descripcion = empty($descripcion) ? null : $descripcion;

            $stmt->bind_param("ssss", $nombre, $categoria, $tipo_genero, $descripcion);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Producto creado exitosamente', 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de producto requerido");

            $nombre = trim($_POST['nombre'] ?? '');
            $categoria = trim($_POST['categoria'] ?? '');
            $tipo_genero = trim($_POST['tipo_genero'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');

            if (empty($nombre)) {
                throw new Exception("El nombre del producto es obligatorio");
            }

            $checkSql = "SELECT id FROM productos WHERE nombre = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $nombre, $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otro producto con el nombre: " . $nombre);
            }

            $stmt = $conn->prepare("
                UPDATE productos 
                SET nombre = ?, 
                    categoria = ?, 
                    tipo_genero = ?, 
                    descripcion = ?
                WHERE id = ?
            ");

            $categoria = empty($categoria) ? null : $categoria;
            $tipo_genero = empty($tipo_genero) ? null : $tipo_genero;
            $descripcion = empty($descripcion) ? null : $descripcion;

            $stmt->bind_param("ssssi", $nombre, $categoria, $tipo_genero, $descripcion, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Producto actualizado exitosamente']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="7" class="text-center text-danger">Error al cargar productos</td></tr>';
    }
}

$conn->close();
?>
