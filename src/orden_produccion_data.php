<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/inventario_cantidad_unidad.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/orden_numero.php';
require_once __DIR__ . '/../lib/InventarioLotes.php';
require_once __DIR__ . '/../lib/OrdenProduccionUnidades.php';

OrdenProduccionUnidades::asegurarTablas($conn);

function asegurar_venta_id_en_ordenes_produccion(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    $chk = $conn->query("SHOW COLUMNS FROM ordenes_produccion LIKE 'venta_id'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    @$conn->query(
        'ALTER TABLE ordenes_produccion ADD COLUMN venta_id INT NULL DEFAULT NULL COMMENT \'Venta enlazada (p. ej. órdenes por falta de stock)\''
    );
    @$conn->query('ALTER TABLE ordenes_produccion ADD INDEX idx_ordenes_produccion_venta_id (venta_id)');
    @$conn->query(
        'ALTER TABLE ordenes_produccion ADD CONSTRAINT fk_ordenes_produccion_venta '
        . 'FOREIGN KEY (venta_id) REFERENCES ventas(id) ON DELETE SET NULL ON UPDATE CASCADE'
    );
}

asegurar_venta_id_en_ordenes_produccion($conn);

function asegurar_costo_en_talleres(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    @$conn->query("CREATE TABLE IF NOT EXISTS talleres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nombre VARCHAR(150) NOT NULL,
        costo DECIMAL(12,2) NOT NULL DEFAULT 0.00,
        descripcion TEXT NULL,
        activo TINYINT(1) DEFAULT 1,
        fecha_creacion TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $chk = $conn->query("SHOW COLUMNS FROM talleres LIKE 'costo'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    @$conn->query("ALTER TABLE talleres ADD COLUMN costo DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER nombre");
}

asegurar_costo_en_talleres($conn);

function asegurar_tabla_ordenes_talleres(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    @$conn->query("CREATE TABLE IF NOT EXISTS ordenes_talleres (
        id INT AUTO_INCREMENT PRIMARY KEY,
        orden_produccion_id INT NOT NULL,
        taller_id INT NOT NULL,
        fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        fecha_envio DATETIME NULL,
        fecha_entrega DATETIME NULL, 
        enviado TINYINT(1) NOT NULL DEFAULT 0,
        recibido TINYINT(1) NOT NULL DEFAULT 0, 
        observaciones TEXT NULL,
        CONSTRAINT fk_talleres_orden FOREIGN KEY (orden_produccion_id) 
            REFERENCES ordenes_produccion(id) 
            ON DELETE CASCADE 
            ON UPDATE CASCADE,
        CONSTRAINT fk_talleres_proveedor FOREIGN KEY (taller_id) 
            REFERENCES talleres(id)
            ON DELETE RESTRICT 
            ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $chkEnv = $conn->query("SHOW COLUMNS FROM ordenes_talleres LIKE 'enviado'");
    if (!$chkEnv || $chkEnv->num_rows === 0) {
        @$conn->query("ALTER TABLE ordenes_talleres ADD COLUMN enviado TINYINT(1) NOT NULL DEFAULT 0 AFTER fecha_asignacion");
    }

    $chkFEnv = $conn->query("SHOW COLUMNS FROM ordenes_talleres LIKE 'fecha_envio'");
    if (!$chkFEnv || $chkFEnv->num_rows === 0) {
        @$conn->query("ALTER TABLE ordenes_talleres ADD COLUMN fecha_envio DATETIME NULL AFTER fecha_asignacion");
    }

    @$conn->query("UPDATE ordenes_talleres SET enviado = 1, fecha_envio = fecha_asignacion WHERE recibido = 1 AND (enviado = 0 OR fecha_envio IS NULL)");
}

asegurar_tabla_ordenes_talleres($conn);

function asegurar_talla_id_en_ordenes_produccion(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    $conn->query(
        "CREATE TABLE IF NOT EXISTS `tallas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `rango_tallas_id` int(11) NOT NULL,
            `nombre` varchar(20) NOT NULL,
            `orden` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_rango_talla` (`rango_tallas_id`,`nombre`),
            KEY `rango_tallas_id` (`rango_tallas_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $chk = $conn->query("SHOW COLUMNS FROM ordenes_produccion LIKE 'talla_id'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    @$conn->query(
        'ALTER TABLE ordenes_produccion ADD COLUMN talla_id INT NULL DEFAULT NULL '
        . "COMMENT 'Talla individual a producir (tabla tallas)' AFTER receta_producto_id"
    );
    @$conn->query('ALTER TABLE ordenes_produccion ADD INDEX idx_ordenes_produccion_talla_id (talla_id)');
    @$conn->query(
        'ALTER TABLE ordenes_produccion ADD CONSTRAINT fk_ordenes_produccion_talla '
        . 'FOREIGN KEY (talla_id) REFERENCES tallas(id) ON DELETE SET NULL ON UPDATE CASCADE'
    );
}

function validar_talla_para_rango(mysqli $conn, ?int $talla_id, int $rango_tallas_id): void
{
    if (!$talla_id || $talla_id <= 0) {
        return; // La selección de talla es opcional
    }
    $stmt = $conn->prepare('SELECT id FROM tallas WHERE id = ? AND rango_tallas_id = ? LIMIT 1');
    $stmt->bind_param('ii', $talla_id, $rango_tallas_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$row) {
        throw new Exception('La talla seleccionada no corresponde al rango del producto');
    }
}

asegurar_talla_id_en_ordenes_produccion($conn);

/**
 * Fecha fin en listado: mismo estilo de alerta que inventario (stock mín./máx.)
 * cuando faltan ≤5 días o la fecha ya venció. No aplica a órdenes finalizadas.
 */
function op_html_fecha_fin_celda(?string $fecha_fin, string $estado): string
{
    if ($fecha_fin === null || trim($fecha_fin) === '') {
        return '—';
    }

    $tsFin = strtotime($fecha_fin);
    if ($tsFin === false) {
        return '—';
    }

    $texto = date('d/m/Y', $tsFin);

    if ($estado === 'finalizado') {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }

    $hoy = strtotime(date('Y-m-d'));
    $diasRestantes = (int) floor(($tsFin - $hoy) / 86400);
    $margenDias = 5;

    $alerta = false;
    $titulo = '';

    if ($diasRestantes < 0) {
        $alerta = true;
        $diasVencidos = abs($diasRestantes);
        $titulo = $diasVencidos === 1
            ? 'La fecha fin venció hace 1 día.'
            : 'La fecha fin venció hace ' . $diasVencidos . ' días.';
    } elseif ($diasRestantes <= $margenDias) {
        $alerta = true;
        if ($diasRestantes === 0) {
            $titulo = 'La fecha fin es hoy.';
        } elseif ($diasRestantes === 1) {
            $titulo = 'Falta 1 día para la fecha fin.';
        } else {
            $titulo = 'Faltan ' . $diasRestantes . ' días para la fecha fin.';
        }
    }

    if (!$alerta) {
        return htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
    }

    $estilo = 'display:block;width:100%;box-sizing:border-box;margin:-12px;padding:12px;'
        . 'color:#721c24;';

    return '<span title="' . htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') . '" style="' . $estilo . '">'
        . '<strong style="color:#721c24;">' . htmlspecialchars($texto, ENT_QUOTES, 'UTF-8') . '</strong></span>';
}

$tieneInventarioNuevo = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'")->num_rows > 0;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'listar_html') {

        $buscar_producto  = isset($_POST['buscar_producto']) ? $conn->real_escape_string(trim($_POST['buscar_producto'])) : '';
        $buscar_categoria = isset($_POST['buscar_categoria']) ? $conn->real_escape_string(trim($_POST['buscar_categoria'])) : '';
        $buscar_estado    = isset($_POST['buscar_estado']) ? $conn->real_escape_string(trim($_POST['buscar_estado'])) : '';
        $fecha_desde      = isset($_POST['fecha_desde']) ? $conn->real_escape_string(trim($_POST['fecha_desde'])) : '';
        $fecha_hasta      = isset($_POST['fecha_hasta']) ? $conn->real_escape_string(trim($_POST['fecha_hasta'])) : '';

        $where = [];

        if ($buscar_producto !== '') {
            $orProducto = ["p.nombre LIKE '%$buscar_producto%'"];
            $idBuscado = parse_id_orden_produccion($buscar_producto);
            if ($idBuscado > 0) {
                $orProducto[] = "op.id = $idBuscado";
            }
            $where[] = '(' . implode(' OR ', $orProducto) . ')';
        }
        if ($buscar_categoria !== '') {
            $where[] = "p.categoria = '$buscar_categoria'";
        }
        if ($buscar_estado !== '') {
            $where[] = "op.estado = '$buscar_estado'";
        }
        if ($fecha_desde !== '') {
            $where[] = "op.fecha_inicio >= '$fecha_desde'";
        }
        if ($fecha_hasta !== '') {
            $where[] = "op.fecha_inicio <= '$fecha_hasta'";
        }

        $fil = !empty($where) ? " WHERE " . implode(" AND ", $where) : "";

        $sqlBase = "
            SELECT 
                op.id AS orden_id,
                op.tasa_cambiaria_id,
                tc.tasa AS tasa_orden,
                p.nombre AS producto_nombre,
                p.categoria AS producto_categoria,
                op.cantidad_a_producir,
                op.fecha_inicio,
                op.fecha_fin,
                op.creado_en,
                op.estado,
                op.observaciones,
                op.talla_id,
                t.nombre AS talla_nombre,
                r.id AS receta_id,
                rp.producto_id,
                rp.rango_tallas_id,
                rp.tipo_produccion_id,
                COALESCE(SUM(rp2.cantidad_por_unidad * i.costo_unitario), 0) AS costo_por_unidad,
                COALESCE((SELECT SUM(tal.costo) FROM ordenes_talleres ot INNER JOIN talleres tal ON ot.taller_id = tal.id WHERE ot.orden_produccion_id = op.id), 0) AS costo_talleres,
                COALESCE((SELECT GROUP_CONCAT(tal.nombre SEPARATOR ', ') FROM ordenes_talleres ot INNER JOIN talleres tal ON ot.taller_id = tal.id WHERE ot.orden_produccion_id = op.id), '') AS nombres_talleres,
                COALESCE((SELECT GROUP_CONCAT(ot.taller_id) FROM ordenes_talleres ot WHERE ot.orden_produccion_id = op.id), '') AS talleres_ids_csv,
                COALESCE((SELECT COUNT(*) FROM ordenes_talleres ot WHERE ot.orden_produccion_id = op.id), 0) AS talleres_total,
                COALESCE((SELECT COUNT(*) FROM ordenes_talleres ot WHERE ot.orden_produccion_id = op.id AND ot.recibido = 0), 0) AS talleres_pendientes
            FROM ordenes_produccion op
            INNER JOIN recetas_productos rp ON op.receta_producto_id = rp.id
            INNER JOIN productos p ON rp.producto_id = p.id
            LEFT JOIN tasas_cambiarias tc ON tc.id = op.tasa_cambiaria_id
            LEFT JOIN tallas t ON t.id = op.talla_id
            LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                AND r.rango_tallas_id = rp.rango_tallas_id 
                AND r.tipo_produccion_id = rp.tipo_produccion_id
            LEFT JOIN recetas_productos rp2 ON rp2.producto_id = rp.producto_id 
                AND rp2.rango_tallas_id = rp.rango_tallas_id 
                AND rp2.tipo_produccion_id = rp.tipo_produccion_id
            LEFT JOIN insumos i ON rp2.insumo_id = i.id
            $fil
            GROUP BY op.id, op.tasa_cambiaria_id, tc.tasa, op.talla_id, t.nombre, r.id, rp.producto_id, rp.rango_tallas_id, rp.tipo_produccion_id, p.nombre, p.categoria, op.cantidad_a_producir, op.fecha_inicio, op.fecha_fin, op.creado_en, op.estado, op.observaciones
        ";

        $total = Pagination::countFromSubquery($conn, $sqlBase);
        $pg = Pagination::fromInput($total, $_POST);

        $sql = $sqlBase . '
            ORDER BY op.id DESC
        ' . $pg->limitClause();

        $result = $conn->query($sql);
        $ordenes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ordenes[] = $row;
            }
        }
        ob_start();
        if (!empty($ordenes)) {
            foreach ($ordenes as $o) {
                $estadoStyle = match($o['estado']) {
                    'finalizado' => 'background-color: #e8f7ec; color: #1b6d2e; border: 1px solid rgba(40, 167, 69, 0.3); font-weight: 600; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12.5px;',
                    'pendiente'  => 'background-color: #fff4e5; color: #b76e00; border: 1px solid rgba(253, 126, 20, 0.35); font-weight: 600; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12.5px;',
                    'en_proceso' => 'background-color: #e7f1ff; color: #0056b3; border: 1px solid rgba(0, 86, 179, 0.3); font-weight: 600; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12.5px;',
                    'en_taller'  => 'background-color: #f3e8ff; color: #6f42c1; border: 1px solid rgba(111, 66, 193, 0.35); font-weight: 600; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12.5px;',
                    'en_empresa' => 'background-color: #e2f7fa; color: #0c5460; border: 1px solid rgba(23, 162, 184, 0.35); font-weight: 600; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12.5px;',
                    default      => 'background-color: #fde8e9; color: #b02a37; border: 1px solid rgba(220, 53, 69, 0.3); font-weight: 600; padding: 5px 12px; border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; gap: 5px; font-size: 12.5px;'
                };

                $estadoIcon = match($o['estado']) {
                    'finalizado' => '<i class="fas fa-check-circle"></i>',
                    'pendiente'  => '<i class="fas fa-clock"></i>',
                    'en_proceso' => '<i class="fas fa-cog fa-spin"></i>',
                    'en_taller'  => '<i class="fas fa-warehouse"></i>',
                    'en_empresa' => '<i class="fas fa-box-open"></i>',
                    default      => '<i class="fas fa-circle-info"></i>'
                };

                $estadoTexto = match($o['estado']) {
                    'en_taller'  => 'Taller',
                    'en_empresa' => 'Recibido',
                    default      => ucfirst($o['estado']) 
                };
                
                $costoPorUnidad = floatval($o['costo_por_unidad'] ?? 0);
                $cantidad       = floatval($o['cantidad_a_producir'] ?? 0);
                $costoTalleresUd = floatval($o['costo_talleres'] ?? 0);
                $costoTalleres  = $costoTalleresUd * $cantidad;
                $costoTotal     = ($costoPorUnidad * $cantidad) + $costoTalleres;
                $nombresTalleres = !empty($o['nombres_talleres']) ? htmlspecialchars($o['nombres_talleres']) : '<em class="text-muted">—</em>';

                $o['costo_talleres'] = $costoTalleres;
                $o['costo_total'] = $costoTotal;
                $o['talleres_ids'] = !empty($o['talleres_ids_csv']) ? array_map('intval', explode(',', $o['talleres_ids_csv'])) : [];
                $o['numero_orden'] = numero_orden_produccion((int)$o['orden_id'], $o['creado_en'] ?? $o['fecha_inicio'] ?? null);

                echo '<tr>';
                echo '<td><strong style="color: #0056b3;"><i class="fas fa-file-lines" style="margin-right: 4px; opacity: 0.7;"></i>' . htmlspecialchars($o['numero_orden']) . '</strong></td>';
                echo '<td>' . htmlspecialchars($o['producto_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($o['producto_categoria'] ?? '-') . '</td>';
                echo '<td style="text-align: right; font-weight: 600;">' . htmlspecialchars($o['cantidad_a_producir']) . '</td>';
                echo '<td style="text-align: right;">$' . number_format($costoPorUnidad, 2, '.', ',') . '</td>';
                echo '<td>' . $nombresTalleres . '</td>';
                echo '<td style="font-weight: bold;text-align:right; color: #198754;">$' . number_format($costoTotal, 2, '.', ',') . '</td>';
                echo '<td>' . ($o['fecha_inicio'] ? date('d/m/Y', strtotime($o['fecha_inicio'])) : '—') . '</td>';
                echo '<td style="font-weight: bold">' . op_html_fecha_fin_celda($o['fecha_fin'] ?? null, (string) ($o['estado'] ?? '')) . '</td>';
                
                $estadoHtml = '<span style="' . $estadoStyle . '">' . $estadoIcon . ' ' . htmlspecialchars($estadoTexto) . '</span>';
                $btnFinalizar = '';
                $btneditar = '';
                $btnTalleres = '';
                $talleresTotal = (int) ($o['talleres_total'] ?? 0);
                $talleresPendientes = (int) ($o['talleres_pendientes'] ?? 0);
                $mercanciaRecibida = ($talleresTotal > 0 && $talleresPendientes === 0);
                if ($o['estado'] !== 'finalizado') {
                    if ($mercanciaRecibida) {
                        $btnFinalizar = '<button class="btn btn-sm btn-success" title="Finalizar Orden de Producción" onclick="aceptarFinalizacionOrden(' . (int)$o['orden_id'] . ')"><i class="fas fa-check-double"></i></button>';
                    }
                    $btneditar = '<button class="btn btn-sm btn-primary" title="Editar Orden de Producción" onclick="editarOrden(' . htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') . ')"><i class="fas fa-pencil"></i></button>';
                    $btnTalleres = '<button class="btn btn-sm btn-info" title="Asignar / Ver Talleres" onclick="abrirModalTalleres(' . htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') . ')"><i class="fas fa-warehouse"></i></button>';
                }
                echo '<td nowrap>' . $estadoHtml . '</td>';
                echo '<td nowrap style="text-align: center;"><div class="btn-action-group">' . $btnTalleres . $btnFinalizar . $btneditar . '</div></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="11" class="text-center">No hay órdenes de producción</td></tr>';
        }
        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    if ($action === 'obtener_historial_talleres') {
        $orden_id = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;

        if ($orden_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de orden no válido.']);
            exit;
        }

        $statusQuery = $conn->query("SELECT estado, creado_en, fecha_inicio FROM ordenes_produccion WHERE id = $orden_id");
        $ordenRow = ($statusQuery) ? $statusQuery->fetch_assoc() : null;
        $orden_status = $ordenRow['estado'] ?? '';
        $numero_orden = numero_orden_produccion($orden_id, $ordenRow['creado_en'] ?? $ordenRow['fecha_inicio'] ?? null);

        $sql = "SELECT 
                    ot.id,
                    ot.orden_produccion_id,
                    ot.taller_id,
                    t.nombre AS taller_nombre,
                    t.costo AS taller_costo,
                    ot.observaciones, 
                    COALESCE(ot.enviado, 0) AS enviado,
                    COALESCE(ot.recibido, 0) AS recibido,
                    DATE_FORMAT(ot.fecha_asignacion, '%d/%m/%Y %h:%i %p') AS fecha_asignacion,
                    DATE_FORMAT(ot.fecha_envio, '%d/%m/%Y %h:%i %p') AS fecha_envio,
                    DATE_FORMAT(ot.fecha_entrega, '%d/%m/%Y %h:%i %p') AS fecha_retorno
                FROM ordenes_talleres ot
                INNER JOIN talleres t ON ot.taller_id = t.id
                WHERE ot.orden_produccion_id = $orden_id
                ORDER BY ot.id ASC";

        $result = $conn->query($sql);
        $historial = [];

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $historial[] = $row;
            }
        }

        echo json_encode([
            'success' => true,
            'orden_status' => $orden_status,
            'numero_orden' => $numero_orden,
            'historial' => $historial
        ]);
        exit;
    }

    if ($action === 'enviar_a_taller' || $action === 'asignar_taller') {
        $orden_id    = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;
        $taller_id   = isset($_POST['taller_id']) ? (int)$_POST['taller_id'] : 0;
        $descripcion = isset($_POST['observaciones']) ? $conn->real_escape_string(trim($_POST['observaciones'])) : '';

        if ($orden_id <= 0 || $taller_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Faltan datos obligatorios para la asignación del taller.']);
            exit;
        }

        $conn->begin_transaction();

        try {
            $sqlInsert = "INSERT INTO ordenes_talleres (orden_produccion_id, taller_id, observaciones, fecha_asignacion, enviado, recibido) 
                        VALUES ($orden_id, $taller_id, '$descripcion', NOW(), 0, 0)";
            
            if (!$conn->query($sqlInsert)) {
                throw new Exception("Error al registrar el taller.");
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Taller asignado correctamente.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'registrar_envio_taller') {
        $historial_id = isset($_POST['historial_id']) ? (int)$_POST['historial_id'] : 0;
        $orden_id     = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;

        if ($historial_id <= 0 || $orden_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'IDs de referencia no válidos.']);
            exit;
        }

        $conn->begin_transaction();

        try {
            $sqlEnvio = "UPDATE ordenes_talleres 
                         SET enviado = 1, fecha_envio = NOW() 
                         WHERE id = $historial_id AND orden_produccion_id = $orden_id";
            
            if (!$conn->query($sqlEnvio)) {
                throw new Exception("Error al registrar el envío de mercancía.");
            }

            $sqlUpdateStatus = "UPDATE ordenes_produccion SET estado = 'en_taller' WHERE id = $orden_id";
            if (!$conn->query($sqlUpdateStatus)) {
                throw new Exception("Error al actualizar el estado de la orden.");
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Mercancía despachada y enviada al taller con éxito.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'registrar_retorno_taller') {
        $historial_id = isset($_POST['historial_id']) ? (int)$_POST['historial_id'] : 0;
        $orden_id     = isset($_POST['orden_id']) ? (int)$_POST['orden_id'] : 0;

        if ($historial_id <= 0 || $orden_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'IDs de referencia no válidos.']);
            exit;
        }

        $conn->begin_transaction();

        try {
            $sqlReturn = "UPDATE ordenes_talleres 
                        SET recibido = 1, fecha_entrega = NOW() 
                        WHERE id = $historial_id AND orden_produccion_id = $orden_id";
            
            if (!$conn->query($sqlReturn)) {
                throw new Exception("Error al asentar la recepción en la base de datos.");
            }

            // Verificar si todos los talleres de la orden ya fueron recibidos
            $chkPend = $conn->query("SELECT COUNT(*) AS total_pendientes FROM ordenes_talleres WHERE orden_produccion_id = $orden_id AND recibido = 0");
            $rowPend = $chkPend ? $chkPend->fetch_assoc() : null;
            $pendientes = (int)($rowPend['total_pendientes'] ?? 0);

            if ($pendientes === 0) {
                $sqlUpdateStatus = "UPDATE ordenes_produccion SET estado = 'en_empresa' WHERE id = $orden_id";
                if (!$conn->query($sqlUpdateStatus)) {
                    throw new Exception("Error al actualizar el estado de la orden.");
                }
            }

            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Mercancía recibida correctamente.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    restringirEscritura();

    header('Content-Type: application/json');

    switch ($action) {
        case 'obtener_costo_receta':
            $receta_id = $_POST['receta_id'] ?? null;
            if (!$receta_id) {
                throw new Exception("ID de guia de corte requerido");
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
                    throw new Exception("No se pudo calcular el costo de la guia de corte");
                }
            } else {
                throw new Exception("Guia de corte no encontrada");
            }
            break;

        case 'obtener_tallas':
            $rango_tallas_id = (int) ($_POST['rango_tallas_id'] ?? 0);
            if ($rango_tallas_id <= 0) {
                $receta_id = (int) ($_POST['receta_id'] ?? 0);
                if ($receta_id > 0) {
                    $stR = $conn->prepare('SELECT rango_tallas_id FROM recetas WHERE id = ? LIMIT 1');
                    $stR->bind_param('i', $receta_id);
                    $stR->execute();
                    $rr = $stR->get_result()->fetch_assoc();
                    $stR->close();
                    $rango_tallas_id = (int) ($rr['rango_tallas_id'] ?? 0);
                }
            }
            if ($rango_tallas_id <= 0) {
                throw new Exception('Rango de tallas no indicado');
            }
            $stmtT = $conn->prepare(
                'SELECT id, nombre FROM tallas WHERE rango_tallas_id = ? ORDER BY orden ASC, id ASC'
            );
            $stmtT->bind_param('i', $rango_tallas_id);
            $stmtT->execute();
            $resT = $stmtT->get_result();
            $tallas = [];
            while ($rowT = $resT->fetch_assoc()) {
                $tallas[] = [
                    'id' => (int) $rowT['id'],
                    'nombre' => $rowT['nombre'],
                ];
            }
            $stmtT->close();
            echo json_encode(['success' => true, 'tallas' => $tallas]);
            break;

        case 'crear':
            $receta_id = $_POST['receta_id'] ?? null;
            $talla_id_raw = (int) ($_POST['talla_id'] ?? 0);
            $talla_id = $talla_id_raw > 0 ? $talla_id_raw : null;
            $cantidad = $_POST['cantidad_a_producir'] ?? 0;
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $observaciones = $_POST['observaciones'] ?? '';

            if (!$receta_id || floatval($cantidad) <= 0) {
                throw new Exception("Guia de corte y cantidad válida son obligatorios");
            }

            try {
                $cantidad = inv_normalizar_cantidad_producto_terminado(floatval($cantidad));
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }

            if (!empty($fecha_inicio) && !empty($fecha_fin)) {
                if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
                    echo json_encode(['success' => false, 'message' => 'La fecha de fin no puede ser menor a la de inicio.']);
                    exit;
                }
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
                    throw new Exception("Guia de corte no encontrada");
                }
                $stmtRecetaInfo->close();

                $producto_id = $rowRecetaInfo['producto_id'];
                $rango_tallas_id = (int) $rowRecetaInfo['rango_tallas_id'];
                $tipo_produccion_id = $rowRecetaInfo['tipo_produccion_id'];

                validar_talla_para_rango($conn, $talla_id, $rango_tallas_id);

                // Validar que haya insumos disponibles en el inventario
                $sqlInsumosReceta = "SELECT rp.insumo_id, rp.cantidad_por_unidad, i.nombre AS insumo_nombre,
                                           COALESCE(um.permite_movimiento_decimal, 1) AS permite_movimiento_decimal
                                    FROM recetas_productos rp
                                    INNER JOIN insumos i ON rp.insumo_id = i.id
                                    LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
                                    WHERE rp.producto_id = ? 
                                      AND rp.rango_tallas_id = ? 
                                      AND rp.tipo_produccion_id = ?";
                $stmtInsumosReceta = $conn->prepare($sqlInsumosReceta);
                $stmtInsumosReceta->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                $stmtInsumosReceta->execute();
                $resultInsumosReceta = $stmtInsumosReceta->get_result();
                
                $insumosFaltantes = array();
                $cantidadProducir = $cantidad;

                while ($rowInsumo = $resultInsumosReceta->fetch_assoc()) {
                    $insumoId = $rowInsumo['insumo_id'];
                    $cantidadPorUnidad = floatval($rowInsumo['cantidad_por_unidad']);
                    $insumoNombre = $rowInsumo['insumo_nombre'];
                    $permiteDec = !isset($rowInsumo['permite_movimiento_decimal']) || (int) $rowInsumo['permite_movimiento_decimal'] === 1;
                    $cantidadNecesaria = inv_normalizar_cantidad_consumo_automatico(
                        $cantidadPorUnidad * $cantidadProducir,
                        $permiteDec
                    );
                    
                    $stockActual = InventarioLotes::stockDisponible($conn, 'insumo', (int) $insumoId);
                    
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
                    throw new Exception("No se encontró línea de producción asociada a esta guia de corte");
                }
                $receta_producto_id = $rowRecetaProducto['id'];
                $stmtRecetaProducto->close();

                // Ahora insertar la orden con el ID correcto de recetas_productos
                $stmt = $conn->prepare("
                    INSERT INTO ordenes_produccion (receta_producto_id, talla_id, cantidad_a_producir, fecha_inicio, fecha_fin, observaciones, estado)
                    VALUES (?, ?, ?, ?, ?, ?, 'pendiente')
                ");
                $stmt->bind_param("iidsss", $receta_producto_id, $talla_id, $cantidad, $fecha_inicio, $fecha_fin, $observaciones);
                $stmt->execute();
                $ordenId = $conn->insert_id;
                $stmt->close();

                // Guardar los talleres asignados
                $talleres = isset($_POST['talleres']) ? (is_array($_POST['talleres']) ? $_POST['talleres'] : json_decode($_POST['talleres'], true)) : [];
                if (!empty($talleres) && is_array($talleres)) {
                    $stmtOT = $conn->prepare("INSERT INTO ordenes_talleres (orden_produccion_id, taller_id, enviado, recibido) VALUES (?, ?, 0, 0)");
                    foreach ($talleres as $tallerId) {
                        $tid = (int)$tallerId;
                        if ($tid > 0) {
                            $stmtOT->bind_param("ii", $ordenId, $tid);
                            $stmtOT->execute();
                        }
                    }
                    $stmtOT->close();
                }

                // Generar números identificadores por cada unidad producida
                OrdenProduccionUnidades::sincronizarUnidadesOrden($conn, $ordenId, (int) $receta_id, !empty($talla_id) ? (int) $talla_id : null, (float) $cantidad);

                $conn->commit();
                $numeroOrden = numero_orden_produccion($ordenId, date('Y-m-d H:i:s'));
                echo json_encode([
                    'success' => true,
                    'message' => 'Orden ' . $numeroOrden . ' creada en estado pendiente',
                    'id' => $ordenId,
                    'numero_orden' => $numeroOrden
                ]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'aceptar_finalizacion':
            $ordenId = (int)($_POST['orden_id'] ?? 0);
            if ($ordenId <= 0) {
                throw new Exception("ID de orden inválido");
            }

            $conn->begin_transaction();
            try {
                $sqlOrden = "
                    SELECT op.id, op.estado, op.cantidad_a_producir, op.receta_producto_id, op.venta_id,
                           op.creado_en, op.fecha_inicio,
                           rp.producto_id, rp.rango_tallas_id, rp.tipo_produccion_id,
                           r.id AS receta_id
                    FROM ordenes_produccion op
                    INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                    LEFT JOIN recetas r ON r.producto_id = rp.producto_id
                        AND r.rango_tallas_id = rp.rango_tallas_id
                        AND r.tipo_produccion_id = rp.tipo_produccion_id
                    WHERE op.id = ?
                    LIMIT 1
                ";
                $stmtOrden = $conn->prepare($sqlOrden);
                $stmtOrden->bind_param("i", $ordenId);
                $stmtOrden->execute();
                $orden = $stmtOrden->get_result()->fetch_assoc();
                $stmtOrden->close();

                if (!$orden) {
                    throw new Exception("Orden no encontrada");
                }
                if ($orden['estado'] === 'finalizado') {
                    throw new Exception("La orden ya se encuentra finalizada");
                }

                $stmtPend = $conn->prepare(
                    "SELECT COUNT(*) AS total, SUM(CASE WHEN recibido = 0 THEN 1 ELSE 0 END) AS pendientes
                     FROM ordenes_talleres WHERE orden_produccion_id = ?"
                );
                $stmtPend->bind_param("i", $ordenId);
                $stmtPend->execute();
                $rowPend = $stmtPend->get_result()->fetch_assoc();
                $stmtPend->close();
                $talleresTotal = (int) ($rowPend['total'] ?? 0);
                $talleresPendientes = (int) ($rowPend['pendientes'] ?? 0);
                if ($talleresTotal === 0 || $talleresPendientes > 0) {
                    throw new Exception("Debe registrar la recepción de mercancía de todos los talleres antes de finalizar la orden.");
                }

                try {
                    $cantidadProducir = inv_normalizar_cantidad_producto_terminado(floatval($orden['cantidad_a_producir']));
                } catch (InvalidArgumentException $e) {
                    throw new Exception(
                        'La cantidad a producir de la orden debe ser un número entero. Edite la orden y corrija la cantidad antes de finalizar.'
                    );
                }
                $producto_id = (int)$orden['producto_id'];
                $rango_tallas_id = (int)$orden['rango_tallas_id'];
                $tipo_produccion_id = (int)$orden['tipo_produccion_id'];
                $recetaId = !empty($orden['receta_id']) ? (int)$orden['receta_id'] : null;
                $numeroOrden = numero_orden_produccion($ordenId, $orden['creado_en'] ?? $orden['fecha_inicio'] ?? null);

                $sqlInsumos = "SELECT rp.insumo_id, rp.cantidad_por_unidad,
                                      COALESCE(um.permite_movimiento_decimal, 1) AS permite_movimiento_decimal
                               FROM recetas_productos rp
                               INNER JOIN insumos i ON i.id = rp.insumo_id
                               LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
                               WHERE rp.producto_id = ? AND rp.rango_tallas_id = ? AND rp.tipo_produccion_id = ?";
                $stmtInsumos = $conn->prepare($sqlInsumos);
                $stmtInsumos->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                $stmtInsumos->execute();
                $resultInsumos = $stmtInsumos->get_result();

                $insumosFaltantes = [];
                $insumos = [];
                while ($rowInsumo = $resultInsumos->fetch_assoc()) {
                    $insumoId = (int)$rowInsumo['insumo_id'];
                    $permiteDec = !isset($rowInsumo['permite_movimiento_decimal']) || (int) $rowInsumo['permite_movimiento_decimal'] === 1;
                    $cantidadTotal = inv_normalizar_cantidad_consumo_automatico(
                        floatval($rowInsumo['cantidad_por_unidad']) * $cantidadProducir,
                        $permiteDec
                    );

                    $stockActual = InventarioLotes::stockDisponible($conn, 'insumo', $insumoId);
                    if ($stockActual < $cantidadTotal) {
                        $insumosFaltantes[] = $insumoId;
                    }

                    $insumos[] = [
                        'insumo_id' => $insumoId,
                        'cantidad_total' => $cantidadTotal,
                    ];
                }
                $stmtInsumos->close();

                $stYa = $conn->prepare(
                    "SELECT 1 FROM inventario_detalle
                     WHERE orden_produccion_id = ? AND tipo_item = 'insumo' AND tipo = 'salida'
                     LIMIT 1"
                );
                $stYa->bind_param('i', $ordenId);
                $stYa->execute();
                $yaConsumioInsumos = $stYa->get_result()->num_rows > 0;
                $stYa->close();

                if (!$yaConsumioInsumos && !empty($insumosFaltantes)) {
                    throw new Exception("No hay stock suficiente para finalizar la orden.");
                }

                if (!$yaConsumioInsumos) {
                    foreach ($insumos as $item) {
                        InventarioLotes::registrarSalida($conn, [
                            'tipo_item' => 'insumo',
                            'tipo_item_id' => (int) $item['insumo_id'],
                            'cantidad' => (float) $item['cantidad_total'],
                            'origen' => 'orden_produccion',
                            'origen_id' => $ordenId,
                            'observaciones' => "Salida de insumos por finalización de orden {$numeroOrden}",
                            'orden_produccion_id' => $ordenId,
                            'tipo_movimiento' => 'orden_produccion',
                        ]);
                    }
                }

                if (!$recetaId) {
                    $stRec = $conn->prepare(
                        'SELECT id FROM recetas WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ? LIMIT 1'
                    );
                    $stRec->bind_param('iii', $producto_id, $rango_tallas_id, $tipo_produccion_id);
                    $stRec->execute();
                    $rowRec = $stRec->get_result()->fetch_assoc();
                    $stRec->close();
                    $recetaId = $rowRec ? (int) $rowRec['id'] : 0;
                }
                if ($recetaId <= 0) {
                    throw new Exception('No se encontró la guía de corte para registrar el producto terminado.');
                }

                // Asegurar que las unidades individuales estén registradas
                $tallaIdFinal = !empty($orden['talla_id']) ? (int) $orden['talla_id'] : null;
                OrdenProduccionUnidades::sincronizarUnidadesOrden($conn, $ordenId, (int) $recetaId, $tallaIdFinal, (float) $cantidadProducir);

                InventarioLotes::registrarEntrada($conn, [
                    'tipo_item' => 'producto',
                    'tipo_item_id' => $recetaId,
                    'cantidad' => $cantidadProducir,
                    'origen' => 'orden_produccion',
                    'origen_id' => $ordenId,
                    'observaciones' => "Entrada de producto por finalización de orden {$numeroOrden}",
                    'orden_produccion_id' => $ordenId,
                    'tipo_movimiento' => 'orden_produccion',
                ]);

                $stmtUpdate = $conn->prepare("UPDATE ordenes_produccion SET estado = 'finalizado' WHERE id = ?");
                $stmtUpdate->bind_param("i", $ordenId);
                $stmtUpdate->execute();
                $stmtUpdate->close();

                $ventaIdEnlazada = (int) ($orden['venta_id'] ?? 0);
                if ($ventaIdEnlazada > 0) {
                    $stPend = $conn->prepare(
                        "SELECT COUNT(*) AS pend FROM ordenes_produccion WHERE venta_id = ? AND estado <> 'finalizado'"
                    );
                    $stPend->bind_param('i', $ventaIdEnlazada);
                    $stPend->execute();
                    $pendRow = $stPend->get_result()->fetch_assoc();
                    $stPend->close();
                    if ((int) ($pendRow['pend'] ?? 0) === 0) {
                        $stV = $conn->prepare(
                            "UPDATE ventas SET estado = 'aprobado' WHERE id = ? AND estado = 'en_proceso'"
                        );
                        $stV->bind_param('i', $ventaIdEnlazada);
                        $stV->execute();
                        $stV->close();

                        $stCot = $conn->prepare('SELECT cotizacion_id FROM ventas WHERE id = ? LIMIT 1');
                        $stCot->bind_param('i', $ventaIdEnlazada);
                        $stCot->execute();
                        $vr = $stCot->get_result()->fetch_assoc();
                        $stCot->close();
                        $cotId = $vr ? (int) ($vr['cotizacion_id'] ?? 0) : 0;
                        if ($cotId > 0) {
                            $statusAprobada = 2;
                            $stUpCot = $conn->prepare('UPDATE cotizaciones SET status = ? WHERE id_cotizacion = ?');
                            $stUpCot->bind_param('ii', $statusAprobada, $cotId);
                            $stUpCot->execute();
                            $stUpCot->close();
                        }
                    }
                }

                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Orden ' . $numeroOrden . ' finalizada y movimiento de inventario generado.', 'numero_orden' => $numeroOrden]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de orden requerido");

            $stmtEstado = $conn->prepare("SELECT estado, receta_producto_id, cantidad_a_producir, creado_en, fecha_inicio FROM ordenes_produccion WHERE id = ?");
            $stmtEstado->bind_param("i", $id);
            $stmtEstado->execute();
            $resultEstado = $stmtEstado->get_result();
            $ordenActual = $resultEstado->fetch_assoc();
            $stmtEstado->close();

            if (!$ordenActual) {
                throw new Exception("Orden no encontrada");
            }

            $receta_id = $_POST['receta_id'] ?? null;
            $talla_id_raw = (int) ($_POST['talla_id'] ?? 0);
            $talla_id = $talla_id_raw > 0 ? $talla_id_raw : null;
            $cantidad = $_POST['cantidad_a_producir'] ?? 0;
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $estado = $_POST['estado'] ?? 'pendiente';
            $observaciones = $_POST['observaciones'] ?? '';

            if (!empty($fecha_inicio) && !empty($fecha_fin)) {
                if (strtotime($fecha_fin) < strtotime($fecha_inicio)) {
                    echo json_encode(['success' => false, 'message' => 'La fecha de fin no puede ser menor a la de inicio.']);
                    exit;
                }
            }

            $cantidadFloat = floatval($cantidad);
            if ($cantidadFloat <= 0) {
                $cantidadFloat = floatval($ordenActual['cantidad_a_producir'] ?? 0);
            }
            try {
                $cantidad = inv_normalizar_cantidad_producto_terminado($cantidadFloat);
            } catch (InvalidArgumentException $e) {
                throw new Exception($e->getMessage());
            }

            // Si se está cambiando la receta, obtener el recetas_productos.id correcto
            $receta_producto_id = $ordenActual['receta_producto_id']; // Mantener el actual por defecto
            $rango_tallas_id_orden = 0;

            if ($receta_id) {
                // Obtener información de la receta
                $sqlRecetaInfo = "SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?";
                $stmtRecetaInfo = $conn->prepare($sqlRecetaInfo);
                $stmtRecetaInfo->bind_param("i", $receta_id);
                $stmtRecetaInfo->execute();
                $resultRecetaInfo = $stmtRecetaInfo->get_result();

                if ($rowRecetaInfo = $resultRecetaInfo->fetch_assoc()) {
                    $rango_tallas_id_orden = (int) $rowRecetaInfo['rango_tallas_id'];
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

            if ($rango_tallas_id_orden <= 0) {
                $stRg = $conn->prepare(
                    'SELECT rp.rango_tallas_id FROM ordenes_produccion op
                     INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
                     WHERE op.id = ? LIMIT 1'
                );
                $stRg->bind_param('i', $id);
                $stRg->execute();
                $rgRow = $stRg->get_result()->fetch_assoc();
                $stRg->close();
                $rango_tallas_id_orden = (int) ($rgRow['rango_tallas_id'] ?? 0);
            }

            if ($rango_tallas_id_orden > 0) {
                validar_talla_para_rango($conn, $talla_id, $rango_tallas_id_orden);
            }

            $conn->begin_transaction();
            try {
                $stmt = $conn->prepare("
                    UPDATE ordenes_produccion 
                    SET receta_producto_id = ?, 
                        talla_id = ?,
                        cantidad_a_producir = ?, 
                        fecha_inicio = ?, 
                        fecha_fin = ?, 
                        estado = ?, 
                        observaciones = ?
                    WHERE id = ?
                ");
                $stmt->bind_param("iidssssi", $receta_producto_id, $talla_id, $cantidad, $fecha_inicio, $fecha_fin, $estado, $observaciones, $id);
                $stmt->execute();
                $stmt->close();

                // Sincronizar talleres si se enviaron
                if (isset($_POST['talleres'])) {
                    $talleres = is_array($_POST['talleres']) ? $_POST['talleres'] : json_decode($_POST['talleres'], true);
                    $talleres = is_array($talleres) ? $talleres : [];
                    
                    $stmtDelOT = $conn->prepare("DELETE FROM ordenes_talleres WHERE orden_produccion_id = ?");
                    $stmtDelOT->bind_param("i", $id);
                    $stmtDelOT->execute();
                    $stmtDelOT->close();

                    if (!empty($talleres)) {
                        $stmtOT = $conn->prepare("INSERT INTO ordenes_talleres (orden_produccion_id, taller_id, enviado, recibido) VALUES (?, ?, 0, 0)");
                        foreach ($talleres as $tallerId) {
                            $tid = (int)$tallerId;
                            if ($tid > 0) {
                                $stmtOT->bind_param("ii", $id, $tid);
                                $stmtOT->execute();
                            }
                        }
                        $stmtOT->close();
                    }
                }
                
                // Si se cambia el estado a 'en_proceso' o 'finalizado' desde 'pendiente', descontar insumos
                if (($estado === 'en_proceso' || $estado === 'finalizado') && 
                    $ordenActual['estado'] === 'pendiente') {
                    
                    $recetaId = $receta_id ?? $ordenActual['receta_producto_id'];
                    $cantidadProducir = $cantidad;
                    
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
                        $sqlInsumos = "SELECT rp.insumo_id, rp.cantidad_por_unidad,
                                              COALESCE(um.permite_movimiento_decimal, 1) AS permite_movimiento_decimal
                                       FROM recetas_productos rp
                                       INNER JOIN insumos i ON i.id = rp.insumo_id
                                       LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
                                       WHERE rp.producto_id = ?
                                         AND rp.rango_tallas_id = ?
                                         AND rp.tipo_produccion_id = ?";
                        $stmtInsumos = $conn->prepare($sqlInsumos);
                        $stmtInsumos->bind_param("iii", $producto_id, $rango_tallas_id, $tipo_produccion_id);
                        $stmtInsumos->execute();
                        $resultInsumos = $stmtInsumos->get_result();
                        
                        while ($rowInsumo = $resultInsumos->fetch_assoc()) {
                            $insumoId = $rowInsumo['insumo_id'];
                            $cantidadPorUnidad = floatval($rowInsumo['cantidad_por_unidad']);
                            $permiteDec = !isset($rowInsumo['permite_movimiento_decimal']) || (int) $rowInsumo['permite_movimiento_decimal'] === 1;
                            $cantidadTotal = inv_normalizar_cantidad_consumo_automatico(
                                $cantidadPorUnidad * floatval($cantidadProducir),
                                $permiteDec
                            );
                            $numeroOrden = numero_orden_produccion((int)$id, $ordenActual['creado_en'] ?? $ordenActual['fecha_inicio'] ?? null);
                            InventarioLotes::registrarSalida($conn, [
                                'tipo_item' => 'insumo',
                                'tipo_item_id' => (int) $insumoId,
                                'cantidad' => $cantidadTotal,
                                'origen' => 'orden_produccion',
                                'origen_id' => (int) $id,
                                'observaciones' => "Descuento por orden de producción {$numeroOrden}",
                                'orden_produccion_id' => (int) $id,
                                'tipo_movimiento' => 'orden_produccion',
                            ]);
                        }
                        $stmtInsumos->close();
                    }
                    $stmtRecetaInfo->close();
                }
                
                // Sincronizar unidades si aumentó la cantidad o no existen
                $recetaIdSync = (int) ($receta_id ?? $ordenActual['receta_producto_id']);
                OrdenProduccionUnidades::sincronizarUnidadesOrden($conn, (int)$id, $recetaIdSync, !empty($talla_id) ? (int)$talla_id : null, (float)$cantidad);

                $conn->commit();
                $numeroOrden = numero_orden_produccion((int)$id, $ordenActual['creado_en'] ?? $ordenActual['fecha_inicio'] ?? null);
                echo json_encode(['success' => true, 'message' => 'Orden ' . $numeroOrden . ' actualizada', 'numero_orden' => $numeroOrden]);
            } catch (Exception $e) {
                $conn->rollback();
                throw $e;
            }
            break;

        case 'listar_unidades_orden':
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
            echo json_encode(['success' => true, 'unidades' => $unidades]);
            break;

        case 'obtener_stock_insumos':
            $receta_id = (int) ($_POST['receta_id'] ?? 0);
            if ($receta_id <= 0) {
                throw new Exception('Guia de corte requerida');
            }
            $stmtR = $conn->prepare('SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?');
            $stmtR->bind_param('i', $receta_id);
            $stmtR->execute();
            $rr = $stmtR->get_result()->fetch_assoc();
            $stmtR->close();
            if (!$rr) {
                throw new Exception('Guia de corte no encontrada');
            }
            $pid = (int) $rr['producto_id'];
            $rid = (int) $rr['rango_tallas_id'];
            $tid = (int) $rr['tipo_produccion_id'];

            $sql = "
                SELECT rp.insumo_id, rp.cantidad_por_unidad, i.nombre AS insumo_nombre,
                       COALESCE(um.codigo, '') AS unidad_medida
                FROM recetas_productos rp
                INNER JOIN insumos i ON i.id = rp.insumo_id
                LEFT JOIN unidad_medida um ON um.id = i.unidad_medida_id
                WHERE rp.producto_id = ? AND rp.rango_tallas_id = ? AND rp.tipo_produccion_id = ?
                ORDER BY i.nombre
            ";
            $st = $conn->prepare($sql);
            $st->bind_param('iii', $pid, $rid, $tid);
            $st->execute();
            $resList = $st->get_result();
            $insumos = [];
            while ($row = $resList->fetch_assoc()) {
                $insumoId = (int) $row['insumo_id'];
                $insumos[] = [
                    'insumo_nombre' => $row['insumo_nombre'],
                    'unidad_medida' => $row['unidad_medida'],
                    'cantidad_por_unidad' => $row['cantidad_por_unidad'],
                    'stock_actual' => InventarioLotes::stockDisponible($conn, 'insumo', $insumoId),
                ];
            }
            $st->close();
            echo json_encode(['success' => true, 'insumos' => $insumos]);
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