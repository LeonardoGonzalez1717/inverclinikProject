<?php

/**
 * Esquema y migración automática para tablas de devoluciones
 */
class DevolucionesSchema
{
    public static function asegurarTablas(mysqli $conn): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;

        try {
            // 1. Tabla de devoluciones (cabecera)
            $conn->query("
                CREATE TABLE IF NOT EXISTS `devoluciones` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `codigo_devolucion` VARCHAR(50) NOT NULL,
                    `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `cliente_id` INT(11) DEFAULT NULL,
                    `motivo` TEXT NOT NULL,
                    `descripcion_motivo` TEXT DEFAULT NULL,
                    `accion_inventario` VARCHAR(50) NOT NULL DEFAULT 'pendiente_inspeccion',
                    `estado` VARCHAR(30) NOT NULL DEFAULT 'pendiente',
                    `fecha_inspeccion` DATETIME DEFAULT NULL,
                    `usuario_inspeccion_id` INT(11) DEFAULT NULL,
                    `motivo_inspeccion` TEXT DEFAULT NULL,
                    `usuario_id` INT(11) DEFAULT NULL,
                    `observaciones` TEXT DEFAULT NULL,
                    `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_codigo_devolucion` (`codigo_devolucion`),
                    KEY `idx_cliente_id` (`cliente_id`),
                    KEY `idx_fecha` (`fecha`),
                    KEY `idx_estado` (`estado`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");

            // 2. Tabla detalle de devoluciones
            $conn->query("
                CREATE TABLE IF NOT EXISTS `devoluciones_detalle` (
                    `id` INT(11) NOT NULL AUTO_INCREMENT,
                    `devolucion_id` INT(11) NOT NULL,
                    `orden_produccion_id` INT(11) NOT NULL,
                    `unidad_id` INT(11) DEFAULT NULL,
                    `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00,
                    `estado_unidad_posterior` VARCHAR(50) NOT NULL DEFAULT 'pendiente_inspeccion',
                    `lote_id` INT(11) DEFAULT NULL,
                    `observaciones` TEXT DEFAULT NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx_devolucion_id` (`devolucion_id`),
                    KEY `idx_op_id` (`orden_produccion_id`),
                    CONSTRAINT `fk_dev_detalle_devolucion` FOREIGN KEY (`devolucion_id`) 
                        REFERENCES `devoluciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
            ");

            // Asegurar columna `estado` en devoluciones
            $chkEstado = $conn->query("SHOW COLUMNS FROM `devoluciones` LIKE 'estado'");
            if ($chkEstado && $chkEstado->num_rows === 0) {
                @$conn->query("ALTER TABLE `devoluciones` ADD COLUMN `estado` VARCHAR(30) NOT NULL DEFAULT 'pendiente' AFTER `accion_inventario`");
                // Los registros previos con 'reingresar_stock' marcarlos como aprobado
                @$conn->query("UPDATE `devoluciones` SET `estado` = 'aprobado' WHERE `accion_inventario` = 'reingresar_stock'");
            }

            // Asegurar columna `fecha_inspeccion` en devoluciones
            $chkFecInsp = $conn->query("SHOW COLUMNS FROM `devoluciones` LIKE 'fecha_inspeccion'");
            if ($chkFecInsp && $chkFecInsp->num_rows === 0) {
                @$conn->query("ALTER TABLE `devoluciones` ADD COLUMN `fecha_inspeccion` DATETIME DEFAULT NULL AFTER `estado`");
            }

            // Asegurar columna `usuario_inspeccion_id` en devoluciones
            $chkUserInsp = $conn->query("SHOW COLUMNS FROM `devoluciones` LIKE 'usuario_inspeccion_id'");
            if ($chkUserInsp && $chkUserInsp->num_rows === 0) {
                @$conn->query("ALTER TABLE `devoluciones` ADD COLUMN `usuario_inspeccion_id` INT(11) DEFAULT NULL AFTER `fecha_inspeccion`");
            }

            // Asegurar columna `motivo_inspeccion` en devoluciones
            $chkMotInsp = $conn->query("SHOW COLUMNS FROM `devoluciones` LIKE 'motivo_inspeccion'");
            if ($chkMotInsp && $chkMotInsp->num_rows === 0) {
                @$conn->query("ALTER TABLE `devoluciones` ADD COLUMN `motivo_inspeccion` TEXT DEFAULT NULL AFTER `usuario_inspeccion_id`");
            }

            // Asegurar columna `cantidad` si la tabla ya existía sin ella
            $chkCol = $conn->query("SHOW COLUMNS FROM `devoluciones_detalle` LIKE 'cantidad'");
            if ($chkCol && $chkCol->num_rows === 0) {
                @$conn->query("ALTER TABLE `devoluciones_detalle` ADD COLUMN `cantidad` DECIMAL(10,2) NOT NULL DEFAULT 1.00 AFTER `orden_produccion_id`");
            }

            // Asegurar columna `lote_id` en devoluciones_detalle
            $chkLote = $conn->query("SHOW COLUMNS FROM `devoluciones_detalle` LIKE 'lote_id'");
            if ($chkLote && $chkLote->num_rows === 0) {
                @$conn->query("ALTER TABLE `devoluciones_detalle` ADD COLUMN `lote_id` INT(11) DEFAULT NULL AFTER `estado_unidad_posterior`");
            }

            // Asegurar que unidad_id sea NULL y sin FK rígida
            @$conn->query("ALTER TABLE `devoluciones_detalle` DROP FOREIGN KEY `fk_dev_detalle_unidad`");
            @$conn->query("ALTER TABLE `devoluciones_detalle` MODIFY COLUMN `unidad_id` INT(11) NULL");
        } catch (Exception $e) {
            // Ignorar errores menores de migración
        }
    }
}
