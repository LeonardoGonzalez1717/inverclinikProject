<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

// Asegurar columnas stock_minimo/stock_maximo y almacen_id en insumos
$checkInsumoStock = $conn->query("SHOW COLUMNS FROM insumos LIKE 'stock_minimo'");
if ($checkInsumoStock->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE insumos ADD COLUMN stock_minimo decimal(12,2) DEFAULT NULL AFTER costo_unitario");
        $conn->query("ALTER TABLE insumos ADD COLUMN stock_maximo decimal(12,2) DEFAULT NULL AFTER stock_minimo");
    } catch (Exception $e) {}
}
$checkInsumoAlmacen = $conn->query("SHOW COLUMNS FROM insumos LIKE 'almacen_id'");
if ($checkInsumoAlmacen->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE insumos ADD COLUMN almacen_id int(11) DEFAULT 1 COMMENT 'Almacén asociado al insumo' AFTER stock_maximo");
    } catch (Exception $e) {}
}

try {
    if ($action === 'listar_html') {
        $sql = "
            SELECT 
                i.id,
                i.nombre,
                i.unidad_medida,
                i.costo_unitario,
                i.stock_minimo,
                i.stock_maximo,
                i.almacen_id,
                i.proveedor_id,
                i.tasa_cambiaria_id,
                tc.tasa AS tasa_insumo,
                p.nombre AS proveedor_nombre,
                a.nombre AS almacen_nombre,
                i.adicional
            FROM insumos i
            LEFT JOIN proveedores p ON i.proveedor_id = p.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = i.tasa_cambiaria_id
            LEFT JOIN almacenes a ON i.almacen_id = a.id
            ORDER BY i.nombre ASC
        ";

        $result = $conn->query($sql);
        $insumos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $insumos[] = $row;
            }
        }
        $orden = 0;
        if (!empty($insumos)) {
            foreach ($insumos as $i) {
                $orden++;
                $costo = (float) ($i['costo_unitario'] ?? 0);
                $tasa = isset($i['tasa_insumo']) && $i['tasa_insumo'] !== null ? (float) $i['tasa_insumo'] : 0;
                $equivBs = ($tasa > 0 && $costo > 0) ? $costo * $tasa : null;
                $equivBsFormato = $equivBs !== null ? 'Bs. ' . number_format($equivBs, 2, '.', ',') : '-';
                $minStr = isset($i['stock_minimo']) && $i['stock_minimo'] !== null && $i['stock_minimo'] !== '' ? number_format((float)$i['stock_minimo'], 2, '.', ',') : '—';
                $maxStr = isset($i['stock_maximo']) && $i['stock_maximo'] !== null && $i['stock_maximo'] !== '' ? number_format((float)$i['stock_maximo'], 2, '.', ',') : '—';
                $almacenStr = isset($i['almacen_nombre']) && $i['almacen_nombre'] ? htmlspecialchars($i['almacen_nombre']) : '—';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($orden) . '</td>';
                echo '<td>' . htmlspecialchars($i['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($i['unidad_medida'] ?? '-') . '</td>';
                echo '<td>$' . number_format($costo, 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($equivBsFormato) . '</td>';
                echo '<td>' . htmlspecialchars($minStr) . '</td>';
                echo '<td>' . htmlspecialchars($maxStr) . '</td>';
                echo '<td>' . $almacenStr . '</td>';
                echo '<td>' . htmlspecialchars($i['proveedor_nombre'] ?? '-') . '</td>';
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="editarInsumo(' . htmlspecialchars(json_encode($i), ENT_QUOTES, 'UTF-8') . ')">Editar</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10" class="text-center">No se encontraron insumos registrados</td></tr>';
        }
        $conn->close();
        exit;
    }

    restringirEscritura();

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $nombre = trim($_POST['nombre'] ?? '');
            $adicional = trim($_POST['adicional'] ?? '');
            $unidad_medida = trim($_POST['unidad_medida'] ?? '');
            $costo_unitario = $_POST['costo_unitario'] ?? 0;
            $stock_minimo = isset($_POST['stock_minimo']) && $_POST['stock_minimo'] !== '' ? (float)$_POST['stock_minimo'] : null;
            $stock_maximo = isset($_POST['stock_maximo']) && $_POST['stock_maximo'] !== '' ? (float)$_POST['stock_maximo'] : null;
            $almacen_id = isset($_POST['almacen_id']) && $_POST['almacen_id'] !== '' ? (int)$_POST['almacen_id'] : null;
            $proveedor_id = $_POST['proveedor_id'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del insumo es obligatorio");
            }

            if (empty($unidad_medida)) {
                throw new Exception("La unidad de medida es obligatoria");
            }

            if ($costo_unitario < 0) {
                throw new Exception("El costo unitario debe ser mayor o igual a 0");
            }

            $checkSql = "SELECT id FROM insumos WHERE nombre = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("s", $nombre);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe un insumo con el nombre: " . $nombre);
            }

            $tasa_cambiaria_id = null;
            $rt = $conn->query("SELECT id FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
            if ($rt && $row_tasa = $rt->fetch_assoc()) {
                $tasa_cambiaria_id = (int) $row_tasa['id'];
            }

            $stmt = $conn->prepare("
                INSERT INTO insumos (nombre, unidad_medida, costo_unitario, stock_minimo, stock_maximo, adicional, almacen_id, proveedor_id, tasa_cambiaria_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $proveedor_id = empty($proveedor_id) ? null : $proveedor_id;
            $almacen_id_val = $almacen_id !== null ? $almacen_id : 1;
            $stmt->bind_param("ssddiiiii", $nombre, $unidad_medida, $costo_unitario, $stock_minimo, $stock_maximo, $adicional, $almacen_id_val, $proveedor_id, $tasa_cambiaria_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Insumo creado exitosamente', 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de insumo requerido");

            $nombre = trim($_POST['nombre'] ?? '');
            $unidad_medida = trim($_POST['unidad_medida'] ?? '');
            $costo_unitario = $_POST['costo_unitario'] ?? 0;
            $stock_minimo = isset($_POST['stock_minimo']) && $_POST['stock_minimo'] !== '' ? (float)$_POST['stock_minimo'] : null;
            $stock_maximo = isset($_POST['stock_maximo']) && $_POST['stock_maximo'] !== '' ? (float)$_POST['stock_maximo'] : null;
            $almacen_id = isset($_POST['almacen_id']) && $_POST['almacen_id'] !== '' ? (int)$_POST['almacen_id'] : null;
            $proveedor_id = $_POST['proveedor_id'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del insumo es obligatorio");
            }

            if (empty($unidad_medida)) {
                throw new Exception("La unidad de medida es obligatoria");
            }

            if ($costo_unitario < 0) {
                throw new Exception("El costo unitario debe ser mayor o igual a 0");
            }

            $checkSql = "SELECT id FROM insumos WHERE nombre = ? AND id != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("si", $nombre, $id);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            
            if ($result->num_rows > 0) {
                throw new Exception("Ya existe otro insumo con el nombre: " . $nombre);
            }

            $tasa_cambiaria_id = null;
            $rt = $conn->query("SELECT id FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
            if ($rt && $row_tasa = $rt->fetch_assoc()) {
                $tasa_cambiaria_id = (int) $row_tasa['id'];
            }

           $stmt = $conn->prepare("
                UPDATE insumos 
                SET nombre = ?, 
                    unidad_medida = ?, 
                    costo_unitario = ?, 
                    stock_minimo = ?,
                    stock_maximo = ?,
                    almacen_id = ?,
                    proveedor_id = ?,
                    tasa_cambiaria_id = ?,
                    adicional = ?
                WHERE id = ?
            ");

            // Asegúrate de procesar el checkbox antes
            $adicional_val = isset($_POST['adicional']) ? 1 : 0;

            $proveedor_id = empty($proveedor_id) ? null : $proveedor_id;
            $almacen_id_val = $almacen_id !== null ? $almacen_id : 1;

            // "ssddiiiiii" ahora tiene 10 letras para 10 variables
            $stmt->bind_param("ssddiiiiii", $nombre, $unidad_medida, $costo_unitario, $stock_minimo, $stock_maximo, $almacen_id_val, $proveedor_id, 
                $tasa_cambiaria_id, $adicional_val, $id);

            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Insumo actualizado exitosamente']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="10" class="text-center text-danger">Error al cargar insumos</td></tr>';
    }
}

$conn->close();
?>
