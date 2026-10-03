<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/InventarioLotes.php';

// Inventario usa tipo_item + tipo_item_id (id de insumo o de receta).
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
                $countSql = "SELECT COUNT(*) AS c FROM inventario inv
                    INNER JOIN insumos i ON i.id = inv.tipo_item_id AND inv.tipo_item = 'insumo'
                    WHERE i.activo = 1";
                $sqlBody = "
                    SELECT 
                        inv.tipo_item_id AS insumo_id,
                        i.nombre AS insumo_nombre,
                        COALESCE(um.codigo, '') AS unidad_medida,
                        a.nombre AS almacen_nombre,
                        inv.stock_actual,
                        i.stock_minimo,
                        i.stock_maximo,
                        DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %h:%i %p') AS fecha_actualizacion
                    FROM inventario inv
                    INNER JOIN insumos i ON i.id = inv.tipo_item_id AND inv.tipo_item = 'insumo'
                    LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
                    LEFT JOIN almacenes a ON i.almacen_id = a.id
                    WHERE i.activo = 1";
                $orderBy = ' ORDER BY i.nombre ASC';
            } else {
                $countSql = "SELECT COUNT(*) AS c FROM inventario inv
                    INNER JOIN insumos i ON inv.insumo_id = i.id
                    WHERE i.activo = 1";
                $sqlBody = "
                    SELECT 
                        inv.insumo_id,
                        i.nombre AS insumo_nombre,
                        COALESCE(um.codigo, '') AS unidad_medida,
                        NULL AS almacen_nombre,
                        inv.stock_actual,
                        i.stock_minimo,
                        i.stock_maximo,
                        DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %h:%i %p') AS fecha_actualizacion
                    FROM inventario inv
                    INNER JOIN insumos i ON inv.insumo_id = i.id
                    LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
                    WHERE i.activo = 1";
                $orderBy = ' ORDER BY i.nombre ASC';
            }

            $total = (int) ($conn->query($countSql)->fetch_assoc()['c'] ?? 0);
            $pg = Pagination::fromInput($total, $_POST);
            $sql = $sqlBody . $orderBy . $pg->limitClause();

            $result = $conn->query($sql);
            $inventario = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $inventario[] = $row;
                }
            }

            ob_start();
            $i = $pg->rowNumberStart() - 1;
            if (!empty($inventario)) {
                foreach ($inventario as $inv) {
                    $i++;
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string) $i) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['fecha_actualizacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['insumo_nombre'] . ' (' . $inv['unidad_medida'] . ')') . '</td>';
                    echo '<td>' . htmlspecialchars($inv['almacen_nombre'] ?? '—') . '</td>';
                    echo '<td style="text-align: right;">' . mov_inv_html_stock_actual_celda($inv['stock_actual'] ?? 0, $inv['stock_minimo'] ?? null, $inv['stock_maximo'] ?? null) . '</td>';
                    $min = isset($inv['stock_minimo']) && $inv['stock_minimo'] !== null ? number_format((float) $inv['stock_minimo'], 2, '.', ',') : '—';
                    $max = isset($inv['stock_maximo']) && $inv['stock_maximo'] !== null ? number_format((float) $inv['stock_maximo'], 2, '.', ',') : '—';
                    echo '<td style="text-align: right;">' . $min . '</td>';
                    echo '<td style="text-align: right;">' . $max . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="7" class="text-center">No hay registros en el inventario de materia prima</td></tr>';
            }
        } elseif ($tipoInventario === 'material_rechazado') {
            require_once __DIR__ . '/../lib/DevolucionesSchema.php';
            DevolucionesSchema::asegurarTablas($conn);

            $countSql = "
                SELECT COUNT(DISTINCT d.id) AS c
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p ON p.id = rp.producto_id
            ";
            $total = (int) ($conn->query($countSql)->fetch_assoc()['c'] ?? 0);
            $pg = Pagination::fromInput($total, $_POST);

            $sql = "
                SELECT d.id, d.codigo_devolucion, d.fecha, d.motivo, d.descripcion_motivo,
                       d.accion_inventario, COALESCE(d.estado, 'pendiente') AS estado,
                       d.fecha_inspeccion, d.usuario_inspeccion_id, d.motivo_inspeccion,
                       c.id AS cliente_id, c.nombre AS cliente_nombre,
                       dd.id AS detalle_id, dd.orden_produccion_id, COALESCE(dd.cantidad, 1.00) AS cantidad,
                       p.id AS producto_id, p.nombre AS producto_nombre,
                       COALESCE(t.nombre, 'Única') AS talla_nombre,
                       op.creado_en AS orden_creado_en
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
                LEFT JOIN clientes c ON c.id = d.cliente_id
                GROUP BY d.id
                ORDER BY 
                    CASE WHEN COALESCE(d.estado, 'pendiente') = 'pendiente' THEN 0 ELSE 1 END ASC,
                    d.fecha DESC, d.id DESC
                " . $pg->limitClause();

            $result = $conn->query($sql);
            $devoluciones = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    if (!empty($row['orden_produccion_id'])) {
                        $row['numero_orden'] = numero_orden_produccion((int)$row['orden_produccion_id'], $row['orden_creado_en']);
                    } else {
                        $row['numero_orden'] = '-';
                    }
                    $devoluciones[] = $row;
                }
            }

            ob_start();
            $i = $pg->rowNumberStart() - 1;
            if (!empty($devoluciones)) {
                foreach ($devoluciones as $dev) {
                    $i++;
                    $estado = $dev['estado'] ?? 'pendiente';
                    $cant = number_format((float)($dev['cantidad'] ?? 1), 2, '.', ',');
                    $fec = !empty($dev['fecha']) ? date('d/m/Y h:i A', strtotime($dev['fecha'])) : '—';

                    $badgeEstado = '';
                    $acciones = '';

                    if ($estado === 'pendiente') {
                        $badgeEstado = '<span class="label label-warning" style="background-color: #f59e0b; color: white; padding: 4px 8px; font-size: 12px;"><i class="fas fa-clock"></i> Pendiente</span>';
                        $acciones = '<span class="text-warning" style="font-weight: 600; font-size: 12px;"><i class="fas fa-hourglass-half"></i> Pendiente de Inspección</span>';
                    } elseif ($estado === 'aprobado') {
                        $badgeEstado = '<span class="label label-success" style="background-color: #10b981; color: white; padding: 4px 8px; font-size: 12px;"><i class="fas fa-check-circle"></i> Buen Estado</span>';
                        $acciones = '<span class="text-success" style="font-weight: 600; font-size: 12px;"><i class="fas fa-check-double"></i> Reingresado al Stock</span>';
                    } else {
                        $badgeEstado = '<span class="label label-danger" style="background-color: #ef4444; color: white; padding: 4px 8px; font-size: 12px;"><i class="fas fa-ban"></i> Rechazado</span>';
                        $motivoRech = htmlspecialchars($dev['motivo_inspeccion'] ?? 'Material descartado', ENT_QUOTES, 'UTF-8');
                        $acciones = '<span class="text-danger" style="font-weight: 600; font-size: 12px;" title="' . $motivoRech . '"><i class="fas fa-times"></i> Descartado (Sin Stock)</span>';
                    }

                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string) $i) . '</td>';
                    echo '<td>' . htmlspecialchars($fec) . '</td>';
                    echo '<td><span class="badge" style="background-color: #f1f5f9; color: #0f172a; border: 1px solid #cbd5e1; font-family: monospace; font-size: 12px; padding: 4px 6px;">' . htmlspecialchars($dev['codigo_devolucion']) . '</span></td>';
                    echo '<td><strong style="color: #0056b3;">' . htmlspecialchars($dev['numero_orden']) . '</strong></td>';
                    echo '<td><strong>' . htmlspecialchars($dev['producto_nombre']) . '</strong> <small class="text-muted">(' . htmlspecialchars($dev['talla_nombre']) . ')</small></td>';
                    echo '<td style="text-align: right;"><strong>' . $cant . '</strong></td>';
                    echo '<td>' . htmlspecialchars($dev['cliente_nombre'] ?? 'Anónimo') . '</td>';
                    echo '<td style="max-width: 180px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="' . htmlspecialchars($dev['motivo'] ?? '') . '">' . htmlspecialchars($dev['motivo'] ?? '—') . '</td>';
                    echo '<td style="text-align: center;">' . $badgeEstado . '</td>';
                    echo '<td style="text-align: center; white-space: nowrap;">' . $acciones . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="10" class="text-center">No hay registros de devoluciones en material rechazado</td></tr>';
            }
        } else {
            if ($tieneInventarioNuevo) {
                $countSql = "
                    SELECT COUNT(*) AS c
                    FROM recetas r
                    INNER JOIN productos p ON r.producto_id = p.id
                    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                    INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
                    LEFT JOIN almacenes a ON r.almacen_id = a.id
                    LEFT JOIN inventario inv ON inv.tipo_item = 'producto' AND inv.tipo_item_id = r.id";
                $sqlBody = "
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
                            THEN DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %h:%i %p')
                            ELSE '-'
                        END AS fecha_actualizacion
                    FROM recetas r
                    INNER JOIN productos p ON r.producto_id = p.id
                    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                    INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
                    LEFT JOIN almacenes a ON r.almacen_id = a.id
                    LEFT JOIN inventario inv ON inv.tipo_item = 'producto' AND inv.tipo_item_id = r.id";
                $orderBy = ' ORDER BY p.nombre, rt.nombre_rango';
            } else {
                $countSql = "
                    SELECT COUNT(*) AS c
                    FROM recetas r
                    INNER JOIN productos p ON r.producto_id = p.id
                    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                    INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
                    LEFT JOIN almacenes a ON r.almacen_id = a.id
                    LEFT JOIN inventario_productos inv ON r.producto_id = inv.producto_id AND r.rango_tallas_id = inv.rango_tallas_id AND r.tipo_produccion_id = inv.tipo_produccion_id";
                $sqlBody = "
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
                        CASE WHEN inv.ultima_actualizacion IS NOT NULL THEN DATE_FORMAT(inv.ultima_actualizacion, '%d/%m/%Y %h:%i %p') ELSE '-' END AS fecha_actualizacion
                    FROM recetas r
                    INNER JOIN productos p ON r.producto_id = p.id
                    INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                    INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
                    LEFT JOIN almacenes a ON r.almacen_id = a.id
                    LEFT JOIN inventario_productos inv ON r.producto_id = inv.producto_id AND r.rango_tallas_id = inv.rango_tallas_id AND r.tipo_produccion_id = inv.tipo_produccion_id";
                $orderBy = ' ORDER BY p.nombre, rt.nombre_rango';
            }

            $total = (int) ($conn->query($countSql)->fetch_assoc()['c'] ?? 0);
            $pg = Pagination::fromInput($total, $_POST);
            $sql = $sqlBody . $orderBy . $pg->limitClause();

            $result = $conn->query($sql);
            $inventario = [];
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $inventario[] = $row;
                }
            }

            ob_start();
            $i = $pg->rowNumberStart() - 1;
            if (!empty($inventario)) {
                foreach ($inventario as $inv) {
                    $i++;
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string) $i) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['fecha_actualizacion']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['producto_nombre']) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['rango_tallas_nombre']) . '</td>';
                    // echo '<td>' . htmlspecialchars($inv['tipo_produccion_nombre']) . '</td>';
                    echo '<td style="text-align: right;">' . mov_inv_html_stock_actual_celda($inv['stock_actual'] ?? 0, $inv['stock_minimo'] ?? null, $inv['stock_maximo'] ?? null) . '</td>';
                    $minReceta = isset($inv['stock_minimo']) && $inv['stock_minimo'] !== null && $inv['stock_minimo'] !== '' ? number_format((float) $inv['stock_minimo'], 2, '.', ',') : '—';
                    $maxReceta = isset($inv['stock_maximo']) && $inv['stock_maximo'] !== null && $inv['stock_maximo'] !== '' ? number_format((float) $inv['stock_maximo'], 2, '.', ',') : '—';
                    echo '<td style="text-align: right;">' . htmlspecialchars($minReceta) . '</td>';
                    echo '<td style="text-align: right;">' . htmlspecialchars($maxReceta) . '</td>';
                    echo '<td>' . htmlspecialchars($inv['almacen_nombre'] ?? '—') . '</td>';
                    echo '</tr>';
                }
            } else {
                echo '<tr><td colspan="9" class="text-center">No hay registros en el inventario de productos</td></tr>';
            }
        }

        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
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

        $permite = true;
        $stUm = $conn->prepare(
            'SELECT COALESCE(um.permite_movimiento_decimal, 1) AS p FROM insumos i LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id WHERE i.id = ? LIMIT 1'
        );
        if ($stUm) {
            $stUm->bind_param('i', $insumo_id);
            $stUm->execute();
            $ru = $stUm->get_result()->fetch_assoc();
            $stUm->close();
            $permite = !$ru || (int) ($ru['p'] ?? 1) === 1;
        }

        echo json_encode([
            'success' => true,
            'stock_actual' => $stock_actual,
            'permite_movimiento_decimal' => $permite ? 1 : 0,
        ]);
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

        if ($tipo_inventario === 'materia_prima' && $insumo_id > 0 && $cantidad > 0) {
            $stUm = $conn->prepare(
                'SELECT COALESCE(um.permite_movimiento_decimal, 1) AS p FROM insumos i LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id WHERE i.id = ? LIMIT 1'
            );
            if ($stUm) {
                $stUm->bind_param('i', $insumo_id);
                $stUm->execute();
                $ru = $stUm->get_result()->fetch_assoc();
                $stUm->close();
                $permite = !$ru || (int) ($ru['p'] ?? 1) === 1;
                try {
                    $cantidad = inv_normalizar_cantidad_movimiento_manual($cantidad, $permite);
                } catch (InvalidArgumentException $e) {
                    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                    $conn->close();
                    exit;
                }
            }
        } elseif ($tipo_inventario === 'productos' && $receta_id > 0 && $cantidad > 0) {
            try {
                $cantidad = inv_normalizar_cantidad_producto_terminado($cantidad);
            } catch (InvalidArgumentException $e) {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                $conn->close();
                exit;
            }
        }

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
                throw new Exception("La guia de corte / producto es obligatoria");
            }

            if (empty($tipo) || !in_array($tipo, ['entrada', 'salida'])) {
                throw new Exception("El tipo de movimiento es obligatorio y debe ser 'entrada' o 'salida'");
            }

            if (empty($cantidad) || $cantidad <= 0) {
                throw new Exception("La cantidad debe ser mayor a 0");
            }

            $cantidad = floatval($cantidad);

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

                    $stUm = $conn->prepare(
                        'SELECT COALESCE(um.permite_movimiento_decimal, 1) AS p FROM insumos i LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id WHERE i.id = ? LIMIT 1'
                    );
                    $stUm->bind_param('i', $insumo_id);
                    $stUm->execute();
                    $ru = $stUm->get_result()->fetch_assoc();
                    $stUm->close();
                    $permite = !$ru || (int) ($ru['p'] ?? 1) === 1;
                    try {
                        $cantidad = inv_normalizar_cantidad_movimiento_manual($cantidad, $permite);
                    } catch (InvalidArgumentException $e) {
                        throw new Exception($e->getMessage());
                    }

                    $optsMov = [
                        'tipo_item' => 'insumo',
                        'tipo_item_id' => (int) $insumo_id,
                        'cantidad' => $cantidad,
                        'origen' => $tipo_movimiento,
                        'observaciones' => $observaciones,
                        'tipo_movimiento' => $tipo_movimiento,
                    ];
                    if ($tipo === 'entrada') {
                        InventarioLotes::registrarEntrada($conn, $optsMov);
                    } else {
                        InventarioLotes::registrarSalida($conn, $optsMov);
                    }

                } else {
                    $sqlReceta = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                    $stmtReceta = $conn->prepare($sqlReceta);
                    $stmtReceta->bind_param("i", $receta_id);
                    $stmtReceta->execute();
                    $resultReceta = $stmtReceta->get_result();
                    if (!$rowReceta = $resultReceta->fetch_assoc()) {
                        throw new Exception("Guia de corte no encontrada");
                    }
                    $stmtReceta->close();
                    $producto_id = $rowReceta['producto_id'];
                    $rango_tallas_id = $rowReceta['rango_tallas_id'];
                    $tipo_produccion_id = $rowReceta['tipo_produccion_id'];

                    try {
                        $cantidad = inv_normalizar_cantidad_producto_terminado($cantidad);
                    } catch (InvalidArgumentException $e) {
                        throw new Exception($e->getMessage());
                    }

                    $optsMov = [
                        'tipo_item' => 'producto',
                        'tipo_item_id' => (int) $receta_id,
                        'cantidad' => $cantidad,
                        'origen' => $tipo_movimiento,
                        'observaciones' => $observaciones,
                        'tipo_movimiento' => $tipo_movimiento,
                    ];
                    if ($tipo === 'entrada') {
                        InventarioLotes::registrarEntrada($conn, $optsMov);
                    } else {
                        InventarioLotes::registrarSalida($conn, $optsMov);
                    }
                }

                $conn->commit();

                $detalleItem = $tipo_inventario === 'materia_prima'
                    ? ('insumo #' . (int) $insumo_id)
                    : ('guia de corte #' . (int) $receta_id);
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

        case 'aprobar_buen_estado':
            require_once __DIR__ . '/../lib/DevolucionesSchema.php';
            require_once __DIR__ . '/../lib/orden_numero.php';
            DevolucionesSchema::asegurarTablas($conn);

            $devolucionId = (int) ($_POST['devolucion_id'] ?? 0);
            $observacionesInspeccion = trim($_POST['observaciones_inspeccion'] ?? '');
            $usuarioId = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;

            if ($devolucionId <= 0) {
                throw new Exception('ID de devolución inválido.');
            }

            $conn->begin_transaction();
            try {
                $st = $conn->prepare("
                    SELECT d.id, d.codigo_devolucion, d.estado, d.motivo,
                           dd.id AS detalle_id, dd.orden_produccion_id, COALESCE(dd.cantidad, 1.00) AS cantidad,
                           op.id AS op_id, op.creado_en AS orden_creado_en,
                           p.id AS producto_id, p.nombre AS producto_nombre,
                           COALESCE(r.id, 0) AS receta_id
                    FROM devoluciones d
                    INNER JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                    INNER JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                    INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                    INNER JOIN productos p ON p.id = rp.producto_id
                    LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                        AND r.rango_tallas_id = rp.rango_tallas_id 
                        AND r.tipo_produccion_id = rp.tipo_produccion_id
                    WHERE d.id = ? FOR UPDATE
                ");
                $st->bind_param('i', $devolucionId);
                $st->execute();
                $dev = $st->get_result()->fetch_assoc();
                $st->close();

                if (!$dev) {
                    throw new Exception('No se encontró el registro de devolución.');
                }

                if ($dev['estado'] === 'aprobado') {
                    throw new Exception('Esta devolución ya fue aprobada y reingresada al inventario.');
                }

                $recetaId = (int) $dev['receta_id'];
                $cantidad = (float) $dev['cantidad'];
                $codigoDev = $dev['codigo_devolucion'];
                $numeroOrden = numero_orden_produccion((int)$dev['op_id'], $dev['orden_creado_en']);
                $fechaActual = date('Y-m-d H:i:s');

                if ($recetaId <= 0) {
                    $stR = $conn->prepare("SELECT id FROM recetas WHERE producto_id = ? LIMIT 1");
                    $stR->bind_param('i', $dev['producto_id']);
                    $stR->execute();
                    $rRow = $stR->get_result()->fetch_assoc();
                    $stR->close();
                    if ($rRow) {
                        $recetaId = (int)$rRow['id'];
                    } else {
                        $conn->query("INSERT INTO recetas (producto_id, rango_tallas_id, tipo_produccion_id) VALUES ({$dev['producto_id']}, 1, 1)");
                        $recetaId = (int)$conn->insert_id;
                    }
                }

                $obsInventario = "Reingreso por devolución {$codigoDev} ({$cantidad}  de OP {$numeroOrden} ({$dev['producto_nombre']}) - Validado en BUEN ESTADO.";
                if ($observacionesInspeccion !== '') {
                    $obsInventario .= " Obs: " . $observacionesInspeccion;
                }

                $resEntrada = InventarioLotes::registrarEntrada($conn, [
                    'tipo_item' => 'producto',
                    'tipo_item_id' => $recetaId,
                    'cantidad' => $cantidad,
                    'origen' => 'devolucion',
                    'origen_id' => $devolucionId,
                    'observaciones' => $obsInventario,
                    'tipo_movimiento' => 'devolucion',
                ]);

                $loteId = (int)($resEntrada['lote_id'] ?? 0);
                $numeroLote = $resEntrada['numero_lote'] ?? '';

                $motivoInspeccionFinal = $observacionesInspeccion !== '' ? $observacionesInspeccion : 'Bobina validada en buen estado para stock.';
                $stUpd = $conn->prepare("
                    UPDATE devoluciones 
                    SET estado = 'aprobado', 
                        accion_inventario = 'reingresar_stock', 
                        fecha_inspeccion = ?, 
                        usuario_inspeccion_id = ?, 
                        motivo_inspeccion = ?
                    WHERE id = ?
                ");
                $stUpd->bind_param('sisi', $fechaActual, $usuarioId, $motivoInspeccionFinal, $devolucionId);
                $stUpd->execute();
                $stUpd->close();

                $stUpdDet = $conn->prepare("
                    UPDATE devoluciones_detalle 
                    SET estado_unidad_posterior = 'devuelto_stock',
                        lote_id = ?
                    WHERE id = ?
                ");
                $detalleId = (int)$dev['detalle_id'];
                $stUpdDet->bind_param('ii', $loteId, $detalleId);
                $stUpdDet->execute();
                $stUpdDet->close();

                Auditoria::registrar(
                    $conn,
                    "Inspección de devolución {$codigoDev}: VALIDADO EN BUEN ESTADO. Reingresaron {$cantidad} al inventario (Lote: {$numeroLote}) de la OP {$numeroOrden}.",
                    'Movimientos inventario'
                );

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "La bobina/material fue validada en BUEN ESTADO y reingresada exitosamente al inventario de stock.",
                    'numero_lote' => $numeroLote
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'rechazar_material':
            require_once __DIR__ . '/../lib/DevolucionesSchema.php';
            require_once __DIR__ . '/../lib/orden_numero.php';
            DevolucionesSchema::asegurarTablas($conn);

            $devolucionId = (int) ($_POST['devolucion_id'] ?? 0);
            $motivoRechazo = trim($_POST['motivo_rechazo'] ?? '');
            $usuarioId = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;

            if ($devolucionId <= 0) {
                throw new Exception('ID de devolución inválido.');
            }

            $conn->begin_transaction();
            try {
                $st = $conn->prepare("
                    SELECT d.id, d.codigo_devolucion, d.estado, d.motivo,
                           dd.id AS detalle_id, dd.orden_produccion_id, COALESCE(dd.cantidad, 1.00) AS cantidad,
                           op.id AS op_id, op.creado_en AS orden_creado_en,
                           p.nombre AS producto_nombre
                    FROM devoluciones d
                    INNER JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                    INNER JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                    INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                    INNER JOIN productos p ON p.id = rp.producto_id
                    WHERE d.id = ? FOR UPDATE
                ");
                $st->bind_param('i', $devolucionId);
                $st->execute();
                $dev = $st->get_result()->fetch_assoc();
                $st->close();

                if (!$dev) {
                    throw new Exception('No se encontró el registro de devolución.');
                }

                if ($dev['estado'] === 'aprobado') {
                    throw new Exception('Esta devolución ya fue aprobada previamente.');
                }

                $codigoDev = $dev['codigo_devolucion'];
                $cantidad = (float) $dev['cantidad'];
                $numeroOrden = numero_orden_produccion((int)$dev['op_id'], $dev['orden_creado_en']);
                $fechaActual = date('Y-m-d H:i:s');
                if ($motivoRechazo === '') {
                    $motivoRechazo = !empty($dev['motivo']) ? $dev['motivo'] : 'Material rechazado / no conforme';
                }

                $stUpd = $conn->prepare("
                    UPDATE devoluciones 
                    SET estado = 'rechazado', 
                        accion_inventario = 'rechazado_sin_stock', 
                        fecha_inspeccion = ?, 
                        usuario_inspeccion_id = ?, 
                        motivo_inspeccion = ?
                    WHERE id = ?
                ");
                $stUpd->bind_param('sisi', $fechaActual, $usuarioId, $motivoRechazo, $devolucionId);
                $stUpd->execute();
                $stUpd->close();

                $stUpdDet = $conn->prepare("
                    UPDATE devoluciones_detalle 
                    SET estado_unidad_posterior = 'rechazado'
                    WHERE id = ?
                ");
                $detalleId = (int)$dev['detalle_id'];
                $stUpdDet->bind_param('i', $detalleId);
                $stUpdDet->execute();
                $stUpdDet->close();

                Auditoria::registrar(
                    $conn,
                    "Inspección de devolución {$codigoDev}: RECHAZADA Y DESCARTADA ({$cantidad} de OP {$numeroOrden}). Motivo: {$motivoRechazo}. No ingresó al inventario.",
                    'Movimientos inventario'
                );

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "La bobina/material fue marcada como RECHAZADA. No se sumará al inventario."
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
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

$conn->close();
?>

