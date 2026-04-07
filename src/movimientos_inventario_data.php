<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';

// Inventario usa tipo_item + tipo_item_id (id de insumo o de receta). Ver sql/migracion_inventario_radical.sql
$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

$checkColumn = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'orden_produccion_id'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE inventario_detalle ADD COLUMN orden_produccion_id INT NULL");
    $conn->query("ALTER TABLE inventario_detalle ADD INDEX idx_orden_produccion (orden_produccion_id)");
    try {
        $conn->query("ALTER TABLE inventario_detalle 
                      ADD CONSTRAINT fk_inventario_detalle_orden_produccion 
                      FOREIGN KEY (orden_produccion_id) REFERENCES ordenes_produccion(id) ON DELETE SET NULL");
    } catch (Exception $e) {
    }
}

$checkInsumoAlmacen = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'almacen_id'");
if ($checkInsumoAlmacen->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE inventario_detalle ADD COLUMN almacen_id int(11) DEFAULT NULL AFTER insumo_id");
    } catch (Exception $e) {
    }
}

$checkInsumoMin = $conn->query("SHOW COLUMNS FROM insumos LIKE 'stock_minimo'");
if ($checkInsumoMin->num_rows == 0) {
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

$checkRecetas = $conn->query("SELECT COUNT(*) as count FROM recetas");
$row = $checkRecetas->fetch_assoc();
if ($row['count'] == 0) {
    $sqlInsertRecetas = "
        INSERT IGNORE INTO recetas (producto_id, rango_tallas_id, tipo_produccion_id, observaciones)
        SELECT DISTINCT producto_id, rango_tallas_id, tipo_produccion_id, NULL
        FROM recetas_productos
    ";
    $conn->query($sqlInsertRecetas);
}

/**
 * Stock actual en listados: fondo rojo (inline) si está a ≤5 u. del mín./máx. o fuera de límites.
 */
function mov_inv_html_stock_actual_celda($stock_actual_raw, $stock_minimo_raw, $stock_maximo_raw)
{
    $stock_actual = round((float) ($stock_actual_raw ?? 0), 2);
    $stock_minimo = isset($stock_minimo_raw) && $stock_minimo_raw !== null && $stock_minimo_raw !== '' ? round((float) $stock_minimo_raw, 2) : null;
    $stock_maximo = isset($stock_maximo_raw) && $stock_maximo_raw !== null && $stock_maximo_raw !== '' ? round((float) $stock_maximo_raw, 2) : null;
    if ($stock_maximo !== null && $stock_maximo <= 0) {
        $stock_maximo = null;
    }

    $margen = 5.0;

    $cercaMin = false;
    if ($stock_minimo !== null) {
        if ($stock_actual < $stock_minimo) {
            $cercaMin = true;
        } else {
            $cercaMin = ($stock_actual - $stock_minimo) <= $margen;
        }
    }

    $cercaMax = false;
    if ($stock_maximo !== null) {
        if ($stock_actual > $stock_maximo) {
            $cercaMax = true;
        } else {
            $distAlMax = $stock_maximo - $stock_actual;
            $cercaMax = $distAlMax <= $margen && $distAlMax >= 0;
        }
    }

    $num = number_format($stock_actual, 2, '.', ',');

    if (!$cercaMin && !$cercaMax) {
        return '<strong>' . $num . '</strong>';
    }

    $partes = [];
    if ($cercaMin) {
        $partes[] = 'Stock a punto de alcanzar el mínimo.';
    }
    if ($cercaMax) {
        if ($stock_maximo !== null && $stock_actual > $stock_maximo) {
            $partes[] = 'Stock por encima del máximo configurado.';
        } else {
            $partes[] = 'Stock a punto de alcanzar el máximo.';
        }
    }
    $title = implode(' ', $partes);

    $estilo = 'display:block;width:100%;box-sizing:border-box;margin:-12px;padding:12px;'
        . 'background-color:#f8d7da;color:#721c24;border-left:3px solid #dc3545;cursor:help;';

    return '<span title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '" style="' . $estilo . '"><strong style="color:#721c24;">' . $num . '</strong></span>';
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {
        $tipoInventario = $_POST['tipo_inventario'] ?? 'materia_prima';
        
        if ($tipoInventario === 'materia_prima') {
            if ($tieneInventarioNuevo) {
                // inventario: tipo_item + tipo_item_id (tipo_item_id = id del insumo)
                $sql = "
                    SELECT 
                        inv.tipo_item_id AS insumo_id,
                        i.nombre AS insumo_nombre,
                        i.unidad_medida,
                        a.nombre AS almacen_nombre,
                        inv.stock_actual,
                        i.stock_minimo,
                        i.stock_maximo,
                        DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %H:%i') AS fecha_actualizacion
                    FROM inventario inv
                    INNER JOIN insumos i ON i.id = inv.tipo_item_id AND inv.tipo_item = 'insumo'
                    LEFT JOIN almacenes a ON i.almacen_id = a.id
                    WHERE i.activo = 1
                    ORDER BY i.nombre ASC
                ";
            } else {
                $sql = "
                    SELECT 
                        inv.insumo_id,
                        i.nombre AS insumo_nombre,
                        i.unidad_medida,
                        NULL AS almacen_nombre,
                        inv.stock_actual,
                        i.stock_minimo,
                        i.stock_maximo,
                        DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %H:%i') AS fecha_actualizacion
                    FROM inventario inv
                    INNER JOIN insumos i ON inv.insumo_id = i.id
                    WHERE i.activo = 1
                    ORDER BY i.nombre ASC
                ";
            }

            $result = $conn->query($sql);
            $inventario = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $inventario[] = $row;
                }
            }

            if (!empty($inventario)) {
                $i = 0;
                foreach ($inventario as $inv) {
                    $i++;
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($i) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['fecha_actualizacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['insumo_nombre'] . ' (' . $inv['unidad_medida'] . ')') . '</td>';
                    echo '<td>' . htmlspecialchars($inv['almacen_nombre'] ?? '—') . '</td>';
                    echo '<td>' . mov_inv_html_stock_actual_celda($inv['stock_actual'] ?? 0, $inv['stock_minimo'] ?? null, $inv['stock_maximo'] ?? null) . '</td>';
                    $min = isset($inv['stock_minimo']) && $inv['stock_minimo'] !== null ? number_format((float)$inv['stock_minimo'], 2, '.', ',') : '—';
                    $max = isset($inv['stock_maximo']) && $inv['stock_maximo'] !== null ? number_format((float)$inv['stock_maximo'], 2, '.', ',') : '—';
                    echo '<td>' . $min . '</td>';
                    echo '<td>' . $max . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="7" class="text-center">No hay registros en el inventario de materia prima</td></tr>';
            }
        } else {
            if ($tieneInventarioNuevo) {
                $sql = "
                    SELECT 
                        r.id AS receta_id,
                        r.producto_id,
                        r.rango_tallas_id,
                        r.tipo_produccion_id,
                        p.nombre AS producto_nombre,
                        rt.nombre_rango AS rango_tallas_nombre,
                        tp.nombre AS tipo_produccion_nombre,
                        COALESCE(inv.stock_actual, 0) AS stock_actual,
                        r.stock_minimo,
                        r.stock_maximo,
                        r.almacen_id,
                        a.nombre AS almacen_nombre,
                        CASE 
                            WHEN inv.ultima_actualizacion IS NOT NULL 
                            THEN DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %H:%i')
                            ELSE '-'
                        END AS fecha_actualizacion
                    FROM recetas r
                    INNER JOIN productos p ON r.producto_id = p.id
                    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                    INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
                    LEFT JOIN almacenes a ON r.almacen_id = a.id
                    LEFT JOIN inventario inv ON inv.tipo_item = 'producto' AND inv.tipo_item_id = r.id
                    ORDER BY p.nombre, rt.nombre_rango
                ";
            } else {
                $sql = "
                    SELECT 
                        r.id AS receta_id,
                        r.producto_id,
                        r.rango_tallas_id,
                        r.tipo_produccion_id,
                        p.nombre AS producto_nombre,
                        rt.nombre_rango AS rango_tallas_nombre,
                        tp.nombre AS tipo_produccion_nombre,
                        COALESCE(inv.stock_actual, 0) AS stock_actual,
                        r.stock_minimo,
                        r.stock_maximo,
                        r.almacen_id,
                        a.nombre AS almacen_nombre,
                        CASE WHEN inv.ultima_actualizacion IS NOT NULL THEN DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %H:%i') ELSE '-' END AS fecha_actualizacion
                    FROM recetas r
                    INNER JOIN productos p ON r.producto_id = p.id
                    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                    INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
                    LEFT JOIN almacenes a ON r.almacen_id = a.id
                    LEFT JOIN inventario_productos inv ON r.producto_id = inv.producto_id AND r.rango_tallas_id = inv.rango_tallas_id AND r.tipo_produccion_id = inv.tipo_produccion_id
                    ORDER BY p.nombre, rt.nombre_rango
                ";
            }

            $result = $conn->query($sql);
            $inventario = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $inventario[] = $row;
                }
            }

            if (!empty($inventario)) {
                $i = 0;
                foreach ($inventario as $inv) {
                    $i++;
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($i) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['fecha_actualizacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['producto_nombre']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['rango_tallas_nombre']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['tipo_produccion_nombre']) . '</td>';
                    echo '<td>' . mov_inv_html_stock_actual_celda($inv['stock_actual'] ?? 0, $inv['stock_minimo'] ?? null, $inv['stock_maximo'] ?? null) . '</td>';
                    $minReceta = isset($inv['stock_minimo']) && $inv['stock_minimo'] !== null && $inv['stock_minimo'] !== '' ? number_format((float)$inv['stock_minimo'], 2, '.', ',') : '—';
                    $maxReceta = isset($inv['stock_maximo']) && $inv['stock_maximo'] !== null && $inv['stock_maximo'] !== '' ? number_format((float)$inv['stock_maximo'], 2, '.', ',') : '—';
                    echo '<td>' . htmlspecialchars($minReceta) . '</td>';
                    echo '<td>' . htmlspecialchars($maxReceta) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['almacen_nombre'] ?? '—') . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="9" class="text-center">No hay registros en el inventario de productos</td></tr>';
            }
        }
        $conn->close();
        exit;
    }
    
    if ($action === 'obtener_stock_insumo') {
        $insumo_id = $_POST['insumo_id'] ?? null;
        if (!$insumo_id) {
            echo json_encode(['success' => false, 'message' => 'Insumo requerido']);
            exit;
        }
        $stock_actual = 0;
        if ($tieneInventarioNuevo) {
            $stmt = $conn->prepare("SELECT stock_actual FROM inventario WHERE tipo_item = 'insumo' AND tipo_item_id = ?");
            $stmt->bind_param("i", $insumo_id);
        } else {
            $stmt = $conn->prepare("SELECT stock_actual FROM inventario WHERE insumo_id = ?");
            $stmt->bind_param("i", $insumo_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $stock_actual = floatval($row['stock_actual']);
        }
        $stmt->close();
        echo json_encode(['success' => true, 'stock_actual' => $stock_actual]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_stock_producto') {
        $receta_id = $_POST['receta_id'] ?? null;
        if ($tieneInventarioNuevo && $receta_id) {
            $stmt = $conn->prepare("SELECT stock_actual FROM inventario WHERE tipo_item = 'producto' AND tipo_item_id = ?");
            $stmt->bind_param("i", $receta_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $stock_actual = 0;
            if ($row = $result->fetch_assoc()) {
                $stock_actual = floatval($row['stock_actual']);
            }
            $stmt->close();
            echo json_encode(['success' => true, 'stock_actual' => $stock_actual]);
            $conn->close();
            exit;
        }
        $producto_id = $_POST['producto_id'] ?? null;
        $rango_tallas_id = $_POST['rango_tallas_id'] ?? null;
        $tipo_produccion_id = $_POST['tipo_produccion_id'] ?? null;
        if (!$producto_id || !$rango_tallas_id || !$tipo_produccion_id) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            exit;
        }
        $stmt = $conn->prepare("SELECT stock_actual FROM inventario_productos WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?");
        $stmt->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stock_actual = 0;
        if ($row = $result->fetch_assoc()) {
            $stock_actual = floatval($row['stock_actual']);
        }
        $stmt->close();
        echo json_encode(['success' => true, 'stock_actual' => $stock_actual]);
        $conn->close();
        exit;
    }

    if ($action === 'prevalidar_stock_max_movimiento') {
        header('Content-Type: application/json');
        $tipo_inventario = $_POST['tipo_inventario'] ?? '';
        $insumo_id = isset($_POST['insumo_id']) ? (int) $_POST['insumo_id'] : 0;
        $receta_id = isset($_POST['receta_id']) ? (int) $_POST['receta_id'] : 0;
        $tipo = $_POST['tipo'] ?? '';
        $cantidad = floatval($_POST['cantidad'] ?? 0);

        $supera_maximo = false;
        if ($tipo === 'entrada' && $cantidad > 0) {
            if ($tipo_inventario === 'materia_prima' && $insumo_id > 0) {
                $stmt = $conn->prepare('SELECT stock_maximo FROM insumos WHERE id = ? AND activo = 1 LIMIT 1');
                $stmt->bind_param('i', $insumo_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && array_key_exists('stock_maximo', $row) && $row['stock_maximo'] !== null && $row['stock_maximo'] !== '') {
                    $stockMax = (float) $row['stock_maximo'];
                    $stockActual = 0;
                    if ($tieneInventarioNuevo) {
                        $stmtS = $conn->prepare("SELECT stock_actual FROM inventario WHERE tipo_item = 'insumo' AND tipo_item_id = ?");
                    } else {
                        $stmtS = $conn->prepare('SELECT stock_actual FROM inventario WHERE insumo_id = ?');
                    }
                    $stmtS->bind_param('i', $insumo_id);
                    $stmtS->execute();
                    $rs = $stmtS->get_result();
                    if ($rS = $rs->fetch_assoc()) {
                        $stockActual = (float) $rS['stock_actual'];
                    }
                    $stmtS->close();
                    if (($stockActual + $cantidad) > $stockMax) {
                        $supera_maximo = true;
                    }
                }
            } elseif ($tipo_inventario === 'productos' && $receta_id > 0) {
                $stmt = $conn->prepare('SELECT stock_maximo, producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ? LIMIT 1');
                $stmt->bind_param('i', $receta_id);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($row && array_key_exists('stock_maximo', $row) && $row['stock_maximo'] !== null && $row['stock_maximo'] !== '') {
                    $stockMax = (float) $row['stock_maximo'];
                    $stockActual = 0;
                    if ($tieneInventarioNuevo) {
                        $stmtS = $conn->prepare("SELECT stock_actual FROM inventario WHERE tipo_item = 'producto' AND tipo_item_id = ?");
                        $stmtS->bind_param('i', $receta_id);
                    } else {
                        $pid = (int) $row['producto_id'];
                        $rtid = (int) $row['rango_tallas_id'];
                        $tpid = (int) $row['tipo_produccion_id'];
                        $stmtS = $conn->prepare('SELECT stock_actual FROM inventario_productos WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?');
                        $stmtS->bind_param('iii', $pid, $rtid, $tpid);
                    }
                    $stmtS->execute();
                    $rs = $stmtS->get_result();
                    if ($rS = $rs->fetch_assoc()) {
                        $stockActual = (float) $rS['stock_actual'];
                    }
                    $stmtS->close();
                    if (($stockActual + $cantidad) > $stockMax) {
                        $supera_maximo = true;
                    }
                }
            }
        }

        echo json_encode(['success' => true, 'supera_maximo' => $supera_maximo]);
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            $tipo_inventario = $_POST['tipo_inventario'] ?? '';
            $insumo_id = $_POST['insumo_id'] ?? null;
            $receta_id = $_POST['receta_id'] ?? null;
            $tipo = $_POST['tipo'] ?? '';
            $tipo_movimiento = $_POST['tipo_movimiento'] ?? 'manual';
            $cantidad = $_POST['cantidad'] ?? 0;
            $observaciones = $_POST['observaciones'] ?? '';
            
            $tipos_validos = ['compra', 'orden_produccion', 'manual', 'ajuste'];
            if (!in_array($tipo_movimiento, $tipos_validos)) {
                $tipo_movimiento = 'manual';
            }

            if (empty($tipo_inventario)) {
                throw new Exception("El tipo de inventario es obligatorio");
            }

            if ($tipo_inventario === 'materia_prima') {
                if (!$insumo_id) {
                    throw new Exception("El insumo es obligatorio");
                }
            }

            if ($tipo_inventario === 'productos' && !$receta_id) {
                throw new Exception("La receta/producto es obligatoria");
            }

            if (empty($tipo) || !in_array($tipo, ['entrada', 'salida'])) {
                throw new Exception("El tipo de movimiento es obligatorio y debe ser 'entrada' o 'salida'");
            }

            if (empty($cantidad) || $cantidad <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0");
            }

            $conn->begin_transaction();

            try {
                if ($tipo_inventario === 'materia_prima') {
                    $checkInsumo = $conn->prepare("SELECT id FROM insumos WHERE id = ? AND activo = 1");
                    $checkInsumo->bind_param("i", $insumo_id);
                    $checkInsumo->execute();
                    $resultInsumo = $checkInsumo->get_result();
                    if ($resultInsumo->num_rows === 0) {
                        throw new Exception("El insumo seleccionado no existe o está inactivo");
                    }
                    $checkInsumo->close();

                    if ($tieneInventarioNuevo) {
                        $stmtStock = $conn->prepare("SELECT stock_actual FROM inventario WHERE tipo_item = 'insumo' AND tipo_item_id = ?");
                        $stmtStock->bind_param("i", $insumo_id);
                    } else {
                        $stmtStock = $conn->prepare("SELECT stock_actual FROM inventario WHERE insumo_id = ?");
                        $stmtStock->bind_param("i", $insumo_id);
                    }
                    $stmtStock->execute();
                    $resultStock = $stmtStock->get_result();
                    $stockActual = 0;
                    if ($rowStock = $resultStock->fetch_assoc()) {
                        $stockActual = floatval($rowStock['stock_actual']);
                    }
                    $stmtStock->close();

                    if ($tipo === 'entrada') {
                        $nuevoStock = $stockActual + floatval($cantidad);
                    } else {
                        $nuevoStock = $stockActual - floatval($cantidad);
                        if ($nuevoStock < 0) $nuevoStock = 0;
                    }

                    $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, insumo_id, tipo, cantidad, origen, observaciones) VALUES ('insumo', ?, ?, ?, ?, ?)");
                    $stmtMovimiento->bind_param("isdss", $insumo_id, $tipo, $cantidad, $tipo_movimiento, $observaciones);
                    $stmtMovimiento->execute();
                    $stmtMovimiento->close();

                    if ($tieneInventarioNuevo) {
                        $stmtInventario = $conn->prepare("
                            INSERT INTO inventario (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion)
                            VALUES ('insumo', ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), tipo_movimiento = VALUES(tipo_movimiento), ultima_actualizacion = NOW()
                        ");
                        $stmtInventario->bind_param("ids", $insumo_id, $nuevoStock, $tipo_movimiento);
                    } else {
                        $stmtInventario = $conn->prepare("
                            INSERT INTO inventario (insumo_id, stock_actual, ultima_actualizacion)
                            VALUES (?, ?, NOW())
                            ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()
                        ");
                        $stmtInventario->bind_param("id", $insumo_id, $nuevoStock);
                    }
                    $stmtInventario->execute();
                    $stmtInventario->close();

                } else {
                    $sqlReceta = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                    $stmtReceta = $conn->prepare($sqlReceta);
                    $stmtReceta->bind_param("i", $receta_id);
                    $stmtReceta->execute();
                    $resultReceta = $stmtReceta->get_result();
                    if (!$rowReceta = $resultReceta->fetch_assoc()) {
                        throw new Exception("Receta no encontrada");
                    }
                    $stmtReceta->close();
                    $producto_id = $rowReceta['producto_id'];
                    $rango_tallas_id = $rowReceta['rango_tallas_id'];
                    $tipo_produccion_id = $rowReceta['tipo_produccion_id'];

                    if ($tieneInventarioNuevo) {
                        $stmtStock = $conn->prepare("SELECT stock_actual FROM inventario WHERE tipo_item = 'producto' AND tipo_item_id = ?");
                        $stmtStock->bind_param("i", $receta_id);
                    } else {
                        $stmtStock = $conn->prepare("SELECT stock_actual FROM inventario_productos WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?");
                        $stmtStock->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                    }
                    $stmtStock->execute();
                    $resultStock = $stmtStock->get_result();
                    $stockActual = 0;
                    if ($rowStock = $resultStock->fetch_assoc()) {
                        $stockActual = floatval($rowStock['stock_actual']);
                    }
                    $stmtStock->close();

                    if ($tipo === 'entrada') {
                        $nuevoStock = $stockActual + floatval($cantidad);
                    } else {
                        $nuevoStock = $stockActual - floatval($cantidad);
                        if ($nuevoStock < 0) $nuevoStock = 0;
                    }

                    $checkRecetaIdDet = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'receta_id'");
                    if ($checkRecetaIdDet->num_rows > 0) {
                        $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, receta_id, tipo, cantidad, origen, observaciones) VALUES ('producto', ?, ?, ?, ?, ?)");
                        $stmtMovimiento->bind_param("isdss", $receta_id, $tipo, $cantidad, $tipo_movimiento, $observaciones);
                    } else {
                        $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, origen, observaciones) VALUES ('producto', ?, ?, ?, ?, ?, ?, ?)");
                        $stmtMovimiento->bind_param("iiisdss", $producto_id, $rango_tallas_id, $tipo_produccion_id, $tipo, $cantidad, $tipo_movimiento, $observaciones);
                    }
                    $stmtMovimiento->execute();
                    $stmtMovimiento->close();

                    if ($tieneInventarioNuevo) {
                        $stmtInventario = $conn->prepare("
                            INSERT INTO inventario (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion)
                            VALUES ('producto', ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), tipo_movimiento = VALUES(tipo_movimiento), ultima_actualizacion = NOW()
                        ");
                        $stmtInventario->bind_param("ids", $receta_id, $nuevoStock, $tipo_movimiento);
                    } else {
                        $stmtInventario = $conn->prepare("
                            INSERT INTO inventario_productos (producto_id, rango_tallas_id, tipo_produccion_id, stock_actual, ultima_actualizacion)
                            VALUES (?, ?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()
                        ");
                        $stmtInventario->bind_param("iiid", $producto_id, $rango_tallas_id, $tipo_produccion_id, $nuevoStock);
                    }
                    $stmtInventario->execute();
                    $stmtInventario->close();
                }

                $conn->commit();

                $detalleItem = $tipo_inventario === 'materia_prima'
                    ? ('insumo #' . (int) $insumo_id)
                    : ('receta #' . (int) $receta_id);
                Auditoria::registrar(
                    $conn,
                    "Movimiento inventario: {$tipo_inventario} {$tipo} {$detalleItem}. Cantidad: {$cantidad}. Origen: {$tipo_movimiento}. " . trim((string) $observaciones),
                    'Movimientos inventario'
                );

                $tipoTexto = $tipo === 'entrada' ? 'entrada' : 'salida';
                $inventarioTexto = $tipo_inventario === 'materia_prima' ? 'materia prima' : 'productos';
                echo json_encode([
                    'success' => true, 
                    'message' => "Movimiento de {$tipoTexto} de {$inventarioTexto} registrado exitosamente. Stock actualizado."
                ]);

            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="6" class="text-center text-danger">Error al cargar movimientos: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
    }
}

$conn->close();
?>

