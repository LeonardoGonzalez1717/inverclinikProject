<?php
require_once __DIR__ . '/orden_numero.php';

/**
 * Inventario por lotes con salida LIFO (última entrada, primera salida).
 * Cada entrada genera un lote; cada salida descuenta primero el lote más reciente.
 */
class InventarioLotes
{
    public static function asegurar(mysqli $conn): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;

        $conn->query("
            CREATE TABLE IF NOT EXISTS inventario_lotes (
                id INT(11) NOT NULL AUTO_INCREMENT,
                numero_lote VARCHAR(40) NOT NULL,
                tipo_item ENUM('insumo','producto') NOT NULL,
                tipo_item_id INT(11) NOT NULL,
                fecha_entrada DATETIME NOT NULL,
                cantidad_inicial DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                cantidad_restante DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                costo_unitario DECIMAL(12,2) DEFAULT NULL,
                origen VARCHAR(50) DEFAULT 'manual',
                origen_id INT(11) DEFAULT NULL,
                inventario_detalle_id INT(11) DEFAULT NULL,
                almacen_id INT(11) DEFAULT NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_numero_lote (numero_lote),
                KEY idx_item_fecha (tipo_item, tipo_item_id, fecha_entrada, id),
                KEY idx_item_restante (tipo_item, tipo_item_id, cantidad_restante)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        $conn->query("
            CREATE TABLE IF NOT EXISTS inventario_detalle_lotes (
                id INT(11) NOT NULL AUTO_INCREMENT,
                inventario_detalle_id INT(11) NOT NULL,
                lote_id INT(11) NOT NULL,
                cantidad DECIMAL(12,2) NOT NULL,
                PRIMARY KEY (id),
                KEY idx_detalle (inventario_detalle_id),
                KEY idx_lote (lote_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        self::migrarStockExistente($conn);
    }

    public static function stockDisponible(mysqli $conn, string $tipoItem, int $tipoItemId): float
    {
        self::asegurar($conn);
        self::asegurarLoteApertura($conn, $tipoItem, $tipoItemId);

        $st = $conn->prepare(
            'SELECT COALESCE(SUM(cantidad_restante), 0) AS s
             FROM inventario_lotes
             WHERE tipo_item = ? AND tipo_item_id = ? AND cantidad_restante > 0'
        );
        $st->bind_param('si', $tipoItem, $tipoItemId);
        $st->execute();
        $sum = (float) ($st->get_result()->fetch_assoc()['s'] ?? 0);
        $st->close();

        return round($sum, 2);
    }

    /**
     * @param array{
     *   tipo_item: string,
     *   tipo_item_id: int,
     *   cantidad: float,
     *   fecha_entrada?: string|null,
     *   origen?: string,
     *   origen_id?: int|null,
     *   observaciones?: string,
     *   orden_produccion_id?: int|null,
     *   costo_unitario?: float|null,
     *   tipo_movimiento?: string
     * } $opts
     * @return array{lote_id:int,numero_lote:string,detalle_id:int}
     */
    public static function registrarEntrada(mysqli $conn, array $opts): array
    {
        self::asegurar($conn);

        $tipoItem = self::tipoItem($opts['tipo_item'] ?? '');
        $tipoItemId = (int) ($opts['tipo_item_id'] ?? 0);
        $cantidad = round((float) ($opts['cantidad'] ?? 0), 2);
        if ($tipoItemId <= 0 || $cantidad <= 0) {
            throw new Exception('Datos inválidos para registrar la entrada de inventario.');
        }

        $fechaEntrada = self::normalizarFecha($opts['fecha_entrada'] ?? null);
        $origen = trim((string) ($opts['origen'] ?? 'manual'));
        if ($origen === '') {
            $origen = 'manual';
        }
        $origenId = isset($opts['origen_id']) ? (int) $opts['origen_id'] : 0;
        $obs = (string) ($opts['observaciones'] ?? '');
        $opId = isset($opts['orden_produccion_id']) ? (int) $opts['orden_produccion_id'] : 0;
        $costo = isset($opts['costo_unitario']) ? (float) $opts['costo_unitario'] : null;
        $tipoMov = (string) ($opts['tipo_movimiento'] ?? $origen);

        $detalleId = self::insertarDetalle($conn, $tipoItem, $tipoItemId, 'entrada', $cantidad, $origen, $obs, $opId);

        $tmp = 'TMP-' . bin2hex(random_bytes(6));
        $costoSql = $costo === null ? 'NULL' : (string) round($costo, 2);
        $origenIdSql = $origenId > 0 ? (string) $origenId : 'NULL';
        $tipoEsc = $conn->real_escape_string($tipoItem);
        $tmpEsc = $conn->real_escape_string($tmp);
        $fechaEsc = $conn->real_escape_string($fechaEntrada);
        $origenEsc = $conn->real_escape_string($origen);
        $ok = $conn->query(
            "INSERT INTO inventario_lotes
                (numero_lote, tipo_item, tipo_item_id, fecha_entrada, cantidad_inicial, cantidad_restante,
                 costo_unitario, origen, origen_id, inventario_detalle_id)
             VALUES (
                '$tmpEsc', '$tipoEsc', $tipoItemId, '$fechaEsc', $cantidad, $cantidad,
                $costoSql, '$origenEsc', $origenIdSql, $detalleId
             )"
        );
        if (!$ok) {
            throw new Exception('No se pudo crear el lote de inventario.');
        }

        $loteId = (int) $conn->insert_id;
        $numero = numero_lote_inventario($loteId, $fechaEntrada);
        $numEsc = $conn->real_escape_string($numero);
        $conn->query("UPDATE inventario_lotes SET numero_lote = '$numEsc' WHERE id = $loteId");

        $stDl = $conn->prepare(
            'INSERT INTO inventario_detalle_lotes (inventario_detalle_id, lote_id, cantidad) VALUES (?, ?, ?)'
        );
        $stDl->bind_param('iid', $detalleId, $loteId, $cantidad);
        $stDl->execute();
        $stDl->close();

        self::sincronizarStock($conn, $tipoItem, $tipoItemId, $tipoMov, $opId > 0 ? $opId : null);

        return [
            'lote_id' => $loteId,
            'numero_lote' => $numero,
            'detalle_id' => $detalleId,
        ];
    }

    /**
     * Salida LIFO: consume primero el lote de fecha de entrada más reciente.
     *
     * @param array{
     *   tipo_item: string,
     *   tipo_item_id: int,
     *   cantidad: float,
     *   origen?: string,
     *   origen_id?: int|null,
     *   observaciones?: string,
     *   orden_produccion_id?: int|null,
     *   tipo_movimiento?: string
     * } $opts
     * @return array{detalle_id:int,lotes:list<array{lote_id:int,numero_lote:string,cantidad:float}>}
     */
    public static function registrarSalida(mysqli $conn, array $opts): array
    {
        self::asegurar($conn);

        $tipoItem = self::tipoItem($opts['tipo_item'] ?? '');
        $tipoItemId = (int) ($opts['tipo_item_id'] ?? 0);
        $cantidad = round((float) ($opts['cantidad'] ?? 0), 2);
        if ($tipoItemId <= 0 || $cantidad <= 0) {
            throw new Exception('Datos inválidos para registrar la salida de inventario.');
        }

        self::asegurarLoteApertura($conn, $tipoItem, $tipoItemId);

        $origen = trim((string) ($opts['origen'] ?? 'manual'));
        if ($origen === '') {
            $origen = 'manual';
        }
        $obs = (string) ($opts['observaciones'] ?? '');
        $opId = isset($opts['orden_produccion_id']) ? (int) $opts['orden_produccion_id'] : 0;
        $tipoMov = (string) ($opts['tipo_movimiento'] ?? $origen);

        $st = $conn->prepare(
            'SELECT id, numero_lote, cantidad_restante
             FROM inventario_lotes
             WHERE tipo_item = ? AND tipo_item_id = ? AND cantidad_restante > 0
             ORDER BY fecha_entrada DESC, id DESC
             FOR UPDATE'
        );
        $st->bind_param('si', $tipoItem, $tipoItemId);
        $st->execute();
        $res = $st->get_result();
        $lotes = [];
        while ($row = $res->fetch_assoc()) {
            $lotes[] = $row;
        }
        $st->close();

        $pendiente = $cantidad;
        $consumos = [];
        foreach ($lotes as $lote) {
            if ($pendiente <= 0.004) {
                break;
            }
            $restante = round((float) $lote['cantidad_restante'], 2);
            $tomar = min($restante, $pendiente);
            $tomar = round($tomar, 2);
            if ($tomar <= 0) {
                continue;
            }
            $nuevoRestante = round($restante - $tomar, 2);
            if ($nuevoRestante < 0.005) {
                $nuevoRestante = 0;
            }
            $loteId = (int) $lote['id'];
            $stU = $conn->prepare('UPDATE inventario_lotes SET cantidad_restante = ? WHERE id = ?');
            $stU->bind_param('di', $nuevoRestante, $loteId);
            $stU->execute();
            $stU->close();

            $consumos[] = [
                'lote_id' => $loteId,
                'numero_lote' => (string) $lote['numero_lote'],
                'cantidad' => $tomar,
            ];
            $pendiente = round($pendiente - $tomar, 2);
        }

        if ($pendiente > 0.004) {
            throw new Exception(
                'No hay stock suficiente por lotes (LIFO) para completar la salida. Faltan '
                . number_format($pendiente, 2, ',', '.') . ' unidades.'
            );
        }

        $obsLotes = $obs;

        $detalleId = self::insertarDetalle($conn, $tipoItem, $tipoItemId, 'salida', $cantidad, $origen, $obsLotes, $opId);

        $stDl = $conn->prepare(
            'INSERT INTO inventario_detalle_lotes (inventario_detalle_id, lote_id, cantidad) VALUES (?, ?, ?)'
        );
        foreach ($consumos as $c) {
            $lid = $c['lote_id'];
            $cant = $c['cantidad'];
            $stDl->bind_param('iid', $detalleId, $lid, $cant);
            $stDl->execute();
        }
        $stDl->close();

        self::sincronizarStock($conn, $tipoItem, $tipoItemId, $tipoMov, $opId > 0 ? $opId : null);

        return [
            'detalle_id' => $detalleId,
            'lotes' => $consumos,
        ];
    }

    public static function resumenLotesTexto(mysqli $conn, string $tipoItem, int $tipoItemId): string
    {
        self::asegurar($conn);
        $tipoItem = self::tipoItem($tipoItem);
        $st = $conn->prepare(
            'SELECT numero_lote
             FROM inventario_lotes
             WHERE tipo_item = ? AND tipo_item_id = ? AND cantidad_restante > 0
             ORDER BY fecha_entrada DESC, id DESC'
        );
        $st->bind_param('si', $tipoItem, $tipoItemId);
        $st->execute();
        $res = $st->get_result();
        $partes = [];
        while ($row = $res->fetch_assoc()) {
            $partes[] = $row['numero_lote'];
        }
        $st->close();

        return $partes === [] ? '—' : implode(' | ', $partes);
    }

    private static function migrarStockExistente(mysqli $conn): void
    {
        $tieneNuevo = self::tieneInventarioNuevo($conn);
        if ($tieneNuevo) {
            $sql = "SELECT tipo_item, tipo_item_id, stock_actual FROM inventario WHERE stock_actual > 0";
        } else {
            $sql = "SELECT 'insumo' AS tipo_item, insumo_id AS tipo_item_id, stock_actual FROM inventario WHERE stock_actual > 0 AND insumo_id IS NOT NULL";
        }
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                self::asegurarLoteApertura(
                    $conn,
                    (string) $row['tipo_item'],
                    (int) $row['tipo_item_id']
                );
            }
        }

        if (!$tieneNuevo) {
            $chk = $conn->query("SHOW TABLES LIKE 'inventario_productos'");
            if ($chk && $chk->num_rows > 0) {
                $rp = $conn->query(
                    "SELECT r.id AS receta_id, ip.stock_actual
                     FROM inventario_productos ip
                     INNER JOIN recetas r ON r.producto_id = ip.producto_id
                       AND r.rango_tallas_id = ip.rango_tallas_id
                       AND r.tipo_produccion_id = ip.tipo_produccion_id
                     WHERE ip.stock_actual > 0"
                );
                if ($rp) {
                    while ($row = $rp->fetch_assoc()) {
                        self::asegurarLoteApertura($conn, 'producto', (int) $row['receta_id']);
                    }
                }
            }
        }
    }

    private static function asegurarLoteApertura(mysqli $conn, string $tipoItem, int $tipoItemId): void
    {
        if ($tipoItemId <= 0) {
            return;
        }
        $tipoItem = self::tipoItem($tipoItem);

        $stockAgg = self::stockAgregado($conn, $tipoItem, $tipoItemId);
        $st = $conn->prepare(
            'SELECT COALESCE(SUM(cantidad_restante), 0) AS s FROM inventario_lotes WHERE tipo_item = ? AND tipo_item_id = ?'
        );
        $st->bind_param('si', $tipoItem, $tipoItemId);
        $st->execute();
        $sumLotes = (float) ($st->get_result()->fetch_assoc()['s'] ?? 0);
        $st->close();

        $diff = round($stockAgg - $sumLotes, 2);
        if ($diff <= 0.004) {
            return;
        }

        $fechaApertura = '2000-01-01 00:00:00';
        $tmp = 'TMP-' . bin2hex(random_bytes(6));
        $tmpEsc = $conn->real_escape_string($tmp);
        $tipoEsc = $conn->real_escape_string($tipoItem);
        $ok = $conn->query(
            "INSERT INTO inventario_lotes
                (numero_lote, tipo_item, tipo_item_id, fecha_entrada, cantidad_inicial, cantidad_restante, origen)
             VALUES ('$tmpEsc', '$tipoEsc', $tipoItemId, '$fechaApertura', $diff, $diff, 'apertura')"
        );
        if (!$ok) {
            return;
        }
        $loteId = (int) $conn->insert_id;
        $numero = numero_lote_inventario($loteId, date('Y-m-d H:i:s'));
        $numEsc = $conn->real_escape_string($numero);
        $conn->query("UPDATE inventario_lotes SET numero_lote = '$numEsc' WHERE id = $loteId");
    }

    private static function stockAgregado(mysqli $conn, string $tipoItem, int $tipoItemId): float
    {
        if (self::tieneInventarioNuevo($conn)) {
            $st = $conn->prepare(
                'SELECT stock_actual FROM inventario WHERE tipo_item = ? AND tipo_item_id = ? LIMIT 1'
            );
            $st->bind_param('si', $tipoItem, $tipoItemId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            return $row ? (float) $row['stock_actual'] : 0.0;
        }

        if ($tipoItem === 'insumo') {
            $st = $conn->prepare('SELECT stock_actual FROM inventario WHERE insumo_id = ? LIMIT 1');
            $st->bind_param('i', $tipoItemId);
            $st->execute();
            $row = $st->get_result()->fetch_assoc();
            $st->close();
            return $row ? (float) $row['stock_actual'] : 0.0;
        }

        $stR = $conn->prepare('SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?');
        $stR->bind_param('i', $tipoItemId);
        $stR->execute();
        $r = $stR->get_result()->fetch_assoc();
        $stR->close();
        if (!$r) {
            return 0.0;
        }
        $pid = (int) $r['producto_id'];
        $rt = (int) $r['rango_tallas_id'];
        $tp = (int) $r['tipo_produccion_id'];
        $st = $conn->prepare(
            'SELECT stock_actual FROM inventario_productos
             WHERE producto_id = ? AND rango_tallas_id = ? AND tipo_produccion_id = ? LIMIT 1'
        );
        $st->bind_param('iii', $pid, $rt, $tp);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ? (float) $row['stock_actual'] : 0.0;
    }

    private static function sincronizarStock(
        mysqli $conn,
        string $tipoItem,
        int $tipoItemId,
        string $tipoMovimiento,
        ?int $ordenProduccionId
    ): void {
        $st = $conn->prepare(
            'SELECT COALESCE(SUM(cantidad_restante), 0) AS s FROM inventario_lotes WHERE tipo_item = ? AND tipo_item_id = ?'
        );
        $st->bind_param('si', $tipoItem, $tipoItemId);
        $st->execute();
        $sum = round((float) ($st->get_result()->fetch_assoc()['s'] ?? 0), 2);
        $st->close();

        $movPermitidos = ['compra', 'orden_produccion', 'manual', 'ajuste'];
        $tipoMov = in_array($tipoMovimiento, $movPermitidos, true) ? $tipoMovimiento : 'manual';
        $opSql = $ordenProduccionId && $ordenProduccionId > 0 ? (string) (int) $ordenProduccionId : 'NULL';

        if (self::tieneInventarioNuevo($conn)) {
            $tipoEsc = $conn->real_escape_string($tipoItem);
            $movEsc = $conn->real_escape_string($tipoMov);
            $conn->query(
                "INSERT INTO inventario (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion, orden_produccion_id)
                 VALUES ('$tipoEsc', $tipoItemId, $sum, '$movEsc', NOW(), $opSql)
                 ON DUPLICATE KEY UPDATE
                    stock_actual = VALUES(stock_actual),
                    tipo_movimiento = VALUES(tipo_movimiento),
                    ultima_actualizacion = NOW(),
                    orden_produccion_id = VALUES(orden_produccion_id)"
            );
            return;
        }

        if ($tipoItem === 'insumo') {
            $conn->query(
                "INSERT INTO inventario (insumo_id, stock_actual, ultima_actualizacion)
                 VALUES ($tipoItemId, $sum, NOW())
                 ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()"
            );
            return;
        }

        $stR = $conn->prepare('SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?');
        $stR->bind_param('i', $tipoItemId);
        $stR->execute();
        $r = $stR->get_result()->fetch_assoc();
        $stR->close();
        if (!$r) {
            return;
        }
        $pid = (int) $r['producto_id'];
        $rt = (int) $r['rango_tallas_id'];
        $tp = (int) $r['tipo_produccion_id'];
        $conn->query(
            "INSERT INTO inventario_productos (producto_id, rango_tallas_id, tipo_produccion_id, stock_actual, ultima_actualizacion)
             VALUES ($pid, $rt, $tp, $sum, NOW())
             ON DUPLICATE KEY UPDATE stock_actual = VALUES(stock_actual), ultima_actualizacion = NOW()"
        );
    }

    private static function insertarDetalle(
        mysqli $conn,
        string $tipoItem,
        int $tipoItemId,
        string $tipo,
        float $cantidad,
        string $origen,
        string $observaciones,
        int $ordenProduccionId
    ): int {
        $opId = $ordenProduccionId > 0 ? $ordenProduccionId : null;

        if ($tipoItem === 'insumo') {
            if ($opId) {
                $st = $conn->prepare(
                    'INSERT INTO inventario_detalle (tipo_item, insumo_id, tipo, cantidad, origen, observaciones, orden_produccion_id)
                     VALUES (\'insumo\', ?, ?, ?, ?, ?, ?)'
                );
                $st->bind_param('isdssi', $tipoItemId, $tipo, $cantidad, $origen, $observaciones, $opId);
            } else {
                $st = $conn->prepare(
                    'INSERT INTO inventario_detalle (tipo_item, insumo_id, tipo, cantidad, origen, observaciones)
                     VALUES (\'insumo\', ?, ?, ?, ?, ?)'
                );
                $st->bind_param('isdss', $tipoItemId, $tipo, $cantidad, $origen, $observaciones);
            }
            $st->execute();
            $id = (int) $st->insert_id;
            $st->close();
            return $id;
        }

        $tieneRecetaId = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'receta_id'")->num_rows > 0;
        if ($tieneRecetaId) {
            if ($opId) {
                $st = $conn->prepare(
                    'INSERT INTO inventario_detalle (tipo_item, receta_id, tipo, cantidad, origen, observaciones, orden_produccion_id)
                     VALUES (\'producto\', ?, ?, ?, ?, ?, ?)'
                );
                $st->bind_param('isdssi', $tipoItemId, $tipo, $cantidad, $origen, $observaciones, $opId);
            } else {
                $st = $conn->prepare(
                    'INSERT INTO inventario_detalle (tipo_item, receta_id, tipo, cantidad, origen, observaciones)
                     VALUES (\'producto\', ?, ?, ?, ?, ?)'
                );
                $st->bind_param('isdss', $tipoItemId, $tipo, $cantidad, $origen, $observaciones);
            }
            $st->execute();
            $id = (int) $st->insert_id;
            $st->close();
            return $id;
        }

        $stR = $conn->prepare('SELECT producto_id, rango_tallas_id, tipo_produccion_id FROM recetas WHERE id = ?');
        $stR->bind_param('i', $tipoItemId);
        $stR->execute();
        $r = $stR->get_result()->fetch_assoc();
        $stR->close();
        if (!$r) {
            throw new Exception('No se encontró la guía de corte para el movimiento de inventario.');
        }
        $pid = (int) $r['producto_id'];
        $rt = (int) $r['rango_tallas_id'];
        $tp = (int) $r['tipo_produccion_id'];
        $st = $conn->prepare(
            'INSERT INTO inventario_detalle (tipo_item, producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, origen, observaciones)
             VALUES (\'producto\', ?, ?, ?, ?, ?, ?, ?)'
        );
        $st->bind_param('iiisdss', $pid, $rt, $tp, $tipo, $cantidad, $origen, $observaciones);
        $st->execute();
        $id = (int) $st->insert_id;
        $st->close();
        return $id;
    }

    private static function tipoItem(string $tipo): string
    {
        return $tipo === 'producto' ? 'producto' : 'insumo';
    }

    private static function normalizarFecha(?string $fecha): string
    {
        if ($fecha === null || trim($fecha) === '' || $fecha === '0000-00-00' || $fecha === '0000-00-00 00:00:00') {
            return date('Y-m-d H:i:s');
        }
        $fecha = trim($fecha);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return $fecha . ' ' . date('H:i:s');
        }
        $ts = strtotime($fecha);
        if ($ts === false) {
            return date('Y-m-d H:i:s');
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private static function tieneInventarioNuevo(mysqli $conn): bool
    {
        static $v = null;
        if ($v === null) {
            $chk = $conn->query("SHOW COLUMNS FROM inventario LIKE 'tipo_item'");
            $v = $chk && $chk->num_rows > 0;
        }
        return $v;
    }
}
