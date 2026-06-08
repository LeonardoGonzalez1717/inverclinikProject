<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Pagination.php';

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
        // 1. RECEPCIÓN DE FILTROS
        $buscar_nombre    = isset($_POST['buscar_nombre']) ? trim($_POST['buscar_nombre']) : '';
        $buscar_proveedor = isset($_POST['buscar_proveedor']) ? trim($_POST['buscar_proveedor']) : '';
        $buscar_almacen   = isset($_POST['buscar_almacen']) ? trim($_POST['buscar_almacen']) : '';

        $whereClauses = [];

        if ($buscar_nombre !== '') {
            $searchN = $conn->real_escape_string($buscar_nombre);
            $whereClauses[] = "i.nombre LIKE '%$searchN%'";
        }

        if ($buscar_proveedor !== '') {
            $searchP = $conn->real_escape_string($buscar_proveedor);
            $whereClauses[] = "p.nombre LIKE '%$searchP%'";
        }

        if ($buscar_almacen !== '') {
            $searchA = $conn->real_escape_string($buscar_almacen);
            $whereClauses[] = "a.nombre LIKE '%$searchA%'";
        }

        $whereSql = !empty($whereClauses) ? " WHERE " . implode(" AND ", $whereClauses) : "";

        // 2. CONSULTA BASE CON WHERE DINÁMICO
        $sqlBase = "
            SELECT 
                i.id,
                i.nombre,
                i.unidad_medida_id,
                um.codigo AS unidad_medida,
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
            LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
            LEFT JOIN proveedores p ON i.proveedor_id = p.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = i.tasa_cambiaria_id
            LEFT JOIN almacenes a ON i.almacen_id = a.id
            " . $whereSql . "
        ";

        // Paginación correcta sobre la consulta filtrada
        $total = (int) ($conn->query("SELECT COUNT(*) AS c FROM ($sqlBase) AS t")->fetch_assoc()['c'] ?? 0);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = $sqlBase . " ORDER BY i.nombre ASC " . $pg->limitClause();

        $result = $conn->query($sql);
        $insumos = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $insumos[] = $row;
            }
        }

        ob_start();
        $orden = $pg->rowNumberStart() - 1;
        if (!empty($insumos)) {
            foreach ($insumos as $insRow) {
                $orden++;
                $costo = (float) ($insRow['costo_unitario'] ?? 0);
                $tasa = isset($insRow['tasa_insumo']) && $insRow['tasa_insumo'] !== null ? (float) $insRow['tasa_insumo'] : 0;
                $equivBs = ($tasa > 0 && $costo > 0) ? $costo * $tasa : null;
                $equivBsFormato = $equivBs !== null ? 'Bs. ' . number_format($equivBs, 2, '.', ',') : '-';
                
                $minStr = isset($insRow['stock_minimo']) && $insRow['stock_minimo'] !== null && $insRow['stock_minimo'] !== '' ? number_format((float)$insRow['stock_minimo'], 2, '.', ',') : '—';
                $maxStr = isset($insRow['stock_maximo']) && $insRow['stock_maximo'] !== null && $insRow['stock_maximo'] !== '' ? number_format((float)$insRow['stock_maximo'], 2, '.', ',') : '—';
                $almacenStr = isset($insRow['almacen_nombre']) && $insRow['almacen_nombre'] ? htmlspecialchars($insRow['almacen_nombre']) : '—';
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars((string) $orden) . '</td>';
                echo '<td>' . htmlspecialchars($insRow['nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($insRow['unidad_medida'] ?? '-') . '</td>';
                echo '<td style="text-align: right;">$' . number_format($costo, 2, '.', ',') . '</td>';
                echo '<td style="text-align: right;">' . htmlspecialchars($equivBsFormato) . '</td>';
                echo '<td style="text-align: right;">' . htmlspecialchars($minStr) . '</td>';
                echo '<td style="text-align: right;">' . htmlspecialchars($maxStr) . '</td>';
                
                $baseStyle = 'font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block; text-transform: capitalize; font-size: 13px;';
                
                echo '<td><span >' . $almacenStr . '</span></td>';
                echo '<td>' . htmlspecialchars($insRow['proveedor_nombre'] ?? '-') . '</td>';
                
                [$adiTxt, $adiStyle] = match((int)$insRow['adicional']) {
                    1       => ['Adicional', "background-color: #0dcaf0; color: #000000; {$baseStyle}"],
                    default => ['Regular', "background-color: #6c757d; color: #ffffff; {$baseStyle}"]
                };
                
                echo '<td>';
                echo '  <button class="btn btn-sm btn-primary" onclick="editarInsumo(' . htmlspecialchars(json_encode($insRow), ENT_QUOTES, 'UTF-8') . ')" title="Editar"><i class="fas fa-edit"></i></button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10" class="text-center">No se encontraron insumos registrados</td></tr>';
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
            $adicional_val = !empty($_POST['adicional']) ? 1 : 0;
            $unidad_medida_id = isset($_POST['unidad_medida_id']) ? (int) $_POST['unidad_medida_id'] : 0;
            $costo_unitario = $_POST['costo_unitario'] ?? 0;
            $stock_minimo = isset($_POST['stock_minimo']) && $_POST['stock_minimo'] !== '' ? (float)$_POST['stock_minimo'] : null;
            $stock_maximo = isset($_POST['stock_maximo']) && $_POST['stock_maximo'] !== '' ? (float)$_POST['stock_maximo'] : null;
            $almacen_id = isset($_POST['almacen_id']) && $_POST['almacen_id'] !== '' ? (int)$_POST['almacen_id'] : null;
            $proveedor_id = $_POST['proveedor_id'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del insumo es obligatorio");
            }

            if ($unidad_medida_id <= 0) {
                throw new Exception("La unidad de medida es obligatoria");
            }

            if ($costo_unitario < 0) {
                throw new Exception("El costo unitario debe ser mayor o igual a 0");
            }

            $chkUm = $conn->prepare('SELECT id FROM unidad_medida WHERE id = ?');
            $chkUm->bind_param('i', $unidad_medida_id);
            $chkUm->execute();
            if ($chkUm->get_result()->num_rows === 0) {
                $chkUm->close();
                throw new Exception('Unidad de medida no válida');
            }
            $chkUm->close();

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
                INSERT INTO insumos (nombre, unidad_medida_id, costo_unitario, stock_minimo, stock_maximo, adicional, almacen_id, proveedor_id, tasa_cambiaria_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $proveedor_id = ($proveedor_id === '' || $proveedor_id === null) ? null : (int) $proveedor_id;
            $almacen_id_val = $almacen_id !== null ? $almacen_id : 1;
            $costo_unitario = (float) $costo_unitario;
            $stmt->bind_param(
                "sidddiiii",
                $nombre,
                $unidad_medida_id,
                $costo_unitario,
                $stock_minimo,
                $stock_maximo,
                $adicional_val,
                $almacen_id_val,
                $proveedor_id,
                $tasa_cambiaria_id
            );
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Insumo creado exitosamente', 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de insumo requerido");

            $nombre = trim($_POST['nombre'] ?? '');
            $unidad_medida_id = isset($_POST['unidad_medida_id']) ? (int) $_POST['unidad_medida_id'] : 0;
            $costo_unitario = $_POST['costo_unitario'] ?? 0;
            $stock_minimo = isset($_POST['stock_minimo']) && $_POST['stock_minimo'] !== '' ? (float)$_POST['stock_minimo'] : null;
            $stock_maximo = isset($_POST['stock_maximo']) && $_POST['stock_maximo'] !== '' ? (float)$_POST['stock_maximo'] : null;
            $almacen_id = isset($_POST['almacen_id']) && $_POST['almacen_id'] !== '' ? (int)$_POST['almacen_id'] : null;
            $proveedor_id = $_POST['proveedor_id'] ?? null;

            if (empty($nombre)) {
                throw new Exception("El nombre del insumo es obligatorio");
            }

            if ($unidad_medida_id <= 0) {
                throw new Exception("La unidad de medida es obligatoria");
            }

            $chkUm = $conn->prepare('SELECT id FROM unidad_medida WHERE id = ?');
            $chkUm->bind_param('i', $unidad_medida_id);
            $chkUm->execute();
            if ($chkUm->get_result()->num_rows === 0) {
                $chkUm->close();
                throw new Exception('Unidad de medida no válida');
            }
            $chkUm->close();

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
                    unidad_medida_id = ?, 
                    costo_unitario = ?, 
                    stock_minimo = ?,
                    stock_maximo = ?,
                    almacen_id = ?,
                    proveedor_id = ?,
                    tasa_cambiaria_id = ?,
                    adicional = ?
                WHERE id = ?
            ");

            $adicional_val = isset($_POST['adicional']) ? 1 : 0;

            $proveedor_id = ($proveedor_id === '' || $proveedor_id === null) ? null : (int) $proveedor_id;
            $almacen_id_val = $almacen_id !== null ? $almacen_id : 1;
            $costo_unitario = (float) $costo_unitario;

            $stmt->bind_param(
                "sidddiiiii",
                $nombre,
                $unidad_medida_id,
                $costo_unitario,
                $stock_minimo,
                $stock_maximo,
                $almacen_id_val,
                $proveedor_id,
                $tasa_cambiaria_id,
                $adicional_val,
                $id
            );

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
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>
