<?php
require_once __DIR__ . "/../connection/connection.php";
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/orden_numero.php';
require_once __DIR__ . '/../lib/InventarioLotes.php';
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/DevolucionesSchema.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

DevolucionesSchema::asegurarTablas($conn);

if (!function_exists('generarCodigoDevolucionSimple')) {
    function generarCodigoDevolucionSimple(int $devolucionId, ?string $fecha = null): string {
        $nOrden = numero_orden_produccion($devolucionId, $fecha);
        return $nOrden !== '' ? 'DEV-' . $nOrden : sprintf('DEV-%04d', $devolucionId);
    }
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'buscar_ordenes_select2':
            $q = trim($_GET['q'] ?? $_POST['q'] ?? '');

            $sql = "
                SELECT op.id, op.cantidad_a_producir, op.creado_en,
                       p.nombre AS producto_nombre,
                       COALESCE(t.nombre, 'Única') AS talla_nombre,
                       COALESCE(
                           inv.stock_actual,
                           (SELECT COALESCE(SUM(l.cantidad_restante), 0)
                            FROM inventario_lotes l
                            WHERE l.tipo_item = 'producto' 
                              AND (l.tipo_item_id = r.id OR (r.id IS NULL AND l.tipo_item_id = p.id))
                              AND l.cantidad_restante > 0),
                           0
                       ) AS stock_inventario
                FROM ordenes_produccion op
                INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                INNER JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
                LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                    AND r.rango_tallas_id = rp.rango_tallas_id 
                    AND r.tipo_produccion_id = rp.tipo_produccion_id
                LEFT JOIN inventario inv ON inv.tipo_item = 'producto' AND inv.tipo_item_id = r.id
            ";

            $mesesMap = [
                'ene' => 1, 'enero' => 1,
                'feb' => 2, 'febrero' => 2,
                'mar' => 3, 'marzo' => 3,
                'abr' => 4, 'abril' => 4,
                'may' => 5, 'mayo' => 5,
                'jun' => 6, 'junio' => 6,
                'jul' => 7, 'julio' => 7,
                'ago' => 8, 'agost' => 8, 'agosto' => 8,
                'sep' => 9, 'sept' => 9, 'septiembre' => 9,
                'oct' => 10, 'octubre' => 10,
                'nov' => 11, 'noviembre' => 11,
                'dic' => 12, 'diciembre' => 12,
            ];

            if ($q !== '') {
                $orConditions = [];
                $params = [];
                $types = '';

                // Búsqueda por nombre de producto o talla
                $qLike = '%' . $q . '%';
                $orConditions[] = "p.nombre LIKE ?";
                $params[] = $qLike;
                $types .= 's';

                $orConditions[] = "t.nombre LIKE ?";
                $params[] = $qLike;
                $types .= 's';

                // Búsqueda por ID de orden parseado
                $parsedId = parse_id_orden_produccion($q);
                if ($parsedId > 0) {
                    $orConditions[] = "op.id = ?";
                    $params[] = $parsedId;
                    $types .= 'i';
                }

                if (preg_match('/(?:op\s*[-#]?\s*|orden\s*[-#]?\s*)0*(\d+)/i', $q, $m)) {
                    $extraOpId = (int)$m[1];
                    if ($extraOpId > 0 && $extraOpId !== $parsedId) {
                        $orConditions[] = "op.id = ?";
                        $params[] = $extraOpId;
                        $types .= 'i';
                    }
                }

                // Búsqueda por mes
                $qLower = strtolower($q);
                foreach ($mesesMap as $nomMes => $numMes) {
                    if (strpos($qLower, $nomMes) !== false) {
                        $orConditions[] = "MONTH(op.creado_en) = ?";
                        $params[] = $numMes;
                        $types .= 'i';
                        break;
                    }
                }

                $baseWhereOp = "op.cantidad_a_producir > COALESCE((
                    SELECT SUM(dd.cantidad)
                    FROM devoluciones_detalle dd
                    INNER JOIN devoluciones d ON d.id = dd.devolucion_id
                    WHERE dd.orden_produccion_id = op.id
                      AND d.estado != 'rechazado'
                ), 0)";

                $sql .= " WHERE {$baseWhereOp} AND (" . implode(" OR ", $orConditions) . ") ORDER BY op.id DESC LIMIT 40";
                $st = $conn->prepare($sql);
                if (!empty($params)) {
                    $st->bind_param($types, ...$params);
                }
            } else {
                $baseWhereOp = "op.cantidad_a_producir > COALESCE((
                    SELECT SUM(dd.cantidad)
                    FROM devoluciones_detalle dd
                    INNER JOIN devoluciones d ON d.id = dd.devolucion_id
                    WHERE dd.orden_produccion_id = op.id
                      AND d.estado != 'rechazado'
                ), 0)";
                $st = $conn->prepare($sql . " WHERE {$baseWhereOp} ORDER BY op.id DESC LIMIT 40");
            }

            $st->execute();
            $res = $st->get_result();
            $items = [];
            while ($row = $res->fetch_assoc()) {
                $numOrden = numero_orden_produccion((int)$row['id'], $row['creado_en']);
                $stockVal = (float)$row['stock_inventario'];
                $stockFmt = (fmod($stockVal, 1.0) == 0.0) ? number_format($stockVal, 0) : number_format($stockVal, 2, '.', ',');

                $items[] = [
                    'id' => (int)$row['id'],
                    'text' => $numOrden . ' — ' . $row['producto_nombre'] . ' (' . $row['talla_nombre'] . ') [Inventario: ' . $stockFmt . ' unds]',
                    'numero_orden' => $numOrden,
                    'producto_nombre' => $row['producto_nombre'],
                    'talla_nombre' => $row['talla_nombre'],
                    'cantidad_a_producir' => $row['cantidad_a_producir'],
                    'stock_inventario' => $stockVal,
                    'stock_inventario_fmt' => $stockFmt,
                    'creado_en' => $row['creado_en']
                ];
            }
            $st->close();

            echo json_encode(['results' => $items]);
            break;

        case 'obtener_orden_info':
            $ordenId = (int)($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
            if ($ordenId <= 0) {
                throw new Exception('ID de orden no válido.');
            }

            $st = $conn->prepare("
                SELECT op.id, op.cantidad_a_producir, op.creado_en,
                       p.nombre AS producto_nombre,
                       COALESCE(t.nombre, 'Única') AS talla_nombre,
                       COALESCE(
                           inv.stock_actual,
                           (SELECT COALESCE(SUM(l.cantidad_restante), 0)
                            FROM inventario_lotes l
                            WHERE l.tipo_item = 'producto' 
                              AND (l.tipo_item_id = r.id OR (r.id IS NULL AND l.tipo_item_id = p.id))
                              AND l.cantidad_restante > 0),
                           0
                       ) AS stock_inventario,
                       COALESCE((
                           SELECT SUM(dd.cantidad)
                           FROM devoluciones_detalle dd
                           INNER JOIN devoluciones d ON d.id = dd.devolucion_id
                           WHERE dd.orden_produccion_id = op.id
                             AND d.estado != 'rechazado'
                       ), 0) AS cantidad_devuelta_op
                FROM ordenes_produccion op
                INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                INNER JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
                LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                    AND r.rango_tallas_id = rp.rango_tallas_id 
                    AND r.tipo_produccion_id = rp.tipo_produccion_id
                LEFT JOIN inventario inv ON inv.tipo_item = 'producto' AND inv.tipo_item_id = r.id
                WHERE op.id = ?
                LIMIT 1
            ");
            $st->bind_param('i', $ordenId);
            $st->execute();
            $orden = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$orden) {
                throw new Exception('No se encontró la orden de producción.');
            }

            $cantProducida = (float)$orden['cantidad_a_producir'];
            $cantDevuelta = (float)$orden['cantidad_devuelta_op'];
            $cantDisponible = max(0.00, $cantProducida - $cantDevuelta);

            if ($cantDisponible <= 0.0001) {
                throw new Exception('Esta orden de producción ya ha sido devuelta en su totalidad.');
            }

            $stockVal = (float)$orden['stock_inventario'];
            $stockFmt = (fmod($stockVal, 1.0) == 0.0) ? number_format($stockVal, 0) : number_format($stockVal, 2, '.', ',');

            $orden['numero_orden'] = numero_orden_produccion((int)$orden['id'], $orden['creado_en']);
            $orden['cantidad_disponible'] = $cantDisponible;
            $orden['cantidad_devuelta_previa'] = $cantDevuelta;
            $orden['stock_inventario'] = $stockVal;
            $orden['stock_inventario_fmt'] = $stockFmt;

            echo json_encode([
                'success' => true,
                'orden' => $orden
            ]);
            break;

        case 'buscar_ventas_select2':
            $q = trim($_GET['q'] ?? $_POST['q'] ?? '');

            $baseWhereVenta = "v.estado != 'cancelado' AND EXISTS (
                SELECT 1
                FROM detalle_venta dv
                WHERE dv.venta_id = v.id
                  AND dv.cantidad > COALESCE((
                      SELECT SUM(dd.cantidad)
                      FROM devoluciones_detalle dd
                      INNER JOIN devoluciones d ON d.id = dd.devolucion_id
                      WHERE dd.venta_id = dv.venta_id 
                        AND (dd.detalle_venta_id = dv.id OR (dd.receta_id = dv.producto_id AND dd.detalle_venta_id IS NULL))
                        AND d.estado != 'rechazado'
                  ), 0)
            )";

            $sql = "
                SELECT v.id, v.numero_factura, v.fecha, v.total,
                       cot.codigo_cotizacion
                FROM ventas v
                LEFT JOIN cotizaciones cot ON cot.id_cotizacion = v.cotizacion_id
                WHERE {$baseWhereVenta}
            ";

            if ($q !== '') {
                $qLike = '%' . $q . '%';
                $sql .= " AND (
                    v.numero_factura LIKE ? 
                    OR cot.codigo_cotizacion LIKE ?
                    OR v.id = ?
                ) ORDER BY v.id DESC LIMIT 40";

                $vIdDirecto = (int)preg_replace('/[^0-9]/', '', $q);
                $st = $conn->prepare($sql);
                $st->bind_param('ssi', $qLike, $qLike, $vIdDirecto);
            } else {
                $sql .= " ORDER BY v.id DESC LIMIT 40";
                $st = $conn->prepare($sql);
            }

            $st->execute();
            $res = $st->get_result();
            $items = [];
            while ($row = $res->fetch_assoc()) {
                $numFact = !empty($row['numero_factura']) ? $row['numero_factura'] : ('Venta #' . $row['id']);
                $fecFmt = date('d/m/Y', strtotime($row['fecha']));
                $totFmt = number_format((float)$row['total'], 2, '.', ',');

                $items[] = [
                    'id' => (int)$row['id'],
                    'text' => "{$numFact} [{$fecFmt} - \${$totFmt}]",
                    'numero_factura' => $numFact,
                    'fecha' => $row['fecha'],
                    'total' => $row['total']
                ];
            }
            $st->close();

            echo json_encode(['results' => $items]);
            break;

        case 'obtener_venta_info':
            $ventaId = (int)($_GET['venta_id'] ?? $_POST['venta_id'] ?? 0);
            if ($ventaId <= 0) {
                throw new Exception('ID de venta no válido.');
            }

            $stV = $conn->prepare("
                SELECT v.id, v.numero_factura, v.fecha, v.total
                FROM ventas v
                WHERE v.id = ?
                LIMIT 1
            ");
            $stV->bind_param('i', $ventaId);
            $stV->execute();
            $venta = $stV->get_result()->fetch_assoc();
            $stV->close();

            if (!$venta) {
                throw new Exception('No se encontró la venta seleccionada.');
            }

            $venta['numero_factura'] = !empty($venta['numero_factura']) ? $venta['numero_factura'] : ('Venta #' . $venta['id']);

            // Obtener productos de la venta (detalle_venta) con cálculo de cantidades ya devueltas
            $stDet = $conn->prepare("
                SELECT 
                    dv.id AS detalle_venta_id,
                    dv.producto_id AS receta_id,
                    dv.cantidad AS cantidad_vendida,
                    dv.precio_unitario,
                    dv.subtotal,
                    p.id AS producto_id,
                    p.nombre AS producto_nombre,
                    COALESCE(rt.nombre_rango, 'Única') AS talla_nombre,
                    COALESCE((
                        SELECT SUM(dd.cantidad)
                        FROM devoluciones_detalle dd
                        INNER JOIN devoluciones d ON d.id = dd.devolucion_id
                        WHERE dd.venta_id = dv.venta_id 
                          AND (dd.detalle_venta_id = dv.id OR (dd.receta_id = dv.producto_id AND dd.detalle_venta_id IS NULL))
                          AND d.estado != 'rechazado'
                    ), 0) AS cantidad_devuelta_previa
                FROM detalle_venta dv
                INNER JOIN recetas r ON dv.producto_id = r.id
                INNER JOIN productos p ON r.producto_id = p.id
                LEFT JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                WHERE dv.venta_id = ?
                ORDER BY p.nombre ASC
            ");
            $stDet->bind_param('i', $ventaId);
            $stDet->execute();
            $resDet = $stDet->get_result();
            $items = [];
            while ($row = $resDet->fetch_assoc()) {
                $cantVendida = (float)$row['cantidad_vendida'];
                $cantDevuelta = (float)$row['cantidad_devuelta_previa'];
                $cantDisponible = max(0.00, $cantVendida - $cantDevuelta);

                // Si ya el producto se devolvió completamente, NO debe aparecer para seleccionarlo
                if ($cantDisponible <= 0.0001) {
                    continue;
                }

                $row['cantidad_vendida'] = $cantVendida;
                $row['cantidad_devuelta_previa'] = $cantDevuelta;
                $row['cantidad_disponible'] = $cantDisponible;
                $items[] = $row;
            }
            $stDet->close();

            echo json_encode([
                'success' => true,
                'venta' => $venta,
                'items' => $items
            ]);
            break;

        case 'crear_devolucion':
            $tipoOrigen = trim($_POST['tipo_devolucion'] ?? $_POST['tipo_origen'] ?? 'orden_produccion');
            $cantidad = (float) ($_POST['cantidad'] ?? 1);
            $motivo = trim($_POST['motivo'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            $usuarioId = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
            $accionInventario = 'pendiente_inspeccion';
            $estado = 'pendiente';
            $fechaActual = date('Y-m-d H:i:s');

            if ($cantidad <= 0) {
                throw new Exception('La cantidad a devolver debe ser mayor a 0.');
            }

            if ($motivo === '') {
                throw new Exception('Debe escribir el motivo de la devolución.');
            }

            $conn->begin_transaction();
            try {
                if ($tipoOrigen === 'venta') {
                    $ventaId = (int)($_POST['venta_id'] ?? 0);
                    $detalleVentaId = (int)($_POST['detalle_venta_id'] ?? 0);

                    if ($ventaId <= 0) {
                        throw new Exception('Debe seleccionar la venta a la cual pertenece la devolución.');
                    }
                    if ($detalleVentaId <= 0) {
                        throw new Exception('Debe seleccionar el producto de la venta a devolver.');
                    }

                    // Verificar ítem de la venta
                    $stItem = $conn->prepare("
                        SELECT dv.id, dv.venta_id, dv.producto_id AS receta_id, dv.cantidad AS cantidad_vendida,
                               p.id AS producto_id, p.nombre AS producto_nombre,
                               COALESCE(rt.nombre_rango, 'Única') AS talla_nombre,
                               v.numero_factura
                        FROM detalle_venta dv
                        INNER JOIN ventas v ON v.id = dv.venta_id
                        INNER JOIN recetas r ON dv.producto_id = r.id
                        INNER JOIN productos p ON r.producto_id = p.id
                        LEFT JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                        WHERE dv.id = ? AND dv.venta_id = ?
                        LIMIT 1
                        FOR UPDATE
                    ");
                    $stItem->bind_param('ii', $detalleVentaId, $ventaId);
                    $stItem->execute();
                    $item = $stItem->get_result()->fetch_assoc();
                    $stItem->close();

                    if (!$item) {
                        throw new Exception('No se encontró el producto en la venta seleccionada.');
                    }

                    // Calcular devoluciones previas no rechazadas
                    $stPrev = $conn->prepare("
                        SELECT COALESCE(SUM(dd.cantidad), 0) AS total_previo
                        FROM devoluciones_detalle dd
                        INNER JOIN devoluciones d ON d.id = dd.devolucion_id
                        WHERE dd.venta_id = ? 
                          AND (dd.detalle_venta_id = ? OR (dd.receta_id = ? AND dd.detalle_venta_id IS NULL))
                          AND d.estado != 'rechazado'
                    ");
                    $recetaId = (int)$item['receta_id'];
                    $stPrev->bind_param('iii', $ventaId, $detalleVentaId, $recetaId);
                    $stPrev->execute();
                    $totalPrevio = (float)($stPrev->get_result()->fetch_assoc()['total_previo'] ?? 0);
                    $stPrev->close();

                    $disponible = max(0.00, (float)$item['cantidad_vendida'] - $totalPrevio);
                    if ($cantidad > $disponible) {
                        throw new Exception("La cantidad a devolver ({$cantidad}) supera la cantidad disponible ({$disponible}) para devolver de este producto en la venta.");
                    }

                    $codigoTemp = 'TMP-DEV-' . bin2hex(random_bytes(4));

                    // Insertar cabecera de devolución tipo venta (sin cliente)
                    $stDev = $conn->prepare("
                        INSERT INTO devoluciones (codigo_devolucion, fecha, tipo_origen, venta_id, motivo, descripcion_motivo, accion_inventario, estado, usuario_id, observaciones)
                        VALUES (?, ?, 'venta', ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stDev->bind_param('ssissssis', $codigoTemp, $fechaActual, $ventaId, $motivo, $motivo, $accionInventario, $estado, $usuarioId, $observaciones);
                    $stDev->execute();
                    $devolucionId = $conn->insert_id;
                    $stDev->close();

                    $codigoDevolucion = generarCodigoDevolucionSimple($devolucionId, $fechaActual);
                    $conn->query("UPDATE devoluciones SET codigo_devolucion = '{$codigoDevolucion}' WHERE id = {$devolucionId}");

                    // Insertar detalle de devolución con referencias a la venta y producto
                    $productoId = (int)$item['producto_id'];
                    $stDet = $conn->prepare("
                        INSERT INTO devoluciones_detalle (devolucion_id, orden_produccion_id, venta_id, detalle_venta_id, receta_id, producto_id, cantidad, estado_unidad_posterior, observaciones)
                        VALUES (?, NULL, ?, ?, ?, ?, ?, 'pendiente_inspeccion', ?)
                    ");
                    $stDet->bind_param('iiiiids', $devolucionId, $ventaId, $detalleVentaId, $recetaId, $productoId, $cantidad, $motivo);
                    $stDet->execute();
                    $stDet->close();

                    $numFact = !empty($item['numero_factura']) ? $item['numero_factura'] : ('Venta #' . $ventaId);
                    Auditoria::registrar(
                        $conn,
                        "Registro de devolución {$codigoDevolucion} por {$cantidad} unds del producto '{$item['producto_nombre']}' de la Venta Factura {$numFact}. Motivo: {$motivo}. Enviado a Material Rechazado para su inspección.",
                        'Devoluciones'
                    );

                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => "Devolución {$codigoDevolucion} registrada correctamente y enviada al módulo de Material Rechazado para su inspección.",
                        'id' => $devolucionId,
                        'codigo_devolucion' => $codigoDevolucion
                    ]);

                } else {
                    // TIPO ORDEN DE PRODUCCIÓN
                    $ordenId = (int) ($_POST['orden_produccion_id'] ?? 0);

                    if ($ordenId <= 0) {
                        throw new Exception('Debe seleccionar la orden de producción a devolver.');
                    }

                    // Obtener datos de la orden de producción
                    $stOrd = $conn->prepare("
                        SELECT op.id, op.cantidad_a_producir, op.creado_en,
                               p.id AS producto_id, p.nombre AS producto_nombre,
                               COALESCE(t.nombre, 'Única') AS talla_nombre,
                               COALESCE(r.id, 0) AS receta_id
                        FROM ordenes_produccion op
                        INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                        INNER JOIN productos p ON p.id = rp.producto_id
                        LEFT JOIN tallas t ON t.id = op.talla_id
                        LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                            AND r.rango_tallas_id = rp.rango_tallas_id 
                            AND r.tipo_produccion_id = rp.tipo_produccion_id
                        WHERE op.id = ? FOR UPDATE
                    ");
                    $stOrd->bind_param('i', $ordenId);
                    $stOrd->execute();
                    $orden = $stOrd->get_result()->fetch_assoc();
                    $stOrd->close();

                    if (!$orden) {
                        throw new Exception('No se encontró la orden de producción seleccionada.');
                    }

                    $recetaId = (int)$orden['receta_id'];
                    $productoId = (int)$orden['producto_id'];

                    $codigoTemp = 'TMP-DEV-' . bin2hex(random_bytes(4));

                    // Insertar cabecera de devolución tipo orden_produccion (sin cliente)
                    $stDev = $conn->prepare("
                        INSERT INTO devoluciones (codigo_devolucion, fecha, tipo_origen, venta_id, motivo, descripcion_motivo, accion_inventario, estado, usuario_id, observaciones)
                        VALUES (?, ?, 'orden_produccion', NULL, ?, ?, ?, ?, ?, ?)
                    ");
                    $stDev->bind_param('ssssssis', $codigoTemp, $fechaActual, $motivo, $motivo, $accionInventario, $estado, $usuarioId, $observaciones);
                    $stDev->execute();
                    $devolucionId = $conn->insert_id;
                    $stDev->close();

                    $codigoDevolucion = generarCodigoDevolucionSimple($devolucionId, $fechaActual);
                    $conn->query("UPDATE devoluciones SET codigo_devolucion = '{$codigoDevolucion}' WHERE id = {$devolucionId}");

                    // Insertar detalle de devolución
                    $stDet = $conn->prepare("
                        INSERT INTO devoluciones_detalle (devolucion_id, orden_produccion_id, venta_id, detalle_venta_id, receta_id, producto_id, cantidad, estado_unidad_posterior, observaciones)
                        VALUES (?, ?, NULL, NULL, ?, ?, ?, 'pendiente_inspeccion', ?)
                    ");
                    $stDet->bind_param('iiiids', $devolucionId, $ordenId, $recetaId, $productoId, $cantidad, $motivo);
                    $stDet->execute();
                    $stDet->close();

                    $numeroOrden = numero_orden_produccion((int)$orden['id'], $orden['creado_en']);
                    Auditoria::registrar(
                        $conn,
                        "Registro de devolución {$codigoDevolucion} por {$cantidad} para el producto '{$orden['producto_nombre']}' de la OP {$numeroOrden}. Motivo: {$motivo}. Enviado al módulo de Material Rechazado para su validación de estado.",
                        'Devoluciones'
                    );

                    $conn->commit();

                    echo json_encode([
                        'success' => true,
                        'message' => "Devolución {$codigoDevolucion} registrada correctamente y enviada al módulo de Material Rechazado para su inspección.",
                        'id' => $devolucionId,
                        'codigo_devolucion' => $codigoDevolucion
                    ]);
                }
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'listar_devoluciones':
            $busqueda = trim($_GET['busqueda'] ?? $_POST['busqueda'] ?? '');
            $fechaDesde = trim($_GET['fecha_desde'] ?? $_POST['fecha_desde'] ?? '');
            $fechaHasta = trim($_GET['fecha_hasta'] ?? $_POST['fecha_hasta'] ?? '');
            $filtroEstado = trim($_GET['estado'] ?? $_POST['estado'] ?? '');
            $filtroTipo = trim($_GET['tipo_origen'] ?? $_POST['tipo_origen'] ?? '');
            $pagina = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
            $porPagina = 15;

            $whereParts = ["1=1"];
            $params = [];
            $types = "";

            if ($busqueda !== '') {
                $whereParts[] = "(
                    d.codigo_devolucion LIKE ? 
                    OR p_op.nombre LIKE ? 
                    OR p_v.nombre LIKE ?
                    OR p_dd.nombre LIKE ?
                    OR d.motivo LIKE ?
                    OR v.numero_factura LIKE ?
                    OR op.id = ?
                )";
                $likeB = '%' . $busqueda . '%';
                $opNumInt = (int) parse_id_orden_produccion($busqueda);
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $opNumInt;
                $types .= "ssssssi";
            }

            if ($filtroEstado !== '') {
                $whereParts[] = "d.estado = ?";
                $params[] = $filtroEstado;
                $types .= "s";
            }

            if ($filtroTipo !== '') {
                $whereParts[] = "COALESCE(d.tipo_origen, 'orden_produccion') = ?";
                $params[] = $filtroTipo;
                $types .= "s";
            }

            if ($fechaDesde !== '') {
                $whereParts[] = "DATE(d.fecha) >= ?";
                $params[] = $fechaDesde;
                $types .= "s";
            }

            if ($fechaHasta !== '') {
                $whereParts[] = "DATE(d.fecha) <= ?";
                $params[] = $fechaHasta;
                $types .= "s";
            }

            $whereSql = implode(" AND ", $whereParts);

            // Contar total de registros
            $sqlCount = "
                SELECT COUNT(DISTINCT d.id) AS total
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p_op ON p_op.id = rp.producto_id
                LEFT JOIN ventas v ON v.id = COALESCE(d.venta_id, dd.venta_id)
                LEFT JOIN recetas r_v ON r_v.id = dd.receta_id
                LEFT JOIN productos p_v ON p_v.id = r_v.producto_id
                LEFT JOIN productos p_dd ON p_dd.id = dd.producto_id
                WHERE {$whereSql}
            ";
            $stmtC = $conn->prepare($sqlCount);
            if (!empty($params)) {
                $stmtC->bind_param($types, ...$params);
            }
            $stmtC->execute();
            $totalFilas = (int) ($stmtC->get_result()->fetch_assoc()['total'] ?? 0);
            $stmtC->close();

            $totalPaginas = max(1, (int) ceil($totalFilas / $porPagina));
            $offset = ($pagina - 1) * $porPagina;

            // Consultar datos de la página
            $sqlData = "
                SELECT d.id, d.codigo_devolucion, d.fecha, d.motivo, d.descripcion_motivo,
                       d.accion_inventario, COALESCE(d.estado, 'pendiente') AS estado,
                       d.fecha_inspeccion, d.usuario_inspeccion_id, d.motivo_inspeccion,
                       COALESCE(d.tipo_origen, 'orden_produccion') AS tipo_origen,
                       d.observaciones, d.creado_en,
                       u_insp.username AS inspector_nombre,
                       dd.orden_produccion_id, dd.venta_id, dd.detalle_venta_id,
                       COALESCE(dd.cantidad, 1.00) AS cantidad,
                       COALESCE(p_op.nombre, p_v.nombre, p_dd.nombre, 'Producto') AS producto_nombre,
                       COALESCE(t_op.nombre, rt_v.nombre_rango, 'Única') AS talla_nombre,
                       op.creado_en AS orden_creado_en,
                       v.numero_factura, v.fecha AS venta_fecha
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p_op ON p_op.id = rp.producto_id
                LEFT JOIN tallas t_op ON t_op.id = op.talla_id
                LEFT JOIN ventas v ON v.id = COALESCE(d.venta_id, dd.venta_id)
                LEFT JOIN recetas r_v ON r_v.id = dd.receta_id
                LEFT JOIN productos p_v ON p_v.id = r_v.producto_id
                LEFT JOIN rangos_tallas rt_v ON rt_v.id = r_v.rango_tallas_id
                LEFT JOIN productos p_dd ON p_dd.id = dd.producto_id
                LEFT JOIN users u_insp ON u_insp.id = d.usuario_inspeccion_id
                WHERE {$whereSql}
                GROUP BY d.id
                ORDER BY d.fecha DESC, d.id DESC
                LIMIT ? OFFSET ?
            ";
            $stmtD = $conn->prepare($sqlData);
            $typesPage = $types . "ii";
            $paramsPage = array_merge($params, [$porPagina, $offset]);
            $stmtD->bind_param($typesPage, ...$paramsPage);
            $stmtD->execute();
            $res = $stmtD->get_result();

            $devoluciones = [];
            while ($row = $res->fetch_assoc()) {
                if ($row['tipo_origen'] === 'venta' || (!empty($row['venta_id']) && empty($row['orden_produccion_id']))) {
                    $row['tipo_origen'] = 'venta';
                    $row['numero_factura'] = !empty($row['numero_factura']) ? $row['numero_factura'] : ('Venta #' . ($row['venta_id'] ?? ''));
                    $row['origen_etiqueta'] = 'Factura: ' . $row['numero_factura'];
                    $row['numero_orden'] = '-';
                } else {
                    $row['tipo_origen'] = 'orden_produccion';
                    $row['numero_orden'] = !empty($row['orden_produccion_id']) ? numero_orden_produccion((int)$row['orden_produccion_id'], $row['orden_creado_en']) : '-';
                    $row['origen_etiqueta'] = 'OP: ' . $row['numero_orden'];
                    $row['numero_factura'] = '-';
                }
                $devoluciones[] = $row;
            }
            $stmtD->close();

            echo json_encode([
                'success' => true,
                'devoluciones' => $devoluciones,
                'paginacion' => [
                    'pagina_actual' => $pagina,
                    'total_paginas' => $totalPaginas,
                    'total_registros' => $totalFilas,
                    'por_pagina' => $porPagina
                ]
            ]);
            break;

        case 'obtener_detalle':
            $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new Exception('ID de devolución inválido.');
            }

            $sql = "
                SELECT d.id, d.codigo_devolucion, d.fecha, d.motivo, d.descripcion_motivo,
                       d.accion_inventario, COALESCE(d.estado, 'pendiente') AS estado,
                       d.fecha_inspeccion, d.usuario_inspeccion_id, d.motivo_inspeccion,
                       COALESCE(d.tipo_origen, 'orden_produccion') AS tipo_origen,
                       d.observaciones, d.creado_en,
                       u_reg.username AS usuario_registro_nombre,
                       u_insp.username AS inspector_nombre,
                       dd.orden_produccion_id, dd.venta_id, dd.detalle_venta_id,
                       COALESCE(dd.cantidad, 1.00) AS cantidad,
                       COALESCE(p_op.id, p_v.id, dd.producto_id) AS producto_id,
                       COALESCE(p_op.nombre, p_v.nombre, p_dd.nombre, 'Producto') AS producto_nombre,
                       COALESCE(p_op.categoria, p_v.categoria, p_dd.categoria, '') AS categoria,
                       COALESCE(t_op.nombre, rt_v.nombre_rango, 'Única') AS talla_nombre,
                       op.creado_en AS orden_creado_en, op.estado AS estado_orden,
                       v.numero_factura, v.fecha AS venta_fecha
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p_op ON p_op.id = rp.producto_id
                LEFT JOIN tallas t_op ON t_op.id = op.talla_id
                LEFT JOIN ventas v ON v.id = COALESCE(d.venta_id, dd.venta_id)
                LEFT JOIN recetas r_v ON r_v.id = dd.receta_id
                LEFT JOIN productos p_v ON p_v.id = r_v.producto_id
                LEFT JOIN rangos_tallas rt_v ON rt_v.id = r_v.rango_tallas_id
                LEFT JOIN productos p_dd ON p_dd.id = dd.producto_id
                LEFT JOIN users u_reg ON u_reg.id = d.usuario_id
                LEFT JOIN users u_insp ON u_insp.id = d.usuario_inspeccion_id
                WHERE d.id = ?
                LIMIT 1
            ";
            $st = $conn->prepare($sql);
            $st->bind_param('i', $id);
            $st->execute();
            $det = $st->get_result()->fetch_assoc();
            $st->close();

            if (!$det) {
                throw new Exception('Devolución no encontrada.');
            }

            if ($det['tipo_origen'] === 'venta' || (!empty($det['venta_id']) && empty($det['orden_produccion_id']))) {
                $det['tipo_origen'] = 'venta';
                $numFact = !empty($det['numero_factura']) ? $det['numero_factura'] : ('Venta #' . ($det['venta_id'] ?? ''));
                $det['numero_factura'] = $numFact;
                $det['origen_etiqueta'] = 'Factura: ' . $numFact;
                $det['numero_orden'] = '-';
            } else {
                $det['tipo_origen'] = 'orden_produccion';
                $det['numero_orden'] = !empty($det['orden_produccion_id']) ? numero_orden_produccion((int)$det['orden_produccion_id'], $det['orden_creado_en']) : '-';
                $det['origen_etiqueta'] = 'OP: ' . $det['numero_orden'];
                $det['numero_factura'] = '-';
            }

            echo json_encode([
                'success' => true,
                'devolucion' => $det
            ]);
            break;

        case 'aprobar_buen_estado':
            $devolucionId = (int) ($_POST['devolucion_id'] ?? 0);
            $observacionesInspeccion = trim($_POST['observaciones_inspeccion'] ?? '');
            $usuarioId = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;

            if ($devolucionId <= 0) {
                throw new Exception('ID de devolución inválido.');
            }

            $conn->begin_transaction();
            try {
                $st = $conn->prepare("
                    SELECT d.id, d.codigo_devolucion, d.estado, d.motivo, COALESCE(d.tipo_origen, 'orden_produccion') AS tipo_origen,
                           dd.id AS detalle_id, dd.orden_produccion_id, dd.venta_id, dd.detalle_venta_id,
                           dd.receta_id AS det_receta_id, dd.producto_id AS det_producto_id,
                           COALESCE(dd.cantidad, 1.00) AS cantidad,
                           op.id AS op_id, op.creado_en AS orden_creado_en,
                           v.id AS v_id, v.numero_factura,
                           COALESCE(p_op.id, p_v.id, dd.producto_id) AS producto_id,
                           COALESCE(p_op.nombre, p_v.nombre, 'Producto') AS producto_nombre,
                           COALESCE(dd.receta_id, r_op.id, r_v.id, 0) AS final_receta_id
                    FROM devoluciones d
                    INNER JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                    LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                    LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                    LEFT JOIN productos p_op ON p_op.id = rp.producto_id
                    LEFT JOIN recetas r_op ON r_op.producto_id = rp.producto_id 
                        AND r_op.rango_tallas_id = rp.rango_tallas_id 
                        AND r_op.tipo_produccion_id = rp.tipo_produccion_id
                    LEFT JOIN ventas v ON v.id = COALESCE(d.venta_id, dd.venta_id)
                    LEFT JOIN recetas r_v ON r_v.id = dd.receta_id
                    LEFT JOIN productos p_v ON p_v.id = r_v.producto_id
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

                $recetaId = (int) $dev['final_receta_id'];
                $cantidad = (float) $dev['cantidad'];
                $codigoDev = $dev['codigo_devolucion'];
                $fechaActual = date('Y-m-d H:i:s');

                if ($dev['tipo_origen'] === 'venta' || (!empty($dev['venta_id']) && empty($dev['orden_produccion_id']))) {
                    $origenTexto = !empty($dev['numero_factura']) ? 'Factura ' . $dev['numero_factura'] : ('Venta #' . ($dev['venta_id'] ?? $dev['v_id']));
                } else {
                    $origenTexto = 'OP ' . (!empty($dev['op_id']) ? numero_orden_produccion((int)$dev['op_id'], $dev['orden_creado_en']) : '');
                }

                if ($recetaId <= 0) {
                    $prodId = (int)$dev['producto_id'];
                    $stR = $conn->prepare("SELECT id FROM recetas WHERE producto_id = ? LIMIT 1");
                    $stR->bind_param('i', $prodId);
                    $stR->execute();
                    $rRow = $stR->get_result()->fetch_assoc();
                    $stR->close();
                    if ($rRow) {
                        $recetaId = (int)$rRow['id'];
                    } else {
                        $conn->query("INSERT INTO recetas (producto_id, rango_tallas_id, tipo_produccion_id) VALUES ({$prodId}, 1, 1)");
                        $recetaId = (int)$conn->insert_id;
                    }
                }

                $obsInventario = "Reingreso por devolución {$codigoDev} ({$cantidad} unds) de {$origenTexto} ({$dev['producto_nombre']}) - Validado en BUEN ESTADO.";
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

                $motivoInspeccionFinal = $observacionesInspeccion !== '' ? $observacionesInspeccion : 'Producto validado en buen estado para stock.';
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
                        lote_id = ?,
                        receta_id = ?
                    WHERE id = ?
                ");
                $detalleId = (int)$dev['detalle_id'];
                $stUpdDet->bind_param('iii', $loteId, $recetaId, $detalleId);
                $stUpdDet->execute();
                $stUpdDet->close();

                Auditoria::registrar(
                    $conn,
                    "Inspección de devolución {$codigoDev}: VALIDADO EN BUEN ESTADO. Reingresaron {$cantidad} al inventario (Lote: {$numeroLote}) origen {$origenTexto}.",
                    'Devoluciones'
                );

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "El producto fue validado en BUEN ESTADO y reingresado exitosamente al inventario de stock.",
                    'numero_lote' => $numeroLote
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'rechazar_material':
            $devolucionId = (int) ($_POST['devolucion_id'] ?? 0);
            $motivoRechazo = trim($_POST['motivo_rechazo'] ?? '');
            $usuarioId = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;

            if ($devolucionId <= 0) {
                throw new Exception('ID de devolución inválido.');
            }

            $conn->begin_transaction();
            try {
                $st = $conn->prepare("
                    SELECT d.id, d.codigo_devolucion, d.estado, d.motivo, COALESCE(d.tipo_origen, 'orden_produccion') AS tipo_origen,
                           dd.id AS detalle_id, dd.orden_produccion_id, dd.venta_id,
                           dd.receta_id AS det_receta_id, dd.producto_id AS det_producto_id,
                           COALESCE(dd.cantidad, 1.00) AS cantidad,
                           op.id AS op_id, op.creado_en AS orden_creado_en,
                           v.id AS v_id, v.numero_factura,
                           COALESCE(p_op.id, p_v.id, dd.producto_id) AS producto_id,
                           COALESCE(p_op.nombre, p_v.nombre, 'Producto') AS producto_nombre,
                           COALESCE(dd.receta_id, r_op.id, r_v.id, 0) AS final_receta_id
                    FROM devoluciones d
                    INNER JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                    LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                    LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                    LEFT JOIN productos p_op ON p_op.id = rp.producto_id
                    LEFT JOIN recetas r_op ON r_op.producto_id = rp.producto_id 
                        AND r_op.rango_tallas_id = rp.rango_tallas_id 
                        AND r_op.tipo_produccion_id = rp.tipo_produccion_id
                    LEFT JOIN ventas v ON v.id = COALESCE(d.venta_id, dd.venta_id)
                    LEFT JOIN recetas r_v ON r_v.id = dd.receta_id
                    LEFT JOIN productos p_v ON p_v.id = r_v.producto_id
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
                $fechaActual = date('Y-m-d H:i:s');
                if ($motivoRechazo === '') {
                    $motivoRechazo = !empty($dev['motivo']) ? $dev['motivo'] : 'Material rechazado / no conforme';
                }

                $esOrdenProduccion = ($dev['tipo_origen'] !== 'venta') && !(!empty($dev['venta_id']) && empty($dev['orden_produccion_id']));

                if (!$esOrdenProduccion) {
                    $origenTexto = !empty($dev['numero_factura']) ? 'Factura ' . $dev['numero_factura'] : ('Venta #' . ($dev['venta_id'] ?? $dev['v_id']));
                } else {
                    $origenTexto = 'OP ' . (!empty($dev['op_id']) ? numero_orden_produccion((int)$dev['op_id'], $dev['orden_creado_en']) : '');
                }

                // Si la devolución es de una Orden de Producción, restar del inventario de producto terminado
                $salidaInfo = null;
                if ($esOrdenProduccion) {
                    $recetaId = (int) $dev['final_receta_id'];

                    // Resolver receta_id si no se encontró en la consulta
                    if ($recetaId <= 0) {
                        $prodId = (int)$dev['producto_id'];
                        $stR = $conn->prepare("SELECT id FROM recetas WHERE producto_id = ? LIMIT 1");
                        $stR->bind_param('i', $prodId);
                        $stR->execute();
                        $rRow = $stR->get_result()->fetch_assoc();
                        $stR->close();
                        if ($rRow) {
                            $recetaId = (int)$rRow['id'];
                        }
                    }

                    if ($recetaId > 0) {
                        // Verificar que hay stock disponible antes de intentar restar
                        $stockDisponible = InventarioLotes::stockDisponible($conn, 'producto', $recetaId);
                        if ($stockDisponible >= $cantidad) {
                            $obsSalida = "Salida por rechazo de devolución {$codigoDev} ({$cantidad} unds) de {$origenTexto} ({$dev['producto_nombre']}). Material rechazado/descartado. Motivo: {$motivoRechazo}";

                            $salidaInfo = InventarioLotes::registrarSalida($conn, [
                                'tipo_item' => 'producto',
                                'tipo_item_id' => $recetaId,
                                'cantidad' => $cantidad,
                                'origen' => 'devolucion_rechazo',
                                'origen_id' => $devolucionId,
                                'observaciones' => $obsSalida,
                                'tipo_movimiento' => 'devolucion',
                            ]);
                        }
                        // Si no hay stock suficiente, se marca como rechazado sin descontar
                    }
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

                $auditoriaMsg = "Inspección de devolución {$codigoDev}: RECHAZADA Y DESCARTADA ({$cantidad} unds de {$origenTexto}). Motivo: {$motivoRechazo}.";
                if ($salidaInfo !== null) {
                    $auditoriaMsg .= " Se restaron {$cantidad} unds del inventario de producto terminado.";
                } else if ($esOrdenProduccion) {
                    $auditoriaMsg .= " No se pudo restar del inventario (stock insuficiente o receta no encontrada).";
                } else {
                    $auditoriaMsg .= " No ingresó al inventario.";
                }

                Auditoria::registrar(
                    $conn,
                    $auditoriaMsg,
                    'Devoluciones'
                );

                $conn->commit();

                $mensajeRespuesta = "El producto fue marcado como RECHAZADO.";
                if ($salidaInfo !== null) {
                    $mensajeRespuesta .= " Se restaron {$cantidad} unidad(es) del inventario de producto terminado.";
                } else if ($esOrdenProduccion && isset($stockDisponible) && $stockDisponible < $cantidad) {
                    $mensajeRespuesta .= " No se pudo descontar del inventario por stock insuficiente.";
                }

                echo json_encode([
                    'success' => true,
                    'message' => $mensajeRespuesta
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
