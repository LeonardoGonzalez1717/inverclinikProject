<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/Pagination.php';

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $checkColumn = $conn->query("SHOW COLUMNS FROM productos LIKE 'imagen'");
        if ($checkColumn->num_rows == 0) {
            $conn->query("ALTER TABLE productos ADD COLUMN imagen VARCHAR(255) NULL AFTER descripcion");
        }
        
        $buscar_nombre    = isset($_POST['buscar_nombre']) ? trim($_POST['buscar_nombre']) : '';
        $buscar_categoria = isset($_POST['buscar_categoria']) ? trim($_POST['buscar_categoria']) : '';
        $buscar_genero    = isset($_POST['buscar_genero']) ? trim($_POST['buscar_genero']) : '';

        $where = [];

        if ($buscar_nombre !== '') {
            $searchN = $conn->real_escape_string($buscar_nombre);
            $where[] = "nombre LIKE '%$searchN%'";
        }

        if ($buscar_categoria !== '') {
            $searchC = $conn->real_escape_string($buscar_categoria);
            $where[] = "categoria LIKE '%$searchC%'";
        }

        if ($buscar_genero !== '') {
            $searchG = $conn->real_escape_string($buscar_genero);
            $where[] = "tipo_genero = '$searchG'";
        }

        $fil = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sqlBase = "
            SELECT 
                id,
                nombre,
                categoria,
                tipo_genero,
                descripcion,
                imagen,
                fecha_creacion
            FROM productos
            " . $fil . "
        ";

        $total = (int) ($conn->query("SELECT COUNT(*) AS c FROM ($sqlBase) AS t")->fetch_assoc()['c'] ?? 0);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = $sqlBase . " ORDER BY fecha_creacion DESC, nombre ASC " . $pg->limitClause();

        $result = $conn->query($sql);
        $productos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $productos[] = $row;
            }
        }
        ob_start();
        $i = $pg->rowNumberStart() - 1;
        if (!empty($productos)) {
            foreach ($productos as $p) {
                $i++;
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td><strong>' . htmlspecialchars($p['nombre']) . '</strong></td>';
                
                $badgeBase = 'font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block; font-size: 12px;';
                echo '<td><span >' . htmlspecialchars($p['categoria'] ?? '-') . '</span></td>';
                
                $genero = $p['tipo_genero'] ?? '';
                $generoStyle = match(strtolower($genero)) {
                    'masculino' => "background-color: #cfe2ff; color: #0a58ca; border: 1px solid #b6d4fe; {$badgeBase}",
                    'femenino'  => "background-color: #f8d7da; color: #a51d24; border: 1px solid #f5c2c7; {$badgeBase}",
                    'unisex'    => "background-color: #d1e7dd; color: #0f5132; border: 1px solid #badbcc; {$badgeBase}",
                    default     => "background-color: #f8f9fa; color: #6c757d; border: 1px solid #dee2e6; {$badgeBase}"
                };
                echo '<td><span style="' . $generoStyle . '">' . htmlspecialchars($genero ? $genero : '-') . '</span></td>';
                
                $descCorta = htmlspecialchars(substr($p['descripcion'] ?? '', 0, 50));
                if (strlen($p['descripcion'] ?? '') > 50) { $descCorta .= '...'; }
                echo '<td><span class="text-muted">' . $descCorta . '</span></td>';
                
                echo '<td>' . ($p['fecha_creacion'] ? date('d/m/Y h:i A', strtotime($p['fecha_creacion'])) : '-') . '</td>';
                echo '<td>';
                echo '  <button class="btn btn-sm btn-primary" onclick="editarProducto(' . htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') . ')"><i class="fas fa-edit"></i> Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7" class="text-center">No se encontraron productos registrados</td></tr>';
        }
        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
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
            $pid = (int) $conn->insert_id;
            $stmt->close();
            Auditoria::registrar(
                $conn,
                'Producto creado: id ' . $pid . ' — ' . $nombre . ($categoria ? ' (cat. ' . $categoria . ')' : ''),
                'Productos'
            );
            echo json_encode(['success' => true, 'message' => 'Producto creado exitosamente', 'id' => $pid]);
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
            $stmt->close();
            Auditoria::registrar(
                $conn,
                'Producto actualizado: id ' . (int) $id . ' — ' . $nombre,
                'Productos'
            );
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
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>
