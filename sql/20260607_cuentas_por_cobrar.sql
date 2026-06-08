-- Cuentas por cobrar vinculadas a ventas (cotizaciones financiadas con pago inicial)

CREATE TABLE IF NOT EXISTS `cuentas_por_cobrar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `cotizacion_id` int(11) DEFAULT NULL,
  `monto_total` decimal(12,2) NOT NULL,
  `monto_pagado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo_pendiente` decimal(12,2) NOT NULL,
  `estado` enum('pendiente','pagada','cancelada') NOT NULL DEFAULT 'pendiente',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `actualizado_en` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_cxc_venta` (`venta_id`),
  KEY `idx_cxc_cliente` (`cliente_id`),
  KEY `idx_cxc_estado` (`estado`),
  KEY `cotizacion_id` (`cotizacion_id`),
  CONSTRAINT `fk_cxc_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cxc_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_cxc_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `cuentas_por_cobrar_pagos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cuenta_id` int(11) NOT NULL,
  `monto` decimal(12,2) NOT NULL,
  `forma_pago_id` int(11) DEFAULT NULL,
  `referencia` varchar(120) DEFAULT NULL,
  `observaciones` varchar(255) DEFAULT NULL,
  `es_pago_inicial` tinyint(1) NOT NULL DEFAULT 0,
  `origen` enum('cliente','interno') NOT NULL DEFAULT 'interno',
  `aprobado` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=verificado en ventas',
  `fecha_pago` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_cxc_pagos_cuenta` (`cuenta_id`),
  KEY `forma_pago_id` (`forma_pago_id`),
  CONSTRAINT `fk_cxc_pagos_cuenta` FOREIGN KEY (`cuenta_id`) REFERENCES `cuentas_por_cobrar` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_cxc_pagos_forma` FOREIGN KEY (`forma_pago_id`) REFERENCES `formas_pago` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
