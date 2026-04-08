<?php
require_once "../connection/connection.php";

$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {

        $sql = "
            SELECT 
                op.id AS orden_id,
                op.tasa_cambiaria_id,
                tc.tasa AS tasa_orden,
                p.nombre AS producto_nombre,
                p.categoria AS producto_categoria,
                op.cantidad_a_producir,
                op.fecha_inicio,
                op.fecha_fin,
                op.estado,
                op.observaciones,
                r.id AS receta_id,
                rp.producto_id,
                rp.rango_tallas_id,
                rp.tipo_produccion_id,
                COALESCE(SUM(rp2.cantidad_por_unidad * i.costo_unitario), 0) AS costo_por_unidad
            FROM ordenes_produccion op
            INNER JOIN recetas_productos rp ON op.receta_producto_id = rp.id
            INNER JOIN productos p ON rp.producto_id = p.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = op.tasa_cambiaria_id
            LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                AND r.rango_tallas_id = rp.rango_tallas_id 
                AND r.tipo_produccion_id = rp.tipo_produccion_id
            LEFT JOIN recetas_productos rp2 ON rp2.producto_id = rp.producto_id 
                AND rp2.rango_tallas_id = rp.rango_tallas_id 
                AND rp2.tipo_produccion_id = rp.tipo_produccion_id
            LEFT JOIN insumos i ON rp2.insumo_id = i.id
            GROUP BY op.id, op.tasa_cambiaria_id, tc.tasa, r.id, rp.producto_id, rp.rango_tallas_id, rp.tipo_produccion_id, p.nombre, p.categoria, op.cantidad_a_producir, op.fecha_inicio, op.fecha_fin, op.estado, op.observaciones
            ORDER BY op.id DESC
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

    restringirEscritura();

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
                // Primero obtener la información de la receta
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

                // Validar que haya insumos disponibles en el inventario
                $sqlInsumosReceta = "SELECT rp.insumo_id, rp.cantidad_por_unidad, i.nombre AS insumo_nombre
                                    FROM recetas_productos rp
                                    INNER JOIN insumos i ON rp.insumo_id = i.id
                                    WHERE rp.producto_id = ? 
                                      AND rp.rango_tallas_id = ? 
                                      AND rp.tipo_produccion_id = ?";
                $stmtInsumosReceta = $conn->prepare($sqlInsumosReceta);
                $stmtInsumosReceta->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                $stmtInsumosReceta->execute();
                $resultInsumosReceta = $stmtInsumosReceta->get_result();
                
                $insumosFaltantes = array();
                $cantidadProducir = floatval($cantidad);
                
                while ($rowInsumo = $resultInsumosReceta->fetch_assoc()) {
                    $insumoId = $rowInsumo['insumo_id'];
                    $cantidadPorUnidad = floatval($rowInsumo['cantidad_por_unidad']);
                    $insumoNombre = $rowInsumo['insumo_nombre'];
                    $cantidadNecesaria = $cantidadPorUnidad * $cantidadProducir;
                    
                    // Obtener stock actual del insumo
                    if ($tieneInventarioNuevo) {
                        $sqlStockInsumo = "SELECT stock_actual FROM inventario WHERE tipo_item = 'insumo' AND tipo_item_id = ?";
                    } else {
                        $sqlStockInsumo = "SELECT stock_actual FROM inventario WHERE insumo_id = ?";
                    }
                    $stmtStockInsumo = $conn->prepare($sqlStockInsumo);
                    $stmtStockInsumo->bind_param("i", $insumoId);
                    $stmtStockInsumo->execute();
                    $resultStockInsumo = $stmtStockInsumo->get_result();
                    
                    $stockActual = 0;
                    if ($rowStockInsumo = $resultStockInsumo->fetch_assoc()) {
                        $stockActual = floatval($rowStockInsumo['stock_actual']);
                    }
                    $stmtStockInsumo->close();
                    
                    // Validar si hay stock suficiente
                    if ($stockActual < $cantidadNecesaria) {
                        $insumosFaltantes[] = [
                            'insumo' => $insumoNombre,
                            'stock_disponible' => $stockActual,
                            'cantidad_necesaria' => $cantidadNecesaria,
                            'faltante' => $cantidadNecesaria - $stockActual
                        ];
                    }
                }
                $stmtInsumosReceta->close();
                
                // Si hay insumos faltantes, lanzar error
                if (!empty($insumosFaltantes)) {
                    $mensajeError = "No hay suficiente stock de insumos para crear la orden de producción:\n\n";
                    foreach ($insumosFaltantes as $faltante) {
                        $mensajeError .= "• {$faltante['insumo']}\n";
                        $mensajeError .= "  Disponible: " . number_format($faltante['stock_disponible'], 2) . "\n";
                        $mensajeError .= "  Necesario: " . number_format($faltante['cantidad_necesaria'], 2) . "\n";
                        $mensajeError .= "  Faltante: " . number_format($faltante['faltante'], 2) . "\n\n";
                    }
                    throw new Exception($mensajeError);
                }

                // Obtener el ID de recetas_productos que corresponde a esta receta
                $sqlRecetaProducto = "SELECT id FROM recetas_productos 
                                     WHERE producto_id = ? 
                                       AND rango_tallas_id = ? 
                                       AND tipo_produccion_id = ? 
                                     LIMIT 1";
                $stmtRecetaProducto = $conn->prepare($sqlRecetaProducto);
                $stmtRecetaProducto->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                $stmtRecetaProducto->execute();
                $resultRecetaProducto = $stmtRecetaProducto->get_result();
                
                if (!$rowRecetaProducto = $resultRecetaProducto->fetch_assoc()) {
                    throw new Exception("No se encontró receta_producto asociada a esta receta");
                }
                $receta_producto_id = $rowRecetaProducto['id'];
                $stmtRecetaProducto->close();

                // Ahora insertar la orden con el ID correcto de recetas_productos
                $stmt = $conn->prepare("
                    INSERT INTO ordenes_produccion (receta_producto_id, cantidad_a_producir, fecha_inicio, fecha_fin, observaciones, estado)
                    VALUES (?, ?, ?, ?, ?, 'pendiente')
                ");
                $stmt->bind_param("idsss", $receta_producto_id, $cantidad, $fecha_inicio, $fecha_fin, $observaciones);
                $stmt->execute();
                $ordenId = $conn->insert_id;
                $stmt->close();

                // Descontar insumos del inventario
                $sqlInsumosParaDescontar = "SELECT rp.insumo_id, rp.cantidad_por_unidad
                                            FROM recetas_productos rp
                                            WHERE rp.producto_id = ? 
                                              AND rp.rango_tallas_id = ? 
                                              AND rp.tipo_produccion_id = ?";
                $stmtInsumosDescontar = $conn->prepare($sqlInsumosParaDescontar);
                $stmtInsumosDescontar->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                $stmtInsumosDescontar->execute();
                $resultInsumosDescontar = $stmtInsumosDescontar->get_result();
                
                while ($rowInsumoDescontar = $resultInsumosDescontar->fetch_assoc()) {
                    $insumoId = $rowInsumoDescontar['insumo_id'];
                    $cantidadPorUnidad = floatval($rowInsumoDescontar['cantidad_por_unidad']);
                    $cantidadTotal = $cantidadPorUnidad * $cantidadProducir;
                    
                    if ($tieneInventarioNuevo) {
                        $sqlStockInsumo = "SELECT stock_actual FROM inventario WHERE tipo_item = 'insumo' AND tipo_item_id = ?";
                    } else {
                        $sqlStockInsumo = "SELECT stock_actual FROM inventario WHERE insumo_id = ?";
                    }
                    $stmtStockInsumo = $conn->prepare($sqlStockInsumo);
                    $stmtStockInsumo->bind_param("i", $insumoId);
                    $stmtStockInsumo->execute();
                    $resultStockInsumo = $stmtStockInsumo->get_result();
                    $stockActual = 0;
                    if ($rowStockInsumo = $resultStockInsumo->fetch_assoc()) {
                        $stockActual = floatval($rowStockInsumo['stock_actual']);
                    }
                    $stmtStockInsumo->close();
                    $nuevoStock = $stockActual - $cantidadTotal;
                    if ($nuevoStock < 0) $nuevoStock = 0;
                    $stmtMovimientoInsumo = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, insumo_id, tipo, cantidad, observaciones, orden_produccion_id) VALUES ('insumo', ?, 'salida', ?, ?, ?)");
                    $obsMovimientoInsumo = "Descuento por creación de orden de producción #{$ordenId}";
                    $stmtMovimientoInsumo->bind_param("idsi", $insumoId, $cantidadTotal, $obsMovimientoInsumo, $ordenId);
                    $stmtMovimientoInsumo->execute();
                    $stmtMovimientoInsumo->close();
                    if ($tieneInventarioNuevo) {
                        $stmtInventarioInsumo = $conn->prepare("
                            INSERT INTO inventario (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion, orden_produccion_id)
                            VALUES ('insumo', ?, ?, 'orden_produccion', NOW(), ?)
                            ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW(), orden_produccion_id = VALUES(orden_produccion_id)
                        ");
                        $stmtInventarioInsumo->bind_param("idi", $insumoId, $nuevoStock, $ordenId);
                    } else {
                        $stmtInventarioInsumo = $conn->prepare("INSERT INTO inventario (insumo_id, stock_actual, ultima_actualizacion) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()");
                        $stmtInventarioInsumo->bind_param("id", $insumoId, $nuevoStock);
                    }
                    $stmtInventarioInsumo->execute();
                    $stmtInventarioInsumo->close();
                }
                $stmtInsumosDescontar->close();

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
                $nuevoStock = $stockActual + floatval($cantidad);

                $obsMovimiento = "Entrada por creación de orden de producción #{$ordenId}";
                $tieneRecetaIdDet = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'receta_id'")->num_rows > 0;
                if ($tieneRecetaIdDet) {
                    $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, receta_id, tipo, cantidad, observaciones, orden_produccion_id) VALUES ('producto', ?, 'entrada', ?, ?, ?)");
                    $stmtMovimiento->bind_param("idsi", $receta_id, $cantidad, $obsMovimiento, $ordenId);
                } else {
                    $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, observaciones) VALUES ('producto', ?, ?, ?, 'entrada', ?, ?)");
                    $stmtMovimiento->bind_param("iiids", $producto_id, $rango_tallas_id, $tipo_produccion_id, $cantidad, $obsMovimiento);
                }
                $stmtMovimiento->execute();
                $stmtMovimiento->close();

                if ($tieneInventarioNuevo) {
                    $stmtInventario = $conn->prepare("
                        INSERT INTO inventario (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion, orden_produccion_id)
                        VALUES ('producto', ?, ?, 'orden_produccion', NOW(), ?)
                        ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW(), orden_produccion_id = VALUES(orden_produccion_id)
                    ");
                    $stmtInventario->bind_param("idi", $receta_id, $nuevoStock, $ordenId);
                } else {
                    $stmtInventario = $conn->prepare("INSERT INTO inventario_productos (producto_id, rango_tallas_id, tipo_produccion_id, stock_actual, ultima_actualizacion) VALUES (?, ?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()");
                    $stmtInventario->bind_param("iiid", $producto_id, $rango_tallas_id, $tipo_produccion_id, $nuevoStock);
                }
                $stmtInventario->execute();
                $stmtInventario->close();

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Orden creada', 'id' => $ordenId]);
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

            if (!$ordenActual) {
                throw new Exception("Orden no encontrada");
            }

            $receta_id = $_POST['receta_id'] ?? null;
            $cantidad = $_POST['cantidad_a_producir'] ?? 0;
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $estado = $_POST['estado'] ?? 'pendiente';
            $observaciones = $_POST['observaciones'] ?? '';

            // Si se está cambiando la receta, obtener el recetas_productos.id correcto
            $receta_producto_id = $ordenActual['receta_producto_id']; // Mantener el actual por defecto
            
            if ($receta_id) {
                // Obtener información de la receta
                $sqlRecetaInfo = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                $stmtRecetaInfo = $conn->prepare($sqlRecetaInfo);
                $stmtRecetaInfo->bind_param("i", $receta_id);
                $stmtRecetaInfo->execute();
                $resultRecetaInfo = $stmtRecetaInfo->get_result();
                
                if ($rowRecetaInfo = $resultRecetaInfo->fetch_assoc()) {
                    // Obtener un recetas_productos.id válido
                    $sqlRecetaProducto = "SELECT id FROM recetas_productos 
                                         WHERE producto_id = ? 
                                           AND rango_tallas_id = ? 
                                           AND tipo_produccion_id = ? 
                                         LIMIT 1";
                    $stmtRecetaProducto = $conn->prepare($sqlRecetaProducto);
                    $stmtRecetaProducto->bind_param("iii", 
                        $rowRecetaInfo['producto_id'], 
                        $rowRecetaInfo['rango_tallas_id'], 
                        $rowRecetaInfo['tipo_produccion_id']
                    );
                    $stmtRecetaProducto->execute();
                    $resultRecetaProducto = $stmtRecetaProducto->get_result();
                    if ($rowRecetaProducto = $resultRecetaProducto->fetch_assoc()) {
                        $receta_producto_id = $rowRecetaProducto['id'];
                    }
                    $stmtRecetaProducto->close();
                }
                $stmtRecetaInfo->close();
            }

            $conn->begin_transaction();
            try {
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
                $stmt->bind_param("idssssi", $receta_producto_id, $cantidad, $fecha_inicio, $fecha_fin, $estado, $observaciones, $id);
                $stmt->execute();
                $stmt->close();
                
                // Si se cambia el estado a 'en_proceso' o 'finalizado' desde 'pendiente', descontar insumos
                if (($estado === 'en_proceso' || $estado === 'finalizado') && 
                    $ordenActual['estado'] === 'pendiente') {
                    
                    $recetaId = $receta_id ?? $ordenActual['receta_producto_id'];
                    $cantidadProducir = $cantidad > 0 ? $cantidad : $ordenActual['cantidad_a_producir'];
                    
                    // Obtener información de la receta
                    $sqlRecetaInfo = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                    $stmtRecetaInfo = $conn->prepare($sqlRecetaInfo);
                    $stmtRecetaInfo->bind_param("i", $recetaId);
                    $stmtRecetaInfo->execute();
                    $resultRecetaInfo = $stmtRecetaInfo->get_result();
                    
                    if ($rowRecetaInfo = $resultRecetaInfo->fetch_assoc()) {
                        $producto_id = $rowRecetaInfo['producto_id'];
                        $rango_tallas_id = $rowRecetaInfo['rango_tallas_id'];
                        $tipo_produccion_id = $rowRecetaInfo['tipo_produccion_id'];
                        
                        // Obtener los insumos de la receta
                        $sqlInsumos = "SELECT insumo_id, cantidad_por_unidad 
                                      FROM recetas_productos 
                                      WHERE producto_id = ? 
                                        AND rango_tallas_id = ? 
                                        AND tipo_produccion_id = ?";
                        $stmtInsumos = $conn->prepare($sqlInsumos);
                        $stmtInsumos->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                        $stmtInsumos->execute();
                        $resultInsumos = $stmtInsumos->get_result();
                        
                        while ($rowInsumo = $resultInsumos->fetch_assoc()) {
                            $insumoId = $rowInsumo['insumo_id'];
                            $cantidadPorUnidad = floatval($rowInsumo['cantidad_por_unidad']);
                            $cantidadTotal = $cantidadPorUnidad * floatval($cantidadProducir);
                            
                            if ($tieneInventarioNuevo) {
                                $sqlStock = "SELECT stock_actual FROM inventario WHERE tipo_item = 'insumo' AND tipo_item_id = ?";
                            } else {
                                $sqlStock = "SELECT stock_actual FROM inventario WHERE insumo_id = ?";
                            }
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
                            if ($nuevoStock < 0) $nuevoStock = 0;
                            $obsMovimiento = "Descuento por orden de producción #{$id}";
                            $stmtMovimiento = $conn->prepare("INSERT INTO inventario_detalle (tipo_item, insumo_id, tipo, cantidad, observaciones, orden_produccion_id) VALUES ('insumo', ?, 'salida', ?, ?, ?)");
                            $stmtMovimiento->bind_param("idsi", $insumoId, $cantidadTotal, $obsMovimiento, $id);
                            $stmtMovimiento->execute();
                            $stmtMovimiento->close();
                            if ($tieneInventarioNuevo) {
                                $stmtInventario = $conn->prepare("
                                    INSERT INTO inventario (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion, orden_produccion_id)
                                    VALUES ('insumo', ?, ?, 'orden_produccion', NOW(), ?)
                                    ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW(), orden_produccion_id = VALUES(orden_produccion_id)
                                ");
                                $stmtInventario->bind_param("idi", $insumoId, $nuevoStock, $id);
                            } else {
                                $stmtInventario = $conn->prepare("INSERT INTO inventario (insumo_id, stock_actual, ultima_actualizacion) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()");
                                $stmtInventario->bind_param("id", $insumoId, $nuevoStock);
                            }
                            $stmtInventario->execute();
                            $stmtInventario->close();
                        }
                        $stmtInsumos->close();
                    }
                    $stmtRecetaInfo->close();
                }
                
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Orden actualizada']);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}