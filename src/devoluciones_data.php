<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/orden_numero.php';
require_once __DIR__ . '/../lib/InventarioLotes.php';
require_once __DIR__ . '/../lib/Auditoria.php';

require_once __DIR__ . '/../lib/DevolucionesSchema.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

header('Content-Type: application/json; charset=utf-8');

DevolucionesSchema::asegurarTablas($conn);

function generarCodigoDevolucionSimple(int $devolucionId, ?string $fecha = null): string {
    $nOrden = numero_orden_produccion($devolucionId, $fecha);
    return $nOrden !== '' ? 'DEV-' . $nOrden : sprintf('DEV-%04d', $devolucionId);
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'buscar_ordenes_select2':
            $q = trim($_GET['q'] ?? $_POST['q'] ?? '');

            $sql = "
                SELECT op.id, op.cantidad_a_producir, op.creado_en,
                       p.nombre AS producto_nombre,
                       COALESCE(t.nombre, 'Única') AS talla_nombre
                FROM ordenes_produccion op
                INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                INNER JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
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

                $sql .= " WHERE (" . implode(" OR ", $orConditions) . ") ORDER BY op.id DESC LIMIT 40";
                $st = $conn->prepare($sql);
                if (!empty($params)) {
                    $st->bind_param($types, ...$params);
                }
            } else {
                $st = $conn->prepare($sql . " ORDER BY op.id DESC LIMIT 40");
            }

            $st->execute();
            $res = $st->get_result();
            $items = [];
            while ($row = $res->fetch_assoc()) {
                $numOrden = numero_orden_produccion((int)$row['id'], $row['creado_en']);
                $items[] = [
                    'id' => (int)$row['id'],
                    'text' => $numOrden . ' — ' . $row['producto_nombre'] . ' (' . $row['talla_nombre'] . ')',
                    'numero_orden' => $numOrden,
                    'producto_nombre' => $row['producto_nombre'],
                    'talla_nombre' => $row['talla_nombre'],
                    'cantidad_a_producir' => $row['cantidad_a_producir'],
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
                       COALESCE(t.nombre, 'Única') AS talla_nombre
                FROM ordenes_produccion op
                INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                INNER JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
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

            $orden['numero_orden'] = numero_orden_produccion((int)$orden['id'], $orden['creado_en']);

            echo json_encode([
                'success' => true,
                'orden' => $orden
            ]);
            break;

        case 'crear_devolucion':
            $ordenId = (int) ($_POST['orden_produccion_id'] ?? 0);
            $cantidad = (float) ($_POST['cantidad'] ?? 1);
            $clienteId = !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null;
            $motivo = trim($_POST['motivo'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            $usuarioId = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
            $accionInventario = 'reingresar_stock';

            if ($ordenId <= 0) {
                throw new Exception('Debe seleccionar la orden de producción a devolver.');
            }

            if ($cantidad <= 0) {
                throw new Exception('La cantidad a devolver debe ser mayor a 0.');
            }

            if ($motivo === '') {
                throw new Exception('Debe escribir el motivo de la devolución.');
            }

            $conn->begin_transaction();
            try {
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

                $codigoTemp = 'TMP-DEV-' . bin2hex(random_bytes(4));
                $fechaActual = date('Y-m-d H:i:s');

                // Insertar cabecera de devolución
                $stDev = $conn->prepare("
                    INSERT INTO devoluciones (codigo_devolucion, fecha, cliente_id, motivo, descripcion_motivo, accion_inventario, usuario_id, observaciones)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stDev->bind_param('ssisssis', $codigoTemp, $fechaActual, $clienteId, $motivo, $motivo, $accionInventario, $usuarioId, $observaciones);
                $stDev->execute();
                $devolucionId = $conn->insert_id;
                $stDev->close();

                $codigoDevolucion = generarCodigoDevolucionSimple($devolucionId, $fechaActual);
                $conn->query("UPDATE devoluciones SET codigo_devolucion = '{$codigoDevolucion}' WHERE id = {$devolucionId}");

                // Insertar detalle de devolución
                $estadoPosterior = 'devuelto_stock';
                $stDet = $conn->prepare("
                    INSERT INTO devoluciones_detalle (devolucion_id, orden_produccion_id, cantidad, estado_unidad_posterior, observaciones)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stDet->bind_param('iidss', $devolucionId, $ordenId, $cantidad, $estadoPosterior, $motivo);
                $stDet->execute();
                $stDet->close();

                // SUMAR STOCK AL INVENTARIO DE PRODUCTOS TERMINADOS
                $recetaId = (int) $orden['receta_id'];
                $numeroOrden = numero_orden_produccion((int)$orden['id'], $orden['creado_en']);
                InventarioLotes::registrarEntrada($conn, [
                    'tipo_item' => 'producto',
                    'tipo_item_id' => $recetaId,
                    'cantidad' => $cantidad,
                    'origen' => 'devolucion',
                    'origen_id' => $devolucionId,
                    'observaciones' => "Reingreso por devolución {$codigoDevolucion} ({$cantidad} unds) de OP {$numeroOrden} ({$orden['producto_nombre']}) - Motivo: {$motivo}",
                    'tipo_movimiento' => 'devolucion',
                ]);

                Auditoria::registrar(
                    $conn,
                    "Registro de devolución {$codigoDevolucion} por {$cantidad} unds para el producto '{$orden['producto_nombre']}' de la OP #{$ordenId}. Motivo: {$motivo}. Reingreso a stock completado.",
                    'Devoluciones'
                );

                $conn->commit();

                echo json_encode([
                    'success' => true,
                    'message' => "Devolución {$codigoDevolucion} registrada correctamente.",
                    'id' => $devolucionId,
                    'codigo_devolucion' => $codigoDevolucion
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'listar_devoluciones':
            $busqueda = trim($_GET['busqueda'] ?? $_POST['busqueda'] ?? '');
            $fechaDesde = trim($_GET['fecha_desde'] ?? $_POST['fecha_desde'] ?? '');
            $fechaHasta = trim($_GET['fecha_hasta'] ?? $_POST['fecha_hasta'] ?? '');
            $pagina = max(1, (int) ($_GET['page'] ?? $_POST['page'] ?? 1));
            $porPagina = 15;

            $whereParts = ["1=1"];
            $params = [];
            $types = "";

            if ($busqueda !== '') {
                $whereParts[] = "(
                    d.codigo_devolucion LIKE ? 
                    OR p.nombre LIKE ? 
                    OR c.nombre LIKE ? 
                    OR c.numero_documento LIKE ?
                    OR d.motivo LIKE ?
                    OR op.id = ?
                )";
                $likeB = '%' . $busqueda . '%';
                $opNumInt = (int) parse_id_orden_produccion($busqueda);
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $likeB;
                $params[] = $opNumInt;
                $types .= "sssssi";
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
                LEFT JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN clientes c ON c.id = d.cliente_id
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
                       d.accion_inventario, d.observaciones, d.creado_en,
                       c.id AS cliente_id, c.nombre AS cliente_nombre, c.numero_documento AS cliente_documento,
                       dd.orden_produccion_id, COALESCE(dd.cantidad, 1.00) AS cantidad,
                       p.nombre AS producto_nombre,
                       COALESCE(t.nombre, 'Sin talla') AS talla_nombre,
                       op.creado_en AS orden_creado_en
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
                LEFT JOIN clientes c ON c.id = d.cliente_id
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
                if (!empty($row['orden_produccion_id'])) {
                    $row['numero_orden'] = numero_orden_produccion((int)$row['orden_produccion_id'], $row['orden_creado_en']);
                } else {
                    $row['numero_orden'] = '-';
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
                       d.accion_inventario, d.observaciones, d.creado_en,
                       c.id AS cliente_id, c.nombre AS cliente_nombre, c.numero_documento AS cliente_documento,
                       c.telefono AS cliente_telefono, c.email AS cliente_email,
                       dd.orden_produccion_id, COALESCE(dd.cantidad, 1.00) AS cantidad,
                       p.nombre AS producto_nombre, p.categoria,
                       COALESCE(t.nombre, 'Sin talla') AS talla_nombre,
                       op.id AS orden_produccion_id, op.creado_en AS orden_creado_en, op.estado AS estado_orden
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
                LEFT JOIN clientes c ON c.id = d.cliente_id
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

            if (!empty($det['orden_produccion_id'])) {
                $det['numero_orden'] = numero_orden_produccion((int)$det['orden_produccion_id'], $det['orden_creado_en']);
            } else {
                $det['numero_orden'] = '-';
            }

            echo json_encode([
                'success' => true,
                'devolucion' => $det
            ]);
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
