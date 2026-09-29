<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/orden_numero.php';
require_once __DIR__ . '/../lib/InventarioLotes.php';
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/OrdenProduccionUnidades.php';

if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

OrdenProduccionUnidades::asegurarTablas($conn);

header('Content-Type: application/json; charset=utf-8');

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'buscar_unidad':
            $codigo = trim($_GET['codigo'] ?? $_POST['codigo'] ?? '');
            if ($codigo === '') {
                throw new Exception('Debe ingresar un número identificador.');
            }

            $unidad = OrdenProduccionUnidades::buscarUnidadPorIdentificador($conn, $codigo);
            if (!$unidad) {
                echo json_encode([
                    'success' => false,
                    'message' => 'No se encontró ningún producto terminado con el identificador: ' . htmlspecialchars($codigo)
                ]);
                exit;
            }

            // Consultar historial de devoluciones previas si tiene
            $stHist = $conn->prepare("
                SELECT d.id, d.codigo_devolucion, d.fecha, d.motivo, d.accion_inventario
                FROM devoluciones_detalle dd
                INNER JOIN devoluciones d ON d.id = dd.devolucion_id
                WHERE dd.unidad_id = ?
                ORDER BY d.fecha DESC
            ");
            $stHist->bind_param('i', $unidad['unidad_id']);
            $stHist->execute();
            $histRes = $stHist->get_result();
            $historial = [];
            while ($h = $histRes->fetch_assoc()) {
                $historial[] = $h;
            }
            $stHist->close();

            $unidad['historial_devoluciones'] = $historial;

            echo json_encode([
                'success' => true,
                'unidad' => $unidad
            ]);
            break;

        case 'buscar_unidades_select2':
            $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
            
            OrdenProduccionUnidades::asegurarTablas($conn);
            OrdenProduccionUnidades::sincronizarTodasLasOrdenes($conn);

            $sql = "
                SELECT u.id AS unidad_id, u.numero_identificador, u.numero_secuencia, u.estado,
                       op.id AS orden_id, op.creado_en AS orden_fecha,
                       COALESCE(p.nombre, 'Producto') AS producto_nombre,
                       COALESCE(t.nombre, 'Única') AS talla_nombre
                FROM ordenes_produccion_unidades u
                INNER JOIN ordenes_produccion op ON op.id = u.orden_produccion_id
                LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = u.talla_id
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

                // 1. Identificador de pieza, producto, talla
                $qLike = '%' . $q . '%';
                $orConditions[] = "u.numero_identificador LIKE ?";
                $params[] = $qLike;
                $types .= 's';

                $orConditions[] = "p.nombre LIKE ?";
                $params[] = $qLike;
                $types .= 's';

                $orConditions[] = "t.nombre LIKE ?";
                $params[] = $qLike;
                $types .= 's';

                // 2. Número de orden extraído (ej. "01-agost", "1-agost-26", "op 5", "5")
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

                // 3. Mes en la fecha de la orden si busca por mes (ej. "agost", "sept", "enero")
                $qLower = strtolower($q);
                foreach ($mesesMap as $nomMes => $numMes) {
                    if (strpos($qLower, $nomMes) !== false) {
                        $orConditions[] = "MONTH(op.creado_en) = ?";
                        $params[] = $numMes;
                        $types .= 'i';
                        break;
                    }
                }

                $sql .= " WHERE (" . implode(" OR ", $orConditions) . ") ORDER BY op.id DESC, u.numero_secuencia ASC LIMIT 60";
                $st = $conn->prepare($sql);
                if (!empty($params)) {
                    $st->bind_param($types, ...$params);
                }
            } else {
                $st = $conn->prepare($sql . " ORDER BY op.id DESC, u.numero_secuencia ASC LIMIT 40");
            }

            $st->execute();
            $res = $st->get_result();
            $items = [];
            while ($row = $res->fetch_assoc()) {
                $numOrden = numero_orden_produccion((int)$row['orden_id'], $row['orden_fecha']);
                $items[] = [
                    'id' => $row['numero_identificador'],
                    'text' => 'Orden ' . $numOrden . ' — ' . $row['numero_identificador'] . ' (' . $row['producto_nombre'] . ')',
                    'unidad_id' => (int) $row['unidad_id'],
                    'estado' => $row['estado'],
                    'orden_numero' => $numOrden
                ];
            }
            $st->close();

            echo json_encode([
                'results' => $items
            ]);
            break;

        case 'buscar_unidades_por_orden':
            $ordenId = (int) ($_GET['orden_id'] ?? $_POST['orden_id'] ?? 0);
            if ($ordenId <= 0) {
                throw new Exception('ID de orden inválido');
            }

            // Asegurar que las unidades de esta orden existan
            $stOrd = $conn->prepare("
                SELECT op.id, op.cantidad_a_producir, op.talla_id, COALESCE(r.id, 0) AS receta_id
                FROM ordenes_produccion op
                INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                    AND r.rango_tallas_id = rp.rango_tallas_id 
                    AND r.tipo_produccion_id = rp.tipo_produccion_id
                WHERE op.id = ? LIMIT 1
            ");
            $stOrd->bind_param('i', $ordenId);
            $stOrd->execute();
            $ordData = $stOrd->get_result()->fetch_assoc();
            $stOrd->close();
            if ($ordData && (int)$ordData['receta_id'] > 0) {
                OrdenProduccionUnidades::sincronizarUnidadesOrden(
                    $conn,
                    $ordenId,
                    (int)$ordData['receta_id'],
                    !empty($ordData['talla_id']) ? (int)$ordData['talla_id'] : null,
                    (float)$ordData['cantidad_a_producir']
                );
            }

            $unidades = OrdenProduccionUnidades::obtenerUnidadesPorOrden($conn, $ordenId);
            echo json_encode([
                'success' => true,
                'unidades' => $unidades
            ]);
            break;

        case 'crear_devolucion':
            $unidadId = (int) ($_POST['unidad_id'] ?? 0);
            $clienteId = !empty($_POST['cliente_id']) ? (int) $_POST['cliente_id'] : null;
            $motivo = trim($_POST['motivo'] ?? '');
            $observaciones = trim($_POST['observaciones'] ?? '');
            $usuarioId = !empty($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : null;
            $accionInventario = 'reingresar_stock'; // Siempre suma al inventario de productos terminados

            if ($unidadId <= 0) {
                throw new Exception('Debe seleccionar la unidad o producto terminado a devolver.');
            }

            if ($motivo === '') {
                throw new Exception('Debe escribir el motivo de la devolución.');
            }

            $conn->begin_transaction();
            try {
                // Obtener datos de la unidad
                $stU = $conn->prepare("
                    SELECT u.id, u.orden_produccion_id, u.receta_id, u.numero_identificador, u.estado,
                           op.id AS op_id, op.creado_en AS op_fecha,
                           p.nombre AS producto_nombre
                    FROM ordenes_produccion_unidades u
                    INNER JOIN ordenes_produccion op ON op.id = u.orden_produccion_id
                    INNER JOIN recetas r ON r.id = u.receta_id
                    INNER JOIN productos p ON p.id = r.producto_id
                    WHERE u.id = ? FOR UPDATE
                ");
                $stU->bind_param('i', $unidadId);
                $stU->execute();
                $unidad = $stU->get_result()->fetch_assoc();
                $stU->close();

                if (!$unidad) {
                    throw new Exception('No se encontró la unidad seleccionada.');
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

                $codigoDevolucion = OrdenProduccionUnidades::generarCodigoDevolucion($devolucionId, $fechaActual);
                $conn->query("UPDATE devoluciones SET codigo_devolucion = '{$codigoDevolucion}' WHERE id = {$devolucionId}");

                $estadoPosteriorUnidad = 'devuelto_stock';
                $nuevoEstadoUnidad = 'disponible';

                // Insertar detalle de devolución
                $opId = (int) $unidad['orden_produccion_id'];
                $stDet = $conn->prepare("
                    INSERT INTO devoluciones_detalle (devolucion_id, unidad_id, orden_produccion_id, estado_unidad_posterior, observaciones)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stDet->bind_param('iiiss', $devolucionId, $unidadId, $opId, $estadoPosteriorUnidad, $motivo);
                $stDet->execute();
                $stDet->close();

                // Actualizar estado de la unidad
                $obsUnidad = "Devuelto en proceso {$codigoDevolucion} el " . date('d/m/Y H:i') . " - Motivo: " . $motivo;
                $stUpU = $conn->prepare("UPDATE ordenes_produccion_unidades SET estado = ?, observaciones = ? WHERE id = ?");
                $stUpU->bind_param('ssi', $nuevoEstadoUnidad, $obsUnidad, $unidadId);
                $stUpU->execute();
                $stUpU->close();

                // SIEMPRE SUMA STOCK AL INVENTARIO DE PRODUCTOS TERMINADOS
                $recetaId = (int) $unidad['receta_id'];
                $numeroOrden = numero_orden_produccion((int)$unidad['op_id'], $unidad['op_fecha']);
                InventarioLotes::registrarEntrada($conn, [
                    'tipo_item' => 'producto',
                    'tipo_item_id' => $recetaId,
                    'cantidad' => 1.00,
                    'origen' => 'devolucion',
                    'origen_id' => $devolucionId,
                    'observaciones' => "Reingreso por devolución {$codigoDevolucion} de unidad {$unidad['numero_identificador']} (OP: {$numeroOrden}) - Motivo: {$motivo}",
                    'tipo_movimiento' => 'devolucion',
                ]);

                Auditoria::registrar(
                    $conn,
                    "Registro de devolución {$codigoDevolucion} para el producto '{$unidad['producto_nombre']}' (Identificador: {$unidad['numero_identificador']}) de la OP #{$unidad['orden_produccion_id']}. Motivo: {$motivo}. Reingresada 1 unidad a stock.",
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
            $motivoFiltro = trim($_GET['motivo'] ?? $_POST['motivo'] ?? '');
            $accionFiltro = trim($_GET['accion_inventario'] ?? $_POST['accion_inventario'] ?? '');
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
                    OR u.numero_identificador LIKE ? 
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
                $params[] = $likeB;
                $params[] = $opNumInt;
                $types .= "ssssssi";
            }

            if ($motivoFiltro !== '') {
                $whereParts[] = "d.motivo LIKE ?";
                $params[] = '%' . $motivoFiltro . '%';
                $types .= "s";
            }

            if ($accionFiltro !== '') {
                $whereParts[] = "d.accion_inventario = ?";
                $params[] = $accionFiltro;
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
                LEFT JOIN ordenes_produccion_unidades u ON u.id = dd.unidad_id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas r ON r.id = u.receta_id
                LEFT JOIN productos p ON p.id = r.producto_id
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
                       dd.unidad_id, dd.orden_produccion_id, dd.estado_unidad_posterior,
                       u.numero_identificador, u.numero_secuencia,
                       p.nombre AS producto_nombre,
                       COALESCE(t.nombre, 'Sin talla') AS talla_nombre,
                       op.creado_en AS orden_creado_en
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion_unidades u ON u.id = dd.unidad_id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas r ON r.id = u.receta_id
                LEFT JOIN tallas t ON t.id = u.talla_id
                LEFT JOIN productos p ON p.id = r.producto_id
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
                       u.id AS unidad_id, u.numero_identificador, u.numero_secuencia, u.estado AS estado_actual_unidad,
                       p.nombre AS producto_nombre, p.categoria,
                       COALESCE(t.nombre, 'Sin talla') AS talla_nombre,
                       op.id AS orden_produccion_id, op.creado_en AS orden_creado_en, op.estado AS estado_orden,
                       dd.estado_unidad_posterior
                FROM devoluciones d
                LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
                LEFT JOIN ordenes_produccion_unidades u ON u.id = dd.unidad_id
                LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
                LEFT JOIN recetas r ON r.id = u.receta_id
                LEFT JOIN tallas t ON t.id = u.talla_id
                LEFT JOIN productos p ON p.id = r.producto_id
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

        case 'obtener_ordenes_recientes':
            // Retorna órdenes de producción recientes para que el usuario pueda seleccionarlas cómodamente en la modal
            $sql = "
                SELECT op.id, op.cantidad_a_producir, op.creado_en, op.fecha_inicio, op.estado,
                       p.nombre AS producto_nombre,
                       COALESCE(t.nombre, 'Única') AS talla_nombre
                FROM ordenes_produccion op
                INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                INNER JOIN productos p ON p.id = rp.producto_id
                LEFT JOIN tallas t ON t.id = op.talla_id
                ORDER BY op.id DESC
                LIMIT 30
            ";
            $res = $conn->query($sql);
            $ordenes = [];
            if ($res) {
                while ($r = $res->fetch_assoc()) {
                    $r['numero_orden'] = numero_orden_produccion((int)$r['id'], $r['creado_en']);
                    $ordenes[] = $r;
                }
            }
            echo json_encode(['success' => true, 'ordenes' => $ordenes]);
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
