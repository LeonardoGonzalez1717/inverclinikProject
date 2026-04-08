<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        // Verificar si existe la columna imagen, si no, agregarla
        $checkColumn = $conn->query("SHOW COLUMNS FROM productos LIKE 'imagen'");
        if ($checkColumn->num_rows == 0) {
            $conn->query("ALTER TABLE productos ADD COLUMN imagen VARCHAR(255) NULL AFTER descripcion");
        }
        
        $sql = "
            SELECT 
                id,
                nombre,
                categoria,
                tipo_genero,
                descripcion,
                imagen,
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
        $i = 0;
        if (!empty($productos)) {
            foreach ($productos as $p) {
                $i++;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
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

    restringirEscritura();

    header('Content-Type: application/json');

    // Verificar si existe la columna imagen, si no, agregarla
    $checkColumn = $conn->query("SHOW COLUMNS FROM productos LIKE 'imagen'");
    if ($checkColumn->num_rows == 0) {
        $conn->query("ALTER TABLE productos ADD COLUMN imagen VARCHAR(255) NULL AFTER descripcion");
    }
    
    // Crear directorio para imágenes si no existe
    $uploadDir = '../assets/img/productos/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    switch ($action) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $categoria = trim($_POST['categoria'] ?? '');
            $tipo_genero = trim($_POST['tipo_genero'] ?? '');
            $descripcion = trim($_POST['descripcion'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;
            $imagen = null;

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

            // Manejar subida de imagen
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['imagen'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception("Tipo de archivo no permitido. Solo se permiten JPG, PNG y GIF.");
                }
                
                if ($file['size'] > $maxSize) {
                    throw new Exception("El archivo es demasiado grande. Tamaño máximo: 5MB.");
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = 'producto_' . time() . '_' . uniqid() . '.' . $extension;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $imagen = $newFileName;
                } else {
                    throw new Exception("Error al subir la imagen.");
                }
            }

            $stmt = $conn->prepare("
                INSERT INTO productos (nombre, categoria, tipo_genero, descripcion, imagen, activo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");

            $categoria = empty($categoria) ? null : $categoria;
            $tipo_genero = empty($tipo_genero) ? null : $tipo_genero;
            $descripcion = empty($descripcion) ? null : $descripcion;

            $stmt->bind_param("sssssi", $nombre, $categoria, $tipo_genero, $descripcion, $imagen, $activo);
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
            $imagenActual = $_POST['imagen_actual'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del producto es obligatorio");
            }

            $checkSql = "SELECT id, imagen FROM productos WHERE id = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("i", $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $productoActual = $result->fetch_assoc();
            
            if (!$productoActual) {
                throw new Exception("Producto no encontrado");
            }

            $checkSql = "SELECT id FROM productos WHERE nombre = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $nombre, $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otro producto con el nombre: " . $nombre);
            }

            $imagen = $imagenActual; // Mantener la imagen actual por defecto

            // Manejar subida de nueva imagen
            if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['imagen'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if (!in_array($file['type'], $allowedTypes)) {
                    throw new Exception("Tipo de archivo no permitido. Solo se permiten JPG, PNG y GIF.");
                }
                
                if ($file['size'] > $maxSize) {
                    throw new Exception("El archivo es demasiado grande. Tamaño máximo: 5MB.");
                }
                
                // Eliminar imagen anterior si existe
                if ($productoActual['imagen'] && file_exists($uploadDir . $productoActual['imagen'])) {
                    unlink($uploadDir . $productoActual['imagen']);
                }
                
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $newFileName = 'producto_' . time() . '_' . uniqid() . '.' . $extension;
                $uploadPath = $uploadDir . $newFileName;
                
                if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                    $imagen = $newFileName;
                } else {
                    throw new Exception("Error al subir la imagen.");
                }
            }

            $stmt = $conn->prepare("
                UPDATE productos 
                SET nombre = ?, 
                    categoria = ?, 
                    tipo_genero = ?, 
                    descripcion = ?,
                    imagen = ?
                WHERE id = ?
            ");

            $categoria = empty($categoria) ? null : $categoria;
            $tipo_genero = empty($tipo_genero) ? null : $tipo_genero;
            $descripcion = empty($descripcion) ? null : $descripcion;

            $stmt->bind_param("sssssi", $nombre, $categoria, $tipo_genero, $descripcion, $imagen, $id);
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
