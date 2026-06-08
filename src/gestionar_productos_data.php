<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/Pagination.php';

$action = $_POST['action'] ?? '';

function ensureRangoTallasEnProductos(mysqli $conn): void
{
    $check = $conn->query("SHOW COLUMNS FROM productos LIKE 'rango_tallas_id'");
    if ($check && $check->num_rows === 0) {
        $conn->query(
            'ALTER TABLE productos ADD COLUMN rango_tallas_id int(11) DEFAULT NULL AFTER tipo_genero'
        );
        $idx = $conn->query("SHOW INDEX FROM productos WHERE Key_name = 'rango_tallas_id'");
        if (!$idx || $idx->num_rows === 0) {
            $conn->query('ALTER TABLE productos ADD KEY rango_tallas_id (rango_tallas_id)');
        }
    }

    $conn->query(
        'UPDATE productos p
         INNER JOIN (
            SELECT producto_id, MIN(rango_tallas_id) AS rango_tallas_id
            FROM recetas
            GROUP BY producto_id
         ) r ON r.producto_id = p.id
         SET p.rango_tallas_id = r.rango_tallas_id
         WHERE p.rango_tallas_id IS NULL'
    );
}

function sincronizarRangoEnRecetas(mysqli $conn, int $productoId, int $rangoTallasId): void
{
    $stmtR = $conn->prepare('UPDATE recetas SET rango_tallas_id = ? WHERE producto_id = ?');
    $stmtR->bind_param('ii', $rangoTallasId, $productoId);
    $stmtR->execute();
    $stmtR->close();

    $stmtRp = $conn->prepare('UPDATE recetas_productos SET rango_tallas_id = ? WHERE producto_id = ?');
    $stmtRp->bind_param('ii', $rangoTallasId, $productoId);
    $stmtRp->execute();
    $stmtRp->close();
}

ensureRangoTallasEnProductos($conn);

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
                p.id,
                p.nombre,
                p.categoria,
                p.tipo_genero,
                p.rango_tallas_id,
                rt.nombre_rango AS rango_tallas_nombre,
                p.descripcion,
                p.imagen,
                p.fecha_creacion
            FROM productos p
            LEFT JOIN rangos_tallas rt ON rt.id = p.rango_tallas_id
            ORDER BY p.fecha_creacion DESC, p.nombre ASC
        " . $pg->limitClause();

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
                echo '<td>' . htmlspecialchars($p['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($p['categoria'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($p['tipo_genero'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($p['rango_tallas_nombre'] ?? '—') . '</td>';
                echo '<td>' . htmlspecialchars(substr($p['descripcion'] ?? '', 0, 50)) . (strlen($p['descripcion'] ?? '') > 50 ? '...' : '') . '</td>';
                echo '<td>' . ($p['fecha_creacion'] ? date('d/m/Y H:i', strtotime($p['fecha_creacion'])) : '-') . '</td>';
                echo '<td>';
                echo '  <button class="btn btn-sm btn-primary" onclick="editarProducto(' . htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8') . ')"><i class="fas fa-edit"></i> Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8" class="text-center">No se encontraron productos registrados</td></tr>';
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
            $rango_tallas_id = (int) ($_POST['rango_tallas_id'] ?? 0);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $activo = isset($_POST['activo']) ? 1 : 0;
            $imagen = null;

            if (empty($nombre)) {
                throw new Exception("El nombre del producto es obligatorio");
            }

            if ($rango_tallas_id <= 0) {
                throw new Exception('Debe seleccionar un rango de tallas para el producto');
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
                INSERT INTO productos (nombre, categoria, tipo_genero, rango_tallas_id, descripcion, imagen, activo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $categoria = empty($categoria) ? null : $categoria;
            $tipo_genero = empty($tipo_genero) ? null : $tipo_genero;
            $descripcion = empty($descripcion) ? null : $descripcion;

            $stmt->bind_param("sssissi", $nombre, $categoria, $tipo_genero, $rango_tallas_id, $descripcion, $imagen, $activo);
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
            $rango_tallas_id = (int) ($_POST['rango_tallas_id'] ?? 0);
            $descripcion = trim($_POST['descripcion'] ?? '');
            $imagenActual = $_POST['imagen_actual'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del producto es obligatorio");
            }

            if ($rango_tallas_id <= 0) {
                throw new Exception('Debe seleccionar un rango de tallas para el producto');
            }

            $checkSql = "SELECT id, imagen, rango_tallas_id FROM productos WHERE id = ?";
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
                    rango_tallas_id = ?,
                    descripcion = ?,
                    imagen = ?
                WHERE id = ?
            ");

            $categoria = empty($categoria) ? null : $categoria;
            $tipo_genero = empty($tipo_genero) ? null : $tipo_genero;
            $descripcion = empty($descripcion) ? null : $descripcion;

            $stmt->bind_param("sssissi", $nombre, $categoria, $tipo_genero, $rango_tallas_id, $descripcion, $imagen, $id);
            $stmt->execute();
            $stmt->close();

            if ((int) ($productoActual['rango_tallas_id'] ?? 0) !== $rango_tallas_id) {
                sincronizarRangoEnRecetas($conn, (int) $id, $rango_tallas_id);
            }
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
