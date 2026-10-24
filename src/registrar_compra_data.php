<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/InventarioLotes.php';
require_once __DIR__ . '/../lib/AbastecimientoService.php';

AbastecimientoService::asegurarTablas($conn);

$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

$chkMax = $conn->query("SHOW COLUMNS FROM insumos LIKE 'stock_maximo'");
if ($chkMax && $chkMax->num_rows == 0) {
    try {
        $conn->query("ALTER TABLE insumos ADD COLUMN stock_minimo decimal(12,2) DEFAULT NULL AFTER costo_unitario");
        $conn->query("ALTER TABLE insumos ADD COLUMN stock_maximo decimal(12,2) DEFAULT NULL AFTER stock_minimo");
    } catch (Exception $e) {
    }
}

$action = $_POST['action'] ?? '';
$requestJson = null;

if (empty($action)) {
    $raw = file_get_contents('php://input');
    $requestJson = json_decode($raw, true);
    $action = is_array($requestJson) ? ($requestJson['action'] ?? '') : '';
}

try {
    if ($action === 'obtener_ordenes_compra_proveedor') {
        $proveedor_id = isset($_POST['proveedor_id']) ? (int) $_POST['proveedor_id'] : 0;
        if ($proveedor_id <= 0) {
            throw new Exception("Debe seleccionar un proveedor para consultar sus Órdenes de Compra pendientes.");
        }

        $sql = "
            SELECT 
                oc.id,
                oc.codigo_oc,
                oc.proveedor_id,
                oc.proveedor_id AS id_proveedor,
                oc.fecha,
                oc.modalidad,
                oc.total,
                oc.total AS monto_total,
                oc.estado,
                oc.observaciones,
                COUNT(ocd.id) AS total_items,
                COALESCE(SUM(ocd.cantidad), 0) AS cantidad_total
            FROM ordenes_compra oc
            LEFT JOIN orden_compra_detalles ocd ON ocd.orden_compra_id = oc.id
            WHERE oc.proveedor_id = ?
              AND oc.estado = 'EMITIDA'
            GROUP BY oc.id, oc.codigo_oc, oc.proveedor_id, oc.fecha, oc.modalidad, oc.total, oc.estado, oc.observaciones
            ORDER BY oc.id DESC
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $proveedor_id);
        $stmt->execute();
        $res = $stmt->get_result();

        $ordenes = [];
        while ($row = $res->fetch_assoc()) {
            $row['id'] = (int) $row['id'];
            $row['proveedor_id'] = (int) $row['proveedor_id'];
            $row['id_proveedor'] = (int) $row['proveedor_id'];
            $row['total'] = (float) $row['total'];
            $row['monto_total'] = (float) $row['total'];
            $row['total_items'] = (int) $row['total_items'];
            $row['fecha_formateada'] = date('d/m/Y', strtotime($row['fecha']));
            $ordenes[] = $row;
        }
        $stmt->close();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'ordenes' => $ordenes
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_insumos_orden_compra') {
        $orden_compra_id = isset($_POST['orden_compra_id']) ? (int) $_POST['orden_compra_id'] : 0;
        if ($orden_compra_id <= 0) {
            throw new Exception("ID de Orden de Compra inválido");
        }

        $stmtCab = $conn->prepare("
            SELECT 
                oc.id,
                oc.codigo_oc,
                oc.proveedor_id,
                oc.proveedor_id AS id_proveedor,
                oc.fecha,
                oc.total,
                oc.total AS monto_total,
                oc.estado,
                oc.observaciones,
                p.nombre AS proveedor_nombre
            FROM ordenes_compra oc
            INNER JOIN proveedores p ON p.id = oc.proveedor_id
            WHERE oc.id = ?
            LIMIT 1
        ");
        $stmtCab->bind_param('i', $orden_compra_id);
        $stmtCab->execute();
        $oc = $stmtCab->get_result()->fetch_assoc();
        $stmtCab->close();

        if (!$oc) {
            throw new Exception("Orden de Compra no encontrada");
        }
        if ($oc['estado'] !== 'EMITIDA') {
            throw new Exception("Solo se pueden cargar Órdenes de Compra pendientes en estado EMITIDA.");
        }

        $oc['id'] = (int) $oc['id'];
        $oc['proveedor_id'] = (int) $oc['proveedor_id'];
        $oc['id_proveedor'] = (int) $oc['proveedor_id'];
        $oc['total'] = (float) $oc['total'];
        $oc['monto_total'] = (float) $oc['total'];

        $sqlDet = "
            SELECT 
                ocd.insumo_id,
                ocd.cantidad,
                ocd.costo_unitario,
                ocd.subtotal,
                i.nombre AS insumo_nombre,
                um.codigo AS unidad_medida
            FROM orden_compra_detalles ocd
            INNER JOIN insumos i ON i.id = ocd.insumo_id
            INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
            WHERE ocd.orden_compra_id = ?
            ORDER BY ocd.id ASC
        ";
        $stmtDet = $conn->prepare($sqlDet);
        $stmtDet->bind_param('i', $orden_compra_id);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();

        $insumos = [];
        while ($row = $resDet->fetch_assoc()) {
            $insumos[] = [
                'insumo_id' => (int) $row['insumo_id'],
                'id_insumo' => (int) $row['insumo_id'],
                'insumo_nombre' => $row['insumo_nombre'],
                'unidad_medida' => $row['unidad_medida'],
                'cantidad' => (float) $row['cantidad'],
                'costo_unitario' => (float) $row['costo_unitario'],
                'subtotal' => (float) $row['subtotal'],
            ];
        }
        $stmtDet->close();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'id_proveedor' => $oc['id_proveedor'],
            'proveedor_id' => $oc['proveedor_id'],
            'orden' => $oc,
            'orden_compra' => $oc,
            'insumos' => $insumos
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_insumos_proveedor') {
        $proveedor_id = $_POST['proveedor_id'] ?? null;
        
        if (!$proveedor_id) {
            throw new Exception("ID de proveedor requerido");
        }
        
        $sql = "SELECT i.id, i.nombre, i.costo_unitario, um.codigo AS unidad_medida
                FROM insumos i
                INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
                WHERE i.proveedor_id = ?
                ORDER BY i.nombre ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $proveedor_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $insumos = [];
        while ($row = $result->fetch_assoc()) {
            $insumos[] = $row;
        }
        
        $stmt->close();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'insumos' => $insumos
        ]);
        $conn->close();
        exit;
    }
    
    if ($action === 'obtener_detalle') {
        $compra_id = $_POST['compra_id'] ?? null;
        
        if (!$compra_id) {
            throw new Exception("ID de compra requerido");
        }
        
        $sqlCompra = "
            SELECT 
                c.id,
                c.id_orden_compra,
                oc.codigo_oc,
                c.fecha,
                c.numero_factura,
                c.total,
                c.estado,
                c.tasa_cambiaria_id,
                tc.tasa AS tasa_compra,
                p.nombre AS proveedor_nombre
            FROM compras c
            INNER JOIN proveedores p ON c.proveedor_id = p.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = c.tasa_cambiaria_id
            LEFT JOIN ordenes_compra oc ON oc.id = c.id_orden_compra
            WHERE c.id = ?
        ";
        
        $stmt = $conn->prepare($sqlCompra);
        $stmt->bind_param("i", $compra_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if (!$result || $result->num_rows === 0) {
            throw new Exception("Compra no encontrada");
        }
        
        $compra = $result->fetch_assoc();
        $stmt->close();
        
        $sqlDetalles = "
            SELECT 
                dc.id,  
                dc.cantidad,
                dc.costo_unitario,
                dc.subtotal,
                i.nombre AS insumo_nombre,
                um.codigo AS unidad_medida
            FROM detalle_compra dc
            INNER JOIN insumos i ON dc.insumo_id = i.id
            INNER JOIN unidad_medida um ON um.id = i.unidad_medida_id
            WHERE dc.compra_id = ?
            ORDER BY i.nombre ASC
        ";
        
        $stmt = $conn->prepare($sqlDetalles);
        $stmt->bind_param("i", $compra_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $detalles = [];
        while ($row = $result->fetch_assoc()) {
            $detalles[] = $row;
        }
        $stmt->close();
        
        $compra['fecha_formateada'] = date('d/m/Y', strtotime($compra['fecha']));
        
        $estadoBadge = '';
        switch($compra['estado']) {
            case 'pendiente':
                $estadoBadge = '<span style="color: orange; font-weight: bold;">Pendiente</span>';
                break;
            case 'recibido':
                $estadoBadge = '<span style="color: green; font-weight: bold;">Recibido</span>';
                break;
            case 'cancelado':
                $estadoBadge = '<span style="color: red; font-weight: bold;">Cancelado</span>';
                break;
            default:
                $estadoBadge = htmlspecialchars($compra['estado']);
        }
        $compra['estado_badge'] = $estadoBadge;
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'compra' => $compra,
            'detalles' => $detalles
        ]);
        $conn->close();
        exit;
    }
    
    if ($action === 'listar_html') {
        $buscar_proveedor = isset($_POST['buscar_proveedor']) ? trim($_POST['buscar_proveedor']) : '';
        $buscar_factura   = isset($_POST['buscar_factura']) ? trim($_POST['buscar_factura']) : '';
        $estado           = isset($_POST['estado']) ? trim($_POST['estado']) : '';
        $fecha_desde      = isset($_POST['fecha_desde']) ? trim($_POST['fecha_desde']) : '';
        $fecha_hasta      = isset($_POST['fecha_hasta']) ? trim($_POST['fecha_hasta']) : '';

        $where = [];

        if ($buscar_proveedor !== '') {
            $searchP = $conn->real_escape_string($buscar_proveedor);
            $where[] = "(p.nombre LIKE '%$searchP%' OR p.cedrif LIKE '%$searchP%')";
        }

        if ($buscar_factura !== '') {
            $searchF = $conn->real_escape_string($buscar_factura);
            $where[] = "c.numero_factura LIKE '%$searchF%'";
        }

        if ($estado !== '') {
            $searchE = $conn->real_escape_string($estado);
            $where[] = "c.estado = '$searchE'";
        }

        if ($fecha_desde !== '') {
            $fDesde = $conn->real_escape_string($fecha_desde);
            $where[] = "c.fecha >= '$fDesde'";
        }

        if ($fecha_hasta !== '') {
            $fHasta = $conn->real_escape_string($fecha_hasta);
            $where[] = "c.fecha <= '$fHasta'";
        }

        // Unimos los filtros si existen
        $fil = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sqlBase = "
            SELECT 
                c.id,
                c.fecha,
                c.numero_factura,
                c.total,
                c.estado,
                c.tasa_cambiaria_id,
                tc.tasa AS tasa_compra,
                COALESCE(SUM(dc.cantidad), 0) AS cantidad_total,
                COUNT(dc.id) AS cantidad_items,
                p.nombre AS proveedor_nombre
            FROM compras c
            INNER JOIN proveedores p ON c.proveedor_id = p.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = c.tasa_cambiaria_id
            LEFT JOIN detalle_compra dc ON c.id = dc.compra_id
            " . $fil . "
            GROUP BY c.id, c.fecha, c.numero_factura, c.total, c.estado, c.tasa_cambiaria_id, tc.tasa, p.nombre, c.creado_en
        ";

        $total = Pagination::countFromSubquery($conn, $sqlBase);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = $sqlBase . '
            ORDER BY c.creado_en DESC, c.fecha DESC
        ' . $pg->limitClause();

        $result = $conn->query($sql);
        $compras = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $compras[] = $row;
            }
        }
        ob_start();
        $i = $pg->rowNumberStart() - 1;
        if (!empty($compras)) {
            foreach ($compras as $c) {
                $i++;
                $totalBs = null;
                if (!empty($c['tasa_compra']) && (float)$c['tasa_compra'] > 0) {
                    $totalBs = (float)$c['total'] * (float)$c['tasa_compra'];
                }
                $totalBsTexto = $totalBs !== null ? 'Bs. ' . number_format($totalBs, 2, '.', ',') : '—';
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($c['proveedor_nombre']) . '</td>';
                echo '<td>' . date('d/m/Y', strtotime($c['fecha'])) . '</td>';
                echo '<td>' . htmlspecialchars($c['numero_factura'] ?? '-') . '</td>';
                echo '<td>' . number_format($c['cantidad_total'], 2, '.', ',') . '</td>';
                echo '<td>$' . number_format($c['total'], 2, '.', ',') . '</td>';
                echo '<td>' . htmlspecialchars($totalBsTexto) . '</td>';
                
                $baseStyle = 'font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block; text-transform: capitalize;';
                
                [$estTxt, $estStyle] = match($c['estado']) {
                    'recibido'  => ['Recibido', "background-color: #198754; color: #ffffff; {$baseStyle}"], // Verde (Finalizado)
                    'pendiente' => ['Pendiente', "background-color: #fd7e14; color: #ffffff; {$baseStyle}"], // Naranja (Espera)
                    'cancelado' => ['Cancelado', "background-color: #dc3545; color: #ffffff; {$baseStyle}"], // Rojo (Cancelado)
                    default     => [$c['estado'] ?? '—', "background-color: #6c757d; color: #ffffff; {$baseStyle}"]
                };

                echo '<td><span style="' . htmlspecialchars($estStyle, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($estTxt) . '</span></td>';
                
                echo '<td>';
                echo '<button class="btn btn-sm btn-primary" onclick="verDetalle(' . $c['id'] . ')"><i class="fas fa-eye"></i> Ver Detalle</button>';
                echo '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="8" class="text-center">No se encontraron compras registradas</td></tr>';
        }
        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    restringirEscritura();

    header('Content-Type: application/json');

    if ($action === 'obtener_siguiente_numero_factura') {
        $siguiente = '1';
        $res = $conn->query("SELECT numero_factura FROM compras ORDER BY id DESC LIMIT 1");
        if ($res && ($row = $res->fetch_assoc()) && !empty($row['numero_factura'])) {
            $ultimo = trim($row['numero_factura']);
            if (ctype_digit($ultimo)) {
                $siguiente = (string)((int)$ultimo + 1);
            } elseif (preg_match('/(\d+)\s*$/', $ultimo, $m)) {
                $siguiente = (string)((int)$m[1] + 1);
            } elseif (preg_match('/^(\d+)/', $ultimo, $m)) {
                $siguiente = (string)((int)$m[1] + 1);
            }
        }
        echo json_encode(['success' => true, 'siguiente_numero_factura' => $siguiente]);
        $conn->close();
        exit;
    }

    if ($action === 'prevalidar_stock_max') {
        if (!is_array($requestJson)) {
            throw new Exception('Solicitud inválida');
        }
        $insumos = $requestJson['insumos'] ?? [];
        if (!is_array($insumos)) {
            throw new Exception('Datos inválidos');
        }
        $porInsumo = [];
        $stmtPerm = $conn->prepare(
            'SELECT COALESCE(um.permite_movimiento_decimal, 1) AS p FROM insumos i LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id WHERE i.id = ? LIMIT 1'
        );
        foreach ($insumos as $row) {
            $id = (int) ($row['insumo_id'] ?? 0);
            $q = (float) ($row['cantidad'] ?? 0);
            if ($id <= 0 || $q <= 0) {
                continue;
            }
            if (!$stmtPerm) {
                throw new Exception('Error al validar unidades de medida');
            }
            $stmtPerm->bind_param('i', $id);
            $stmtPerm->execute();
            $rp = $stmtPerm->get_result()->fetch_assoc();
            $permite = !$rp || (int) ($rp['p'] ?? 1) === 1;
            try {
                $q = inv_normalizar_cantidad_movimiento_manual($q, $permite);
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }
            if (!isset($porInsumo[$id])) {
                $porInsumo[$id] = 0;
            }
            $porInsumo[$id] += $q;
        }
        if ($stmtPerm) {
            $stmtPerm->close();
        }
        $alertas = [];
        foreach ($porInsumo as $insumo_id => $cant_total) {
            $stmtNom = $conn->prepare('SELECT nombre, stock_maximo FROM insumos WHERE id = ?');
            $stmtNom->bind_param('i', $insumo_id);
            $stmtNom->execute();
            $resNom = $stmtNom->get_result();
            $rowNom = $resNom->fetch_assoc();
            $stmtNom->close();
            if (!$rowNom) {
                continue;
            }
            $stockMaxRaw = $rowNom['stock_maximo'];
            if ($stockMaxRaw === null || $stockMaxRaw === '') {
                continue;
            }
            $stock_max = (float) $stockMaxRaw;
            $stockActual = 0;
            if ($tieneInventarioNuevo) {
                $stmtStock = $conn->prepare("SELECT stock_actual FROM inventario WHERE tipo_item = 'insumo' AND tipo_item_id = ?");
            } else {
                $stmtStock = $conn->prepare('SELECT stock_actual FROM inventario WHERE insumo_id = ?');
            }
            $stmtStock->bind_param('i', $insumo_id);
            $stmtStock->execute();
            $rsStock = $stmtStock->get_result();
            if ($rowS = $rsStock->fetch_assoc()) {
                $stockActual = (float) $rowS['stock_actual'];
            }
            $stmtStock->close();
            $resultante = $stockActual + $cant_total;
            if ($resultante > $stock_max) {
                $alertas[] = [
                    'insumo_nombre' => $rowNom['nombre'],
                    'stock_actual' => $stockActual,
                    'cantidad_compra' => $cant_total,
                    'stock_maximo' => $stock_max,
                    'resultante' => $resultante,
                ];
            }
        }
        $requiere = count($alertas) > 0;
        $mensaje = $requiere
            ? 'El stock máximo ha sido superado, ¿estás seguro que deseas continuar?'
            : '';
        echo json_encode([
            'success' => true,
            'requiere_confirmacion' => $requiere,
            'mensaje' => $mensaje,
            'alertas' => $alertas,
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'crear') {
        if (!is_array($requestJson)) {
            throw new Exception('Solicitud inválida');
        }
        $data = $requestJson;
        $proveedor_id = $data['proveedor_id'] ?? null;
        $id_orden_compra = !empty($data['id_orden_compra']) ? (int) $data['id_orden_compra'] : null;
        $fecha = $data['fecha'] ?? null;
        $numero_factura = trim($data['numero_factura'] ?? '');
        $estado = $data['estado'] ?? 'recibido';
        $insumos = $data['insumos'] ?? [];

        if (!$proveedor_id) {
            throw new Exception("El proveedor es obligatorio");
        }

        if (!$fecha) {
            throw new Exception("La fecha es obligatoria");
        }

        if (empty($insumos) || !is_array($insumos)) {
            throw new Exception("Debe agregar al menos un insumo a la compra");
        }

        if ($numero_factura !== '') {
            $facNorm = strtoupper($numero_factura);
            $chkFac = $conn->prepare(
                "SELECT id FROM compras WHERE proveedor_id = ? AND numero_factura IS NOT NULL AND TRIM(numero_factura) <> '' AND UPPER(TRIM(numero_factura)) = ? LIMIT 1"
            );
            if (!$chkFac) {
                throw new Exception('Error al validar número de factura: ' . $conn->error);
            }
            $proveedor_id_int = (int) $proveedor_id;
            $chkFac->bind_param('is', $proveedor_id_int, $facNorm);
            $chkFac->execute();
            $rsFac = $chkFac->get_result();
            if ($rsFac && $rsFac->num_rows > 0) {
                $chkFac->close();
                throw new Exception('Este proveedor ya tiene una compra registrada con el mismo número de factura.');
            }
            $chkFac->close();
        }

        $stmtPermTot = $conn->prepare(
            'SELECT COALESCE(um.permite_movimiento_decimal, 1) AS p FROM insumos i LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id WHERE i.id = ? LIMIT 1'
        );
        if (!$stmtPermTot) {
            throw new Exception('Error al preparar validación de unidades');
        }

        $total = 0;
        foreach ($insumos as &$insumo) {
            $insumo_id = (int) ($insumo['insumo_id'] ?? 0);
            $cantidad = (float) ($insumo['cantidad'] ?? 0);
            $costo_unitario = (float) ($insumo['costo_unitario'] ?? 0);

            if ($insumo_id <= 0 || $costo_unitario <= 0) {
                throw new Exception('Datos inválidos en el detalle de la compra');
            }

            $stmtPermTot->bind_param('i', $insumo_id);
            $stmtPermTot->execute();
            $rp = $stmtPermTot->get_result()->fetch_assoc();
            $permite = !$rp || (int) ($rp['p'] ?? 1) === 1;
            try {
                $cantidad = inv_normalizar_cantidad_movimiento_manual($cantidad, $permite);
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }

            $insumo['cantidad'] = $cantidad;
            $total += $cantidad * $costo_unitario;
        }
        unset($insumo);
        $stmtPermTot->close();

        $tasa_cambiaria_id = null;
        $rt = $conn->query("SELECT id FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
        if ($rt && $row_tasa = $rt->fetch_assoc()) {
            $tasa_cambiaria_id = (int) $row_tasa['id'];
        }

        $conn->begin_transaction();

        try {
            $codigoOcAsociada = null;
            if ($id_orden_compra !== null && $id_orden_compra > 0) {
                $stmtChkOc = $conn->prepare("SELECT id, codigo_oc, proveedor_id, estado FROM ordenes_compra WHERE id = ? FOR UPDATE");
                $stmtChkOc->bind_param('i', $id_orden_compra);
                $stmtChkOc->execute();
                $ocRow = $stmtChkOc->get_result()->fetch_assoc();
                $stmtChkOc->close();

                if (!$ocRow) {
                    throw new Exception("La Orden de Compra seleccionada no existe.");
                }
                if ((int) $ocRow['proveedor_id'] !== (int) $proveedor_id) {
                    throw new Exception("La Orden de Compra seleccionada no pertenece al proveedor indicado.");
                }
                if ($ocRow['estado'] !== 'EMITIDA') {
                    throw new Exception("La Orden de Compra {$ocRow['codigo_oc']} ya no se encuentra en estado EMITIDA.");
                }
                $codigoOcAsociada = (string) $ocRow['codigo_oc'];
            } else {
                $id_orden_compra = null;
            }

            $stmt = $conn->prepare("
                INSERT INTO compras (proveedor_id, id_orden_compra, fecha, numero_factura, total, estado, tasa_cambiaria_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");

            $numero_factura = empty($numero_factura) ? null : $numero_factura;
            $stmt->bind_param("iissdsi", $proveedor_id, $id_orden_compra, $fecha, $numero_factura, $total, $estado, $tasa_cambiaria_id);
            $stmt->execute();
            
            $compra_id = $conn->insert_id;
            $stmt->close();

            if (!$compra_id) {
                throw new Exception("Error al crear la compra");
            }

            if ($id_orden_compra !== null && $id_orden_compra > 0) {
                $stmtUpdOc = $conn->prepare("UPDATE ordenes_compra SET estado = 'COMPLETADA' WHERE id = ?");
                $stmtUpdOc->bind_param('i', $id_orden_compra);
                $stmtUpdOc->execute();
                $stmtUpdOc->close();
            }

            $stmtDetalle = $conn->prepare("
                INSERT INTO detalle_compra (compra_id, insumo_id, cantidad, costo_unitario)
                VALUES (?, ?, ?, ?)
            ");

            foreach ($insumos as $insumo) {
                $insumo_id = intval($insumo['insumo_id'] ?? 0);
                $cantidad = floatval($insumo['cantidad'] ?? 0);
                $costo_unitario = floatval($insumo['costo_unitario'] ?? 0);

                if ($insumo_id <= 0 || $cantidad <= 0 || $costo_unitario <= 0) {
                    throw new Exception("Datos inválidos en el detalle de la compra");
                }

                $stmtDetalle->bind_param("iidd", $compra_id, $insumo_id, $cantidad, $costo_unitario);
                $stmtDetalle->execute();

                InventarioLotes::registrarEntrada($conn, [
                    'tipo_item' => 'insumo',
                    'tipo_item_id' => $insumo_id,
                    'cantidad' => $cantidad,
                    'fecha_entrada' => $fecha,
                    'origen' => 'compra',
                    'origen_id' => $compra_id,
                    'observaciones' => "Entrada por compra #{$compra_id}" . ($codigoOcAsociada ? " (O.C. {$codigoOcAsociada})" : ""),
                    'tipo_movimiento' => 'compra',
                    'costo_unitario' => $costo_unitario,
                ]);
            }

            $stmtDetalle->close();

            $conn->commit();

            $facturaCmp = $data['numero_factura'] ?? '';
            $facturaCmp = is_string($facturaCmp) ? trim($facturaCmp) : '';
            $facturaTxt = $facturaCmp !== '' ? $facturaCmp : 'sin número';
            $ocTxt = $codigoOcAsociada ? " O.C.: {$codigoOcAsociada}." : "";
            Auditoria::registrar(
                $conn,
                "Compra #{$compra_id} registrada. Proveedor #{$proveedor_id}.{$ocTxt} Factura: {$facturaTxt}. Total: {$total}. Estado: {$estado}.",
                'Compras'
            );

            echo json_encode([
                'success' => true, 
                'message' => 'Compra registrada exitosamente' . ($codigoOcAsociada ? " (Orden de Compra {$codigoOcAsociada} completada)" : ''),
                'compra_id' => $compra_id
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

    } else {
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

