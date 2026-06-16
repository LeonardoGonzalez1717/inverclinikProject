<?php

/**
 * Cuentas por cobrar vinculadas a ventas generadas desde comprobante de cotización (financiada).
 */
class CuentasPorCobrar
{
    public static function asegurarTablas(mysqli $conn): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;

        $conn->query(
            'CREATE TABLE IF NOT EXISTS cuentas_por_cobrar (
                id INT AUTO_INCREMENT PRIMARY KEY,
                venta_id INT NOT NULL,
                cliente_id INT NOT NULL,
                cotizacion_id INT DEFAULT NULL,
                monto_total DECIMAL(12,2) NOT NULL,
                monto_pagado DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                saldo_pendiente DECIMAL(12,2) NOT NULL,
                estado ENUM(\'pendiente\',\'pagada\',\'cancelada\') NOT NULL DEFAULT \'pendiente\',
                creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                actualizado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_cxc_venta (venta_id),
                KEY idx_cxc_cliente (cliente_id),
                KEY idx_cxc_estado (estado),
                CONSTRAINT fk_cxc_venta FOREIGN KEY (venta_id) REFERENCES ventas(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_cxc_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id)
                    ON UPDATE CASCADE,
                CONSTRAINT fk_cxc_cotizacion FOREIGN KEY (cotizacion_id) REFERENCES cotizaciones(id_cotizacion)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS cuentas_por_cobrar_pagos (
                id INT AUTO_INCREMENT PRIMARY KEY,
                cuenta_id INT NOT NULL,
                monto DECIMAL(12,2) NOT NULL,
                forma_pago_id INT DEFAULT NULL,
                referencia VARCHAR(120) DEFAULT NULL,
                observaciones VARCHAR(255) DEFAULT NULL,
                es_pago_inicial TINYINT(1) NOT NULL DEFAULT 0,
                origen ENUM(\'cliente\',\'interno\') NOT NULL DEFAULT \'interno\',
                fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_cxc_pagos_cuenta (cuenta_id),
                CONSTRAINT fk_cxc_pagos_cuenta FOREIGN KEY (cuenta_id) REFERENCES cuentas_por_cobrar(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_cxc_pagos_forma FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
        );

        $chkApr = $conn->query("SHOW COLUMNS FROM cuentas_por_cobrar_pagos LIKE 'aprobado'");
        if (!$chkApr || $chkApr->num_rows === 0) {
            $conn->query(
                'ALTER TABLE cuentas_por_cobrar_pagos
                 ADD COLUMN aprobado TINYINT(1) NOT NULL DEFAULT 0
                 COMMENT \'1=verificado por el equipo en ventas\' AFTER origen'
            );
            $conn->query('UPDATE cuentas_por_cobrar_pagos SET aprobado = 1');
        }

        self::corregirPagosInternosPendientes($conn);
    }

    /** Abonos del equipo deben contar al saldo; corrige registros anteriores mal marcados. */
    private static function corregirPagosInternosPendientes(mysqli $conn): void
    {
        $res = $conn->query(
            "SELECT DISTINCT cuenta_id FROM cuentas_por_cobrar_pagos WHERE origen = 'interno' AND aprobado = 0"
        );
        if (!$res || $res->num_rows === 0) {
            return;
        }

        $cuentas = [];
        while ($row = $res->fetch_assoc()) {
            $cuentas[] = (int) $row['cuenta_id'];
        }

        $conn->query(
            "UPDATE cuentas_por_cobrar_pagos SET aprobado = 1 WHERE origen = 'interno' AND aprobado = 0"
        );

        foreach ($cuentas as $cuentaId) {
            if ($cuentaId > 0) {
                self::recalcularTotalesCuenta($conn, $cuentaId);
            }
        }
    }

    public static function obtenerVentaIdPorCotizacion(mysqli $conn, int $idCotizacion): ?int
    {
        $stmt = $conn->prepare('SELECT id FROM ventas WHERE cotizacion_id = ? ORDER BY id DESC LIMIT 1');
        $stmt->bind_param('i', $idCotizacion);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int) $row['id'] : null;
    }

    /**
     * Crea venta + cuenta por cobrar (financiada) o venta pagada (contado) al cargar comprobante del cliente.
     *
     * @return array{venta_id:int,cuenta_id:?int,creado:bool}
     */
    public static function procesarComprobanteCotizacion(
        mysqli $conn,
        int $idCotizacion,
        int $idCliente,
        float $montoPago,
        int $formaPagoId,
        string $referencia,
        bool $esFinanciada,
        float $totalCot
    ): array {
        self::asegurarTablas($conn);

        $ventaExistente = self::obtenerVentaIdPorCotizacion($conn, $idCotizacion);
        if ($ventaExistente !== null) {
            self::actualizarPagoInicialComprobante(
                $conn,
                $ventaExistente,
                $montoPago,
                $formaPagoId,
                $referencia,
                $esFinanciada,
                $totalCot
            );

            $cuentaId = self::obtenerCuentaIdPorVenta($conn, $ventaExistente);

            return [
                'venta_id' => $ventaExistente,
                'cuenta_id' => $cuentaId,
                'creado' => false,
            ];
        }

        $fecha = date('Y-m-d');
        $tasaId = self::obtenerUltimaTasaId($conn);
        $refSql = trim($referencia);
        $estadoVenta = 'por_pagar';

        $stmtV = $conn->prepare(
            'INSERT INTO ventas (cliente_id, fecha, numero_factura, total, tasa_cambiaria_id, cotizacion_id, estado, comprobante_referencia, forma_pago_id)
             VALUES (?, ?, NULL, ?, ?, ?, ?, NULLIF(TRIM(?), \'\'), ?)'
        );
        $stmtV->bind_param(
            'isdiissi',
            $idCliente,
            $fecha,
            $totalCot,
            $tasaId,
            $idCotizacion,
            $estadoVenta,
            $refSql,
            $formaPagoId
        );
        if (!$stmtV->execute()) {
            $err = $stmtV->error;
            $stmtV->close();
            throw new RuntimeException('No se pudo crear la venta: ' . $err);
        }
        $ventaId = (int) $conn->insert_id;
        $stmtV->close();

        if ($ventaId <= 0) {
            throw new RuntimeException('No se obtuvo el ID de la venta creada.');
        }

        self::insertarDetalleVentaDesdeCotizacion($conn, $ventaId, $idCotizacion);

        $cuentaId = null;
        if ($esFinanciada) {
            $cuentaId = self::crearCuentaConPagoInicial(
                $conn,
                $ventaId,
                $idCliente,
                $idCotizacion,
                $totalCot,
                $montoPago,
                $formaPagoId,
                $referencia
            );
        }

        return [
            'venta_id' => $ventaId,
            'cuenta_id' => $cuentaId,
            'creado' => true,
        ];
    }

    /**
     * @return array<string,mixed>|null
     */
    public static function obtenerCuentaParaCliente(mysqli $conn, int $cuentaId, int $idCliente): ?array
    {
        self::asegurarTablas($conn);

        $stmt = $conn->prepare(
            'SELECT cxc.id, cxc.venta_id, cxc.cliente_id, cxc.cotizacion_id,
                    cxc.monto_total, cxc.monto_pagado, cxc.saldo_pendiente, cxc.estado,
                    cot.status AS cotizacion_status, cot.codigo_cotizacion
             FROM cuentas_por_cobrar cxc
             LEFT JOIN cotizaciones cot ON cot.id_cotizacion = cxc.cotizacion_id
             WHERE cxc.id = ? AND cxc.cliente_id = ?
             LIMIT 1'
        );
        $stmt->bind_param('ii', $cuentaId, $idCliente);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ?: null;
    }

    public static function registrarPago(
        mysqli $conn,
        int $cuentaId,
        float $monto,
        int $formaPagoId,
        string $referencia,
        string $observaciones = '',
        string $origen = 'interno'
    ): void {
        self::asegurarTablas($conn);

        if ($monto <= 0) {
            throw new RuntimeException('El monto del pago debe ser mayor a cero.');
        }

        $stmt = $conn->prepare(
            'SELECT id, venta_id, monto_total, monto_pagado, saldo_pendiente, estado
             FROM cuentas_por_cobrar WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $cuentaId);
        $stmt->execute();
        $cuenta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cuenta) {
            throw new RuntimeException('La cuenta por cobrar no existe.');
        }
        if (($cuenta['estado'] ?? '') === 'cancelada') {
            throw new RuntimeException('La cuenta por cobrar está cancelada.');
        }
        if (($cuenta['estado'] ?? '') === 'pagada') {
            throw new RuntimeException('La cuenta por cobrar ya está pagada.');
        }

        $saldo = round((float) $cuenta['saldo_pendiente'], 2);
        $monto = round($monto, 2);
        if ($monto > $saldo + 0.009) {
            throw new RuntimeException(
                'El monto supera el saldo pendiente ($' . number_format($saldo, 2, '.', ',') . ').'
            );
        }

        $vf = $conn->prepare('SELECT id FROM formas_pago WHERE id = ? AND activo = 1 LIMIT 1');
        $vf->bind_param('i', $formaPagoId);
        $vf->execute();
        if (!$vf->get_result()->fetch_assoc()) {
            $vf->close();
            throw new RuntimeException('Forma de pago no válida.');
        }
        $vf->close();

        // Abonos registrados por el equipo (interno) aplican de inmediato al saldo.
        // Los del cliente quedan pendientes de verificación (aprobado = 0).
        $aprobado = $origen === 'interno' ? 1 : 0;
        $stmtP = $conn->prepare(
            'INSERT INTO cuentas_por_cobrar_pagos
                (cuenta_id, monto, forma_pago_id, referencia, observaciones, es_pago_inicial, origen, aprobado)
             VALUES (?, ?, ?, NULLIF(TRIM(?), \'\'), NULLIF(TRIM(?), \'\'), 0, ?, ?)'
        );
        $stmtP->bind_param('idisssi', $cuentaId, $monto, $formaPagoId, $referencia, $observaciones, $origen, $aprobado);
        if (!$stmtP->execute()) {
            $err = $stmtP->error;
            $stmtP->close();
            throw new RuntimeException('No se pudo registrar el pago: ' . $err);
        }
        $stmtP->close();

        self::recalcularTotalesCuenta($conn, $cuentaId);
        self::sincronizarEstadoVenta($conn, (int) $cuenta['venta_id']);
    }

    /**
     * Registra cuenta por cobrar al facturar desde administración si el monto pagado es menor al total.
     *
     * @return int|null ID de la cuenta creada, o null si el pago cubre el total
     */
    public static function crearCuentaPorCobrarDesdeVentaAdmin(
        mysqli $conn,
        int $ventaId,
        int $clienteId,
        ?int $cotizacionId,
        float $total,
        float $montoPagado,
        int $formaPagoId,
        string $referencia
    ): ?int {
        self::asegurarTablas($conn);

        $total = round($total, 2);
        $montoPagado = round($montoPagado, 2);

        if ($montoPagado < 0) {
            throw new RuntimeException('El monto pagado no puede ser negativo.');
        }
        if ($montoPagado > $total + 0.009) {
            throw new RuntimeException('El monto pagado no puede superar el total de la venta.');
        }
        if ($montoPagado >= $total - 0.009) {
            return null;
        }

        if (self::obtenerCuentaIdPorVenta($conn, $ventaId) !== null) {
            throw new RuntimeException('Esta venta ya tiene una cuenta por cobrar asociada.');
        }

        $cotParam = ($cotizacionId !== null && $cotizacionId > 0) ? $cotizacionId : 0;
        $pagadoInicial = 0.0;
        $saldoInicial = $total;
        $estado = 'pendiente';

        $stmt = $conn->prepare(
            'INSERT INTO cuentas_por_cobrar
                (venta_id, cliente_id, cotizacion_id, monto_total, monto_pagado, saldo_pendiente, estado)
             VALUES (?, ?, NULLIF(?, 0), ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iiiddds',
            $ventaId,
            $clienteId,
            $cotParam,
            $total,
            $pagadoInicial,
            $saldoInicial,
            $estado
        );
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('No se pudo crear la cuenta por cobrar: ' . $err);
        }
        $cuentaId = (int) $conn->insert_id;
        $stmt->close();

        if ($montoPagado > 0.009) {
            $aprobado = 1;
            $stmtP = $conn->prepare(
                'INSERT INTO cuentas_por_cobrar_pagos
                    (cuenta_id, monto, forma_pago_id, referencia, es_pago_inicial, origen, aprobado)
                 VALUES (?, ?, ?, NULLIF(TRIM(?), \'\'), 1, \'interno\', ?)'
            );
            $stmtP->bind_param('idisi', $cuentaId, $montoPagado, $formaPagoId, $referencia, $aprobado);
            if (!$stmtP->execute()) {
                $err = $stmtP->error;
                $stmtP->close();
                throw new RuntimeException('No se pudo registrar el pago inicial: ' . $err);
            }
            $stmtP->close();
        }

        self::recalcularTotalesCuenta($conn, $cuentaId);

        return $cuentaId;
    }

    public static function aprobarPagoVerificado(mysqli $conn, int $ventaId): array
    {
        self::asegurarTablas($conn);

        $stmt = $conn->prepare(
            'SELECT v.id, v.cotizacion_id, cot.status AS cotizacion_status
             FROM ventas v
             LEFT JOIN cotizaciones cot ON cot.id_cotizacion = v.cotizacion_id
             WHERE v.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $ventaId);
        $stmt->execute();
        $venta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$venta) {
            throw new RuntimeException('La venta no existe.');
        }

        $idCot = (int) ($venta['cotizacion_id'] ?? 0);
        if ($idCot <= 0) {
            throw new RuntimeException('Esta venta no proviene de una cotización con comprobante.');
        }

        if (!self::ventaRequiereAprobarPago($conn, $ventaId)) {
            throw new RuntimeException('No hay pagos pendientes de aprobación para esta venta.');
        }

        if ((int) ($venta['cotizacion_status'] ?? 0) === 1) {
            $statusAprobada = 2;
            $stCot = $conn->prepare('UPDATE cotizaciones SET status = ? WHERE id_cotizacion = ? AND status = 1');
            $stCot->bind_param('ii', $statusAprobada, $idCot);
            $stCot->execute();
            if ($stCot->affected_rows === 0) {
                $stCot->close();
                throw new RuntimeException('No se pudo aprobar la cotización. Puede que ya haya sido procesada.');
            }
            $stCot->close();
        }

        self::aprobarPagosPendientesDeVenta($conn, $ventaId);

        $cuentaId = self::obtenerCuentaIdPorVenta($conn, $ventaId);
        if ($cuentaId !== null) {
            self::aplicarEstadoVentaSegunCobros($conn, $ventaId, $cuentaId);
            $stmtEst = $conn->prepare('SELECT estado FROM ventas WHERE id = ? LIMIT 1');
            $stmtEst->bind_param('i', $ventaId);
            $stmtEst->execute();
            $nuevoEstado = (string) ($stmtEst->get_result()->fetch_assoc()['estado'] ?? 'por_pagar');
            $stmtEst->close();
        } else {
            $nuevoEstado = 'aprobado';
            $stV = $conn->prepare("UPDATE ventas SET estado = 'aprobado' WHERE id = ?");
            $stV->bind_param('i', $ventaId);
            $stV->execute();
            $stV->close();
        }

        return ['estado' => $nuevoEstado];
    }

    public static function ventaRequiereAprobarPago(mysqli $conn, int $ventaId): bool
    {
        $stmt = $conn->prepare(
            'SELECT cot.status AS cotizacion_status
             FROM ventas v
             LEFT JOIN cotizaciones cot ON cot.id_cotizacion = v.cotizacion_id
             WHERE v.id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $ventaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return false;
        }

        if ((int) ($row['cotizacion_status'] ?? 0) === 1) {
            return true;
        }

        return self::ventaTienePagosSinAprobar($conn, $ventaId);
    }

    public static function ventaTienePagosSinAprobar(mysqli $conn, int $ventaId): bool
    {
        self::asegurarTablas($conn);

        $stmt = $conn->prepare(
            'SELECT 1
             FROM cuentas_por_cobrar cxc
             INNER JOIN cuentas_por_cobrar_pagos p ON p.cuenta_id = cxc.id
             WHERE cxc.venta_id = ? AND p.aprobado = 0
             LIMIT 1'
        );
        $stmt->bind_param('i', $ventaId);
        $stmt->execute();
        $tiene = (bool) $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $tiene;
    }

    public static function sincronizarEstadoVenta(mysqli $conn, int $ventaId): void
    {
        if (self::ventaRequiereAprobarPago($conn, $ventaId)) {
            return;
        }

        $cuentaId = self::obtenerCuentaIdPorVenta($conn, $ventaId);
        if ($cuentaId === null) {
            return;
        }

        self::aplicarEstadoVentaSegunCobros($conn, $ventaId, $cuentaId);
    }

    private static function aplicarEstadoVentaSegunCobros(mysqli $conn, int $ventaId, int $cuentaId): void
    {

        $stmt = $conn->prepare(
            'SELECT saldo_pendiente FROM cuentas_por_cobrar WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $cuentaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            return;
        }

        $saldo = round((float) ($row['saldo_pendiente'] ?? 0), 2);
        $nuevoEstadoVenta = $saldo <= 0.009 ? 'aprobado' : 'por_pagar';
        $stV = $conn->prepare('UPDATE ventas SET estado = ? WHERE id = ?');
        $stV->bind_param('si', $nuevoEstadoVenta, $ventaId);
        $stV->execute();
        $stV->close();
    }

    private static function obtenerUltimaTasaId(mysqli $conn): ?int
    {
        $rt = $conn->query('SELECT id FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1');
        if ($rt && ($row = $rt->fetch_assoc())) {
            return (int) $row['id'];
        }

        return null;
    }

    private static function insertarDetalleVentaDesdeCotizacion(mysqli $conn, int $ventaId, int $idCotizacion): void
    {
        $stmt = $conn->prepare(
            'SELECT id_receta, cantidad, precio_unitario FROM cotizacion_detalles WHERE id_cotizacion = ?'
        );
        $stmt->bind_param('i', $idCotizacion);
        $stmt->execute();
        $res = $stmt->get_result();

        $stmtDet = $conn->prepare(
            'INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)'
        );

        $lineas = 0;
        while ($row = $res->fetch_assoc()) {
            $recetaId = (int) $row['id_receta'];
            $cantidad = (float) $row['cantidad'];
            $precio = (float) $row['precio_unitario'];
            if ($recetaId <= 0 || $cantidad <= 0) {
                continue;
            }
            $stmtDet->bind_param('iidd', $ventaId, $recetaId, $cantidad, $precio);
            $stmtDet->execute();
            $lineas++;
        }
        $stmt->close();
        $stmtDet->close();

        if ($lineas === 0) {
            throw new RuntimeException('La cotización no tiene detalle para generar la venta.');
        }
    }

    private static function crearCuentaConPagoInicial(
        mysqli $conn,
        int $ventaId,
        int $idCliente,
        int $idCotizacion,
        float $total,
        float $montoInicial,
        int $formaPagoId,
        string $referencia
    ): int {
        $total = round($total, 2);
        $montoInicial = round($montoInicial, 2);
        $pagadoInicial = 0.0;
        $saldoInicial = $total;
        $estado = 'pendiente';

        $stmt = $conn->prepare(
            'INSERT INTO cuentas_por_cobrar
                (venta_id, cliente_id, cotizacion_id, monto_total, monto_pagado, saldo_pendiente, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->bind_param(
            'iiiddds',
            $ventaId,
            $idCliente,
            $idCotizacion,
            $total,
            $pagadoInicial,
            $saldoInicial,
            $estado
        );
        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            throw new RuntimeException('No se pudo crear la cuenta por cobrar: ' . $err);
        }
        $cuentaId = (int) $conn->insert_id;
        $stmt->close();

        $aprobado = 0;
        $stmtP = $conn->prepare(
            'INSERT INTO cuentas_por_cobrar_pagos
                (cuenta_id, monto, forma_pago_id, referencia, es_pago_inicial, origen, aprobado)
             VALUES (?, ?, ?, NULLIF(TRIM(?), \'\'), 1, \'cliente\', ?)'
        );
        $stmtP->bind_param('idisi', $cuentaId, $montoInicial, $formaPagoId, $referencia, $aprobado);
        if (!$stmtP->execute()) {
            $err = $stmtP->error;
            $stmtP->close();
            throw new RuntimeException('No se pudo registrar el pago inicial: ' . $err);
        }
        $stmtP->close();

        self::recalcularTotalesCuenta($conn, $cuentaId);

        return $cuentaId;
    }

    private static function actualizarPagoInicialComprobante(
        mysqli $conn,
        int $ventaId,
        float $montoPago,
        int $formaPagoId,
        string $referencia,
        bool $esFinanciada,
        float $totalCot
    ): void {
        $cuentaId = self::obtenerCuentaIdPorVenta($conn, $ventaId);

        $stV = $conn->prepare(
            'UPDATE ventas SET total = ?, comprobante_referencia = NULLIF(TRIM(?), \'\'), forma_pago_id = ?
             WHERE id = ?'
        );
        $stV->bind_param('dsii', $totalCot, $referencia, $formaPagoId, $ventaId);
        $stV->execute();
        $stV->close();

        if (!$esFinanciada || $cuentaId === null) {
            return;
        }

        $stmt = $conn->prepare(
            'SELECT id FROM cuentas_por_cobrar_pagos WHERE cuenta_id = ? AND es_pago_inicial = 1 LIMIT 1'
        );
        $stmt->bind_param('i', $cuentaId);
        $stmt->execute();
        $pagoIni = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $montoPago = round($montoPago, 2);
        if ($pagoIni) {
            $pagoId = (int) $pagoIni['id'];
            $stU = $conn->prepare(
                'UPDATE cuentas_por_cobrar_pagos
                 SET monto = ?, forma_pago_id = ?, referencia = NULLIF(TRIM(?), \'\'),
                     fecha_pago = NOW(), aprobado = 0
                 WHERE id = ?'
            );
            $stU->bind_param('disi', $montoPago, $formaPagoId, $referencia, $pagoId);
            $stU->execute();
            $stU->close();
        } else {
            $aprobado = 0;
            $stmtP = $conn->prepare(
                'INSERT INTO cuentas_por_cobrar_pagos
                    (cuenta_id, monto, forma_pago_id, referencia, es_pago_inicial, origen, aprobado)
                 VALUES (?, ?, ?, NULLIF(TRIM(?), \'\'), 1, \'cliente\', ?)'
            );
            $stmtP->bind_param('idisi', $cuentaId, $montoPago, $formaPagoId, $referencia, $aprobado);
            $stmtP->execute();
            $stmtP->close();
        }

        self::recalcularTotalesCuenta($conn, $cuentaId);
    }

    private static function recalcularTotalesCuenta(mysqli $conn, int $cuentaId): void
    {
        $stmt = $conn->prepare(
            'SELECT monto_total FROM cuentas_por_cobrar WHERE id = ? LIMIT 1'
        );
        $stmt->bind_param('i', $cuentaId);
        $stmt->execute();
        $cuenta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cuenta) {
            return;
        }

        $stmtSum = $conn->prepare(
            'SELECT COALESCE(SUM(monto), 0) AS pagado
             FROM cuentas_por_cobrar_pagos WHERE cuenta_id = ? AND aprobado = 1'
        );
        $stmtSum->bind_param('i', $cuentaId);
        $stmtSum->execute();
        $pagado = round((float) ($stmtSum->get_result()->fetch_assoc()['pagado'] ?? 0), 2);
        $stmtSum->close();

        $total = round((float) $cuenta['monto_total'], 2);
        $saldo = round(max(0, $total - $pagado), 2);
        $estado = $saldo <= 0.009 ? 'pagada' : 'pendiente';

        $stU = $conn->prepare(
            'UPDATE cuentas_por_cobrar SET monto_pagado = ?, saldo_pendiente = ?, estado = ? WHERE id = ?'
        );
        $stU->bind_param('ddsi', $pagado, $saldo, $estado, $cuentaId);
        $stU->execute();
        $stU->close();
    }

    private static function aprobarPagosPendientesDeVenta(mysqli $conn, int $ventaId): void
    {
        $cuentaId = self::obtenerCuentaIdPorVenta($conn, $ventaId);
        if ($cuentaId === null) {
            return;
        }

        $st = $conn->prepare(
            'UPDATE cuentas_por_cobrar_pagos SET aprobado = 1 WHERE cuenta_id = ? AND aprobado = 0'
        );
        $st->bind_param('i', $cuentaId);
        $st->execute();
        $st->close();

        self::recalcularTotalesCuenta($conn, $cuentaId);
    }

    private static function obtenerCuentaIdPorVenta(mysqli $conn, int $ventaId): ?int
    {
        $stmt = $conn->prepare('SELECT id FROM cuentas_por_cobrar WHERE venta_id = ? LIMIT 1');
        $stmt->bind_param('i', $ventaId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $row ? (int) $row['id'] : null;
    }
}
