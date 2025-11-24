<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {

        $sql = "
            SELECT 
                op.id AS orden_id,
                p.nombre AS producto_nombre,
                p.categoria AS producto_categoria,
                op.cantidad_a_producir,
                op.fecha_inicio,
                op.fecha_fin,
                op.estado,
                op.observaciones,
                r.id AS receta_id,
                r.producto_id,
                r.rango_tallas_id,
                r.tipo_produccion_id,
                COALESCE(SUM(rp.cantidad_por_unidad * i.costo_unitario), 0) AS costo_por_unidad
            FROM ordenes_produccion op
            INNER JOIN recetas r ON op.receta_producto_id = r.id
            INNER JOIN productos p ON r.producto_id = p.id
            LEFT JOIN recetas_productos rp ON rp.producto_id = r.producto_id 
                AND rp.rango_tallas_id = r.rango_tallas_id 
                AND rp.tipo_produccion_id = r.tipo_produccion_id
            LEFT JOIN insumos i ON rp.insumo_id = i.id
            GROUP BY op.id, r.id, r.producto_id, r.rango_tallas_id, r.tipo_produccion_id
            ORDER BY op.creado_en DESC
        ";

        $result = $conn->query($sql);
        $ordenes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ordenes[] = $row;
            }
        }
        $i = 0;
        if (!empty($ordenes)) {
            foreach ($ordenes as $o) {
                $i++;
                $badgeClass = match($o['estado']) {
                    'finalizado' => 'success',
                    'en_proceso' => 'warning',
                    'pendiente' => 'info',
                    default => 'danger'
                };
                
                $costoPorUnidad = floatval($o['costo_por_unidad'] ?? 0);
                $cantidad = floatval($o['cantidad_a_producir'] ?? 0);
                $costoTotal = $costoPorUnidad * $cantidad;
                
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($o['producto_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($o['producto_categoria'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($o['cantidad_a_producir']) . '</td>';
                echo '<td>$' . number_format($costoPorUnidad, 2, '.', ',') . '</td>';
                echo '<td style="font-weight: bold; color: #0056b3;">$' . number_format($costoTotal, 2, '.', ',') . '</td>';
                echo '<td>' . ($o['fecha_inicio'] ? date('d/m/Y', strtotime($o['fecha_inicio'])) : '—') . '</td>';
                echo '<td>' . ($o['fecha_fin'] ? date('d/m/Y', strtotime($o['fecha_fin'])) : '—') . '</td>';
                // echo '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($o['estado']) . '</span></td>';
                echo '<td><button class="btn btn-sm btn-primary" onclick="editarOrden(' . htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') . ')">Editar</button></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="10" class="text-center">No hay órdenes de producción</td></tr>';
        }
        $conn->close();
        exit;
    }

    header('Content-Type: application/json');

    switch ($action) {
        case 'obtener_costo_receta':
            $receta_id = $_POST['receta_id'] ?? null;
            if (!$receta_id) {
                throw new Exception("ID de receta requerido");
            }
            
            $sqlReceta = "SELECT producto_id, rango_tallas_id, tipo_produccion_id 
                         FROM recetas WHERE id = ?";
            $stmtReceta = $conn->prepare($sqlReceta);
            $stmtReceta->bind_param("i", $receta_id);
            $stmtReceta->execute();
            $resultReceta = $stmtReceta->get_result();
            
            if ($rowReceta = $resultReceta->fetch_assoc()) {
                $sqlCosto = "
                    SELECT SUM(rp.cantidad_por_unidad * i.costo_unitario) AS costo_por_unidad
                    FROM recetas_productos rp
                    INNER JOIN insumos i ON rp.insumo_id = i.id
                    WHERE rp.producto_id = ? 
                      AND rp.rango_tallas_id = ? 
                      AND rp.tipo_produccion_id = ?
                ";
                $stmtCosto = $conn->prepare($sqlCosto);
                $stmtCosto->bind_param("iii", 
                    $rowReceta['producto_id'], 
                    $rowReceta['rango_tallas_id'], 
                    $rowReceta['tipo_produccion_id']
                );
                $stmtCosto->execute();
                $resultCosto = $stmtCosto->get_result();
                
                if ($rowCosto = $resultCosto->fetch_assoc()) {
                    echo json_encode([
                        'success' => true, 
                        'costo_por_unidad' => $rowCosto['costo_por_unidad'] ?? 0
                    ]);
                } else {
                    throw new Exception("No se pudo calcular el costo de la receta");
                }
            } else {
                throw new Exception("Receta no encontrada");
            }
            break;

        case 'crear':
            $receta_id = $_POST['receta_id'] ?? null;
            $cantidad = $_POST['cantidad_a_producir'] ?? 0;
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $observaciones = $_POST['observaciones'] ?? '';

            if (!$receta_id || $cantidad <= 0) {
                throw new Exception("Receta y cantidad válida son obligatorios");
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    INSERT INTO ordenes_produccion (receta_producto_id, cantidad_a_producir, fecha_inicio, fecha_fin, observaciones, estado)
                    VALUES (?, ?, ?, ?, ?, 'pendiente')
                ");

                if($_POST['orden_id'] == ""){
                    $msg = "orden creada";
                }else{
                    $msg = "orden editada";
                }
                $stmt->bind_param("idsss", $receta_id, $cantidad, $fecha_inicio, $fecha_fin, $observaciones);
                $stmt->execute();
                $ordenId = $conn->insert_id;
                $stmt->close();

                $sqlRecetaInfo = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                $stmtRecetaInfo = $conn->prepare($sqlRecetaInfo);
                $stmtRecetaInfo->bind_param("i", $receta_id);
                $stmtRecetaInfo->execute();
                $resultRecetaInfo = $stmtRecetaInfo->get_result();
                
                if (!$rowRecetaInfo = $resultRecetaInfo->fetch_assoc()) {
                    throw new Exception("Receta no encontrada");
                }
                $stmtRecetaInfo->close();

                $producto_id = $rowRecetaInfo['producto_id'];
                $rango_tallas_id = $rowRecetaInfo['rango_tallas_id'];
                $tipo_produccion_id = $rowRecetaInfo['tipo_produccion_id'];

                $createInventarioProductos = "
                CREATE TABLE IF NOT EXISTS inventario_productos (
                    producto_id INT NOT NULL,
                    rango_tallas_id INT NOT NULL,
                    tipo_produccion_id INT NOT NULL,
                    stock_actual DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    ultima_actualizacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (producto_id, rango_tallas_id, tipo_produccion_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ";
                $conn->query($createInventarioProductos);

                $createMovimientosProductos = "
                CREATE TABLE IF NOT EXISTS movimientos_productos_detalle (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    producto_id INT NOT NULL,
                    rango_tallas_id INT NOT NULL,
                    tipo_produccion_id INT NOT NULL,
                    tipo ENUM('entrada', 'salida') NOT NULL,
                    cantidad DECIMAL(12,2) NOT NULL,
                    observaciones TEXT,
                    fecha_movimiento TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
                ";
                $conn->query($createMovimientosProductos);

                $sqlStock = "SELECT stock_actual FROM inventario_productos 
                            WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ?";
                $stmtStock = $conn->prepare($sqlStock);
                $stmtStock->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                $stmtStock->execute();
                $resultStock = $stmtStock->get_result();
                $stockActual = 0;
                
                if ($rowStock = $resultStock->fetch_assoc()) {
                    $stockActual = floatval($rowStock['stock_actual']);
                }
                $stmtStock->close();

                $nuevoStock = $stockActual + floatval($cantidad);

                $stmtMovimiento = $conn->prepare("
                    INSERT INTO movimientos_productos_detalle 
                    (producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, observaciones)
                    VALUES (?, ?, ?, 'entrada', ?, ?)
                ");
                $obsMovimiento = "Entrada por creación de orden de producción #{$ordenId}";
                $stmtMovimiento->bind_param("iiids", $producto_id, $rango_tallas_id, $tipo_produccion_id, $cantidad, $obsMovimiento);
                $stmtMovimiento->execute();
                $stmtMovimiento->close();

                $stmtInventario = $conn->prepare("
                    INSERT INTO inventario_productos (producto_id, rango_tallas_id, tipo_produccion_id, stock_actual, ultima_actualizacion)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE 
                        stock_actual = VALUES(stock_actual),
                        ultima_actualizacion = NOW()
                ");
                $stmtInventario->bind_param("iiid", $producto_id, $rango_tallas_id, $tipo_produccion_id, $nuevoStock);
                $stmtInventario->execute();
                $stmtInventario->close();

                $conn->commit();
                echo json_encode(['success' => true, 'message' => $msg, 'id' => $ordenId]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de orden requerido");

            $stmtEstado = $conn->prepare("SELECT estado, receta_producto_id, cantidad_a_producir FROM ordenes_produccion WHERE id = ?");
            $stmtEstado->bind_param("i", $id);
            $stmtEstado->execute();
            $resultEstado = $stmtEstado->get_result();
            $ordenActual = $resultEstado->fetch_assoc();
            $stmtEstado->close();

            $receta_id = $_POST['receta_id'] ?? null;
            $cantidad = $_POST['cantidad_a_producir'] ?? 0;
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $estado = $_POST['estado'] ?? 'pendiente';
            $observaciones = $_POST['observaciones'] ?? '';

            $stmt = $conn->prepare("
                UPDATE ordenes_produccion 
                SET receta_producto_id = ?, 
                    cantidad_a_producir = ?, 
                    fecha_inicio = ?, 
                    fecha_fin = ?, 
                    estado = ?, 
                    observaciones = ?
                WHERE id = ?
            ");
            $stmt->bind_param("idssssi", $receta_id, $cantidad, $fecha_inicio, $fecha_fin, $estado, $observaciones, $id);
            $stmt->execute();
            
            if (($estado === 'en_proceso' || $estado === 'finalizado') && 
                $ordenActual && 
                $ordenActual['estado'] === 'pendiente') {
                
                $recetaId = $receta_id ?? $ordenActual['receta_producto_id'];
                $cantidadProducir = $cantidad > 0 ? $cantidad : $ordenActual['cantidad_a_producir'];
                
                $sqlRecetaInfo = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                $stmtRecetaInfo = $conn->prepare($sqlRecetaInfo);
                $stmtRecetaInfo->bind_param("i", $recetaId);
                $stmtRecetaInfo->execute();
                $resultRecetaInfo = $stmtRecetaInfo->get_result();
                
                if (!$rowRecetaInfo = $resultRecetaInfo->fetch_assoc()) {
                    throw new Exception("Receta no encontrada");
                }
                $stmtRecetaInfo->close();
                
                $sqlReceta = "SELECT insumo_id, cantidad_por_unidad 
                             FROM recetas_productos 
                             WHERE producto_id = ? 
                               AND rango_tallas_id = ? 
                               AND tipo_produccion_id = ?";
                $stmtReceta = $conn->prepare($sqlReceta);
                $stmtReceta->bind_param("iii", 
                    $rowRecetaInfo['producto_id'], 
                    $rowRecetaInfo['rango_tallas_id'], 
                    $rowRecetaInfo['tipo_produccion_id']
                );
                $stmtReceta->execute();
                $resultReceta = $stmtReceta->get_result();
                
                $conn->begin_transaction();
                try {
                    while ($rowReceta = $resultReceta->fetch_assoc()) {
                        $insumoId = $rowReceta['insumo_id'];
                        $cantidadPorUnidad = floatval($rowReceta['cantidad_por_unidad']);
                        $cantidadTotal = $cantidadPorUnidad * floatval($cantidadProducir);
                        
                        $sqlStock = "SELECT stock_actual FROM inventario WHERE insumo_id = ?";
                        $stmtStock = $conn->prepare($sqlStock);
                        $stmtStock->bind_param("i", $insumoId);
                        $stmtStock->execute();
                        $resultStock = $stmtStock->get_result();
                        $stockActual = 0;
                        if ($rowStock = $resultStock->fetch_assoc()) {
                            $stockActual = floatval($rowStock['stock_actual']);
                        }
                        $stmtStock->close();
                        
                        $nuevoStock = $stockActual - $cantidadTotal;
                        if ($nuevoStock < 0) {
                            $nuevoStock = 0;
                        }
                        
                        $stmtMovimiento = $conn->prepare("
                            INSERT INTO movimientos_inventario_detalle 
                            (insumo_id, tipo, cantidad, observaciones, orden_produccion_id)
                            VALUES (?, 'salida', ?, ?, ?)
                        ");
                        $obsMovimiento = "Descuento por orden de producción #{$id}";
                        $stmtMovimiento->bind_param("idsi", $insumoId, $cantidadTotal, $obsMovimiento, $id);
                        $stmtMovimiento->execute();
                        $stmtMovimiento->close();
                        
                        $stmtInventario = $conn->prepare("
                            INSERT INTO inventario (insumo_id, stock_actual, ultima_actualizacion)
                            VALUES (?, ?, NOW())
                            ON DUPLICATE KEY UPDATE 
                                stock_actual = VALUES(stock_actual),
                                ultima_actualizacion = NOW()
                        ");
                        $stmtInventario->bind_param("id", $insumoId, $nuevoStock);
                        $stmtInventario->execute();
                        $stmtInventario->close();
                    }
                    $stmtReceta->close();
                    $conn->commit();
                } catch (Exception $e) {
                    $conn->rollback();
                    throw new Exception("Error al descontar insumos: " . $e->getMessage());
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Orden actualizada']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="10" class="text-center text-danger">Error al cargar órdenes</td></tr>';
    }
}

$conn->close();
?>