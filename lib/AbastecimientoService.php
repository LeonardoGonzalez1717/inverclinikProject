<?php

/**
 * Modelo / Servicio y Esquema para el Ciclo de Abastecimiento de Compras:
 * Requisición -> Aprobación -> Orden de Compra -> Registro de Compra.
 */
class AbastecimientoService
{
    // Estados principales de Requisición (columna `estado`)
    public const REQ_PENDIENTE = 1;
    public const REQ_APROBADA  = 2;
    public const REQ_RECHAZADA = 3;
    public const REQ_PROCESADA = 4;
    public const REQ_PARCIAL   = 5;

    // Sub-estados de procesamiento en O.C. (columna `estado_orden`)
    public const OC_PROC_NINGUNO   = 0;
    public const OC_PROC_PROCESADA = 1;
    public const OC_PROC_PARCIAL   = 2;

    /**
     * Asegura de forma idempotente la existencia de las tablas de abastecimiento
     * y la columna foránea `id_orden_compra` en la tabla `compras`.
     */
    public static function asegurarTablas(mysqli $conn): void
    {
        static $ejecutado = false;
        if ($ejecutado) {
            return;
        }
        $ejecutado = true;

        try {
            // 1. Tabla `requisiciones`
            $conn->query("
                CREATE TABLE IF NOT EXISTS `requisiciones` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `codigo_requisicion` VARCHAR(30) NOT NULL,
                    `usuario_id` INT(11) DEFAULT NULL COMMENT 'Usuario solicitante (users.id)',
                    `fecha_solicitud` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `justificacion` TEXT DEFAULT NULL COMMENT 'Motivo o justificación de la solicitud',
                    `observaciones` TEXT DEFAULT NULL,
                    `estado` TINYINT(4) NOT NULL DEFAULT 1 COMMENT '1=PENDIENTE, 2=APROBADA, 3=RECHAZADA, 4=PROCESADA, 5=PARCIAL',
                    `estado_orden` TINYINT(4) NOT NULL DEFAULT 0 COMMENT '0=PENDIENTE_OC, 1=PROCESADA, 2=PARCIAL',
                    `usuario_aprobacion_id` INT(11) DEFAULT NULL COMMENT 'Usuario que aprobó o rechazó (users.id)',
                    `fecha_aprobacion` DATETIME DEFAULT NULL,
                    `motivo_rechazo` TEXT DEFAULT NULL,
                    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_codigo_requisicion` (`codigo_requisicion`),
                    KEY `idx_req_usuario` (`usuario_id`),
                    KEY `idx_req_estado` (`estado`),
                    KEY `idx_req_fecha` (`fecha_solicitud`),
                    KEY `idx_req_aprobador` (`usuario_aprobacion_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");

            // 2. Tabla `requisicion_detalles`
            $conn->query("
                CREATE TABLE IF NOT EXISTS `requisicion_detalles` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `requisicion_id` INT(11) NOT NULL,
                    `insumo_id` INT(11) NOT NULL,
                    `cantidad` DECIMAL(12,2) NOT NULL COMMENT 'Cantidad solicitada en la requisición',
                    `cantidad_pedida` DECIMAL(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Cantidad ya incluida en Órdenes de Compra activas',
                    `observaciones` VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_req_det_requisicion` (`requisicion_id`),
                    KEY `idx_req_det_insumo` (`insumo_id`),
                    CONSTRAINT `fk_req_detalles_requisicion` FOREIGN KEY (`requisicion_id`)
                        REFERENCES `requisiciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_req_detalles_insumo` FOREIGN KEY (`insumo_id`)
                        REFERENCES `insumos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");

            // 3. Tabla `ordenes_compra`
            $conn->query("
                CREATE TABLE IF NOT EXISTS `ordenes_compra` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `codigo_oc` VARCHAR(30) NOT NULL,
                    `proveedor_id` INT(11) NOT NULL,
                    `usuario_id` INT(11) DEFAULT NULL,
                    `fecha` DATE NOT NULL,
                    `modalidad` ENUM('requisicion','directa') NOT NULL DEFAULT 'directa',
                    `requisiciones_ids` VARCHAR(255) DEFAULT NULL,
                    `total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `tasa_cambiaria_id` INT(11) DEFAULT NULL,
                    `estado` ENUM('EMITIDA','COMPLETADA','CANCELADA') NOT NULL DEFAULT 'EMITIDA',
                    `observaciones` TEXT DEFAULT NULL,
                    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_codigo_oc` (`codigo_oc`),
                    KEY `idx_oc_proveedor` (`proveedor_id`),
                    KEY `idx_oc_estado` (`estado`),
                    KEY `idx_oc_fecha` (`fecha`),
                    KEY `idx_oc_usuario` (`usuario_id`),
                    KEY `idx_oc_tasa` (`tasa_cambiaria_id`),
                    CONSTRAINT `fk_oc_proveedor` FOREIGN KEY (`proveedor_id`)
                        REFERENCES `proveedores` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");

            // 4. Tabla `orden_compra_detalles`
            $conn->query("
                CREATE TABLE IF NOT EXISTS `orden_compra_detalles` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `orden_compra_id` INT(11) NOT NULL,
                    `insumo_id` INT(11) NOT NULL,
                    `requisicion_id` INT(11) DEFAULT NULL,
                    `requisicion_detalle_id` INT(11) DEFAULT NULL,
                    `cantidad` DECIMAL(12,2) NOT NULL,
                    `costo_unitario` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                    `subtotal` DECIMAL(12,2) GENERATED ALWAYS AS (`cantidad` * `costo_unitario`) STORED,
                    PRIMARY KEY (`id`),
                    KEY `idx_ocd_orden` (`orden_compra_id`),
                    KEY `idx_ocd_insumo` (`insumo_id`),
                    KEY `idx_ocd_requisicion` (`requisicion_id`),
                    KEY `idx_ocd_req_detalle` (`requisicion_detalle_id`),
                    CONSTRAINT `fk_ocd_orden_compra` FOREIGN KEY (`orden_compra_id`)
                        REFERENCES `ordenes_compra` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                    CONSTRAINT `fk_ocd_insumo` FOREIGN KEY (`insumo_id`)
                        REFERENCES `insumos` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");

            // 5. Columna `id_orden_compra` en `compras`
            $chkCol = $conn->query("SHOW COLUMNS FROM `compras` LIKE 'id_orden_compra'");
            if ($chkCol && $chkCol->num_rows === 0) {
                @$conn->query("
                    ALTER TABLE `compras`
                    ADD COLUMN `id_orden_compra` INT(11) DEFAULT NULL COMMENT 'FK opcional a ordenes_compra.id' AFTER `proveedor_id`,
                    ADD KEY `idx_compras_orden_compra` (`id_orden_compra`),
                    ADD CONSTRAINT `fk_compras_orden_compra`
                        FOREIGN KEY (`id_orden_compra`) REFERENCES `ordenes_compra` (`id`)
                        ON DELETE SET NULL ON UPDATE CASCADE
                ");
            }
        } catch (Exception $e) {
            // Ignorar avisos si ya existen índices/restricciones
        }
    }

    /**
     * Genera el siguiente código correlativo para Requisición (ej. REQ-0001).
     */
    public static function generarCodigoRequisicion(mysqli $conn): string
    {
        $res = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS prox FROM requisiciones");
        $prox = 1;
        if ($res && ($row = $res->fetch_assoc())) {
            $prox = (int) $row['prox'];
        }
        while (true) {
            $codigo = 'REQ-' . str_pad((string) $prox, 4, '0', STR_PAD_LEFT);
            $chk = $conn->prepare("SELECT 1 FROM requisiciones WHERE codigo_requisicion = ? LIMIT 1");
            $chk->bind_param('s', $codigo);
            $chk->execute();
            $existe = $chk->get_result()->num_rows > 0;
            $chk->close();
            if (!$existe) {
                return $codigo;
            }
            $prox++;
        }
    }

    /**
     * Genera el siguiente código correlativo para Orden de Compra (ej. OC-0001).
     */
    public static function generarCodigoOrdenCompra(mysqli $conn): string
    {
        $res = $conn->query("SELECT COALESCE(MAX(id), 0) + 1 AS prox FROM ordenes_compra");
        $prox = 1;
        if ($res && ($row = $res->fetch_assoc())) {
            $prox = (int) $row['prox'];
        }
        while (true) {
            $codigo = 'OC-' . str_pad((string) $prox, 4, '0', STR_PAD_LEFT);
            $chk = $conn->prepare("SELECT 1 FROM ordenes_compra WHERE codigo_oc = ? LIMIT 1");
            $chk->bind_param('s', $codigo);
            $chk->execute();
            $existe = $chk->get_result()->num_rows > 0;
            $chk->close();
            if (!$existe) {
                return $codigo;
            }
            $prox++;
        }
    }

    /**
     * Devuelve [texto, estilo_inline, clase_badge] para el estado de una requisición.
     */
    public static function etiquetaEstadoRequisicion(int $estado, int $estadoOrden = 0): array
    {
        $baseStyle = 'font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block; font-size: 12.5px;';

        if ($estado === self::REQ_PROCESADA || $estadoOrden === self::OC_PROC_PROCESADA) {
            return ['PROCESADA', "background-color: #0d6efd; color: #ffffff; {$baseStyle}", 'badge-primary'];
        }
        if ($estado === self::REQ_PARCIAL || $estadoOrden === self::OC_PROC_PARCIAL) {
            return ['PARCIAL', "background-color: #6f42c1; color: #ffffff; {$baseStyle}", 'badge-info'];
        }

        return match ($estado) {
            self::REQ_PENDIENTE => ['PENDIENTE', "background-color: #fd7e14; color: #ffffff; {$baseStyle}", 'badge-warning'],
            self::REQ_APROBADA  => ['APROBADA', "background-color: #198754; color: #ffffff; {$baseStyle}", 'badge-success'],
            self::REQ_RECHAZADA => ['RECHAZADA', "background-color: #dc3545; color: #ffffff; {$baseStyle}", 'badge-danger'],
            default             => ['DESCONOCIDO', "background-color: #6c757d; color: #ffffff; {$baseStyle}", 'badge-secondary'],
        };
    }

    /**
     * Devuelve [texto, estilo_inline, clase_badge] para el estado de una Orden de Compra.
     */
    public static function etiquetaEstadoOrdenCompra(string $estado): array
    {
        $est = strtoupper(trim($estado));
        $baseStyle = 'font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block; font-size: 12.5px;';

        return match ($est) {
            'EMITIDA'    => ['EMITIDA', "background-color: #fd7e14; color: #ffffff; {$baseStyle}", 'badge-warning'],
            'COMPLETADA' => ['COMPLETADA', "background-color: #198754; color: #ffffff; {$baseStyle}", 'badge-success'],
            'CANCELADA'  => ['CANCELADA', "background-color: #dc3545; color: #ffffff; {$baseStyle}", 'badge-danger'],
            default      => [$est ?: '—', "background-color: #6c757d; color: #ffffff; {$baseStyle}", 'badge-secondary'],
        };
    }

    /**
     * Recalcula las cantidades pedidas de cada renglón de una requisición en base a las
     * Órdenes de Compra activas (EMITIDA o COMPLETADA) y actualiza el estado de la requisición:
     * - Si todos los renglones tienen cantidad_pedida >= cantidad: pasa a PROCESADA (estado=4, estado_orden=1)
     * - Si se generó O.C. pero aún quedan productos/cantidades por requisar: pasa a PARCIAL (estado=5, estado_orden=2)
     * - Si no tiene O.C. activas y estaba en 4 o 5: vuelve a APROBADA (estado=2, estado_orden=0)
     */
    public static function recalcularEstadoRequisicion(mysqli $conn, int $requisicionId): void
    {
        if ($requisicionId <= 0) {
            return;
        }

        $stmtReq = $conn->prepare("SELECT id, estado FROM requisiciones WHERE id = ? LIMIT 1");
        $stmtReq->bind_param('i', $requisicionId);
        $stmtReq->execute();
        $reqRow = $stmtReq->get_result()->fetch_assoc();
        $stmtReq->close();

        if (!$reqRow) {
            return;
        }

        $estadoActual = (int) $reqRow['estado'];
        // Si está pendiente o rechazada no debe cambiar a procesada/parcial
        if (in_array($estadoActual, [self::REQ_PENDIENTE, self::REQ_RECHAZADA], true)) {
            return;
        }

        // Obtener detalles de la requisición
        $stmtDet = $conn->prepare("SELECT id, insumo_id, cantidad FROM requisicion_detalles WHERE requisicion_id = ?");
        $stmtDet->bind_param('i', $requisicionId);
        $stmtDet->execute();
        $resDet = $stmtDet->get_result();
        $detalles = [];
        while ($d = $resDet->fetch_assoc()) {
            $detalles[] = $d;
        }
        $stmtDet->close();

        if (empty($detalles)) {
            return;
        }

        $totalLineas = count($detalles);
        $lineasCompletas = 0;
        $sumaPedidaTotal = 0.0;

        $stmtSumDet = $conn->prepare("
            SELECT COALESCE(SUM(ocd.cantidad), 0) AS total_pedido
            FROM orden_compra_detalles ocd
            INNER JOIN ordenes_compra oc ON oc.id = ocd.orden_compra_id
            WHERE oc.estado IN ('EMITIDA', 'COMPLETADA')
              AND (
                  ocd.requisicion_detalle_id = ?
                  OR (ocd.requisicion_detalle_id IS NULL AND ocd.requisicion_id = ? AND ocd.insumo_id = ?)
              )
        ");

        $stmtUpdDet = $conn->prepare("UPDATE requisicion_detalles SET cantidad_pedida = ? WHERE id = ?");

        foreach ($detalles as $det) {
            $detId = (int) $det['id'];
            $insId = (int) $det['insumo_id'];
            $cantSolicitada = (float) $det['cantidad'];

            $stmtSumDet->bind_param('iii', $detId, $requisicionId, $insId);
            $stmtSumDet->execute();
            $rowSum = $stmtSumDet->get_result()->fetch_assoc();
            $cantPedida = (float) ($rowSum['total_pedido'] ?? 0);

            $stmtUpdDet->bind_param('di', $cantPedida, $detId);
            $stmtUpdDet->execute();

            $sumaPedidaTotal += $cantPedida;
            if ($cantPedida + 0.0001 >= $cantSolicitada) {
                $lineasCompletas++;
            }
        }

        $stmtSumDet->close();
        $stmtUpdDet->close();

        // Verificar también si existe alguna O.C. activa que referencie a esta requisición
        $stmtOcVinc = $conn->prepare("
            SELECT COUNT(*) AS n
            FROM ordenes_compra oc
            WHERE oc.estado IN ('EMITIDA', 'COMPLETADA')
              AND FIND_IN_SET(?, REPLACE(IFNULL(oc.requisiciones_ids, ''), ' ', '')) > 0
        ");
        $reqIdStr = (string) $requisicionId;
        $stmtOcVinc->bind_param('s', $reqIdStr);
        $stmtOcVinc->execute();
        $nOcVinculadas = (int) ($stmtOcVinc->get_result()->fetch_assoc()['n'] ?? 0);
        $stmtOcVinc->close();

        if ($lineasCompletas === $totalLineas && $totalLineas > 0) {
            $nuevoEstado = self::REQ_PROCESADA;
            $nuevoEstadoOrden = self::OC_PROC_PROCESADA; // 1 = PROCESADA
        } elseif ($sumaPedidaTotal > 0.0001 || $nOcVinculadas > 0) {
            $nuevoEstado = self::REQ_PARCIAL;
            $nuevoEstadoOrden = self::OC_PROC_PARCIAL;   // 2 = PARCIAL
        } else {
            $nuevoEstado = self::REQ_APROBADA;
            $nuevoEstadoOrden = self::OC_PROC_NINGUNO;   // 0 = SIN PROCESAR
        }

        $stmtUpdReq = $conn->prepare("UPDATE requisiciones SET estado = ?, estado_orden = ? WHERE id = ?");
        $stmtUpdReq->bind_param('iii', $nuevoEstado, $nuevoEstadoOrden, $requisicionId);
        $stmtUpdReq->execute();
        $stmtUpdReq->close();
    }
}
