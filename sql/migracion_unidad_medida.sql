-- Migración: catálogo unidad_medida + insumos.unidad_medida_id (FK)
-- Las bases antiguas se migran automáticamente al cargar la app (connection/ensure_unidad_medida.php).
-- Este archivo documenta el catálogo inicial y la FK esperada.

START TRANSACTION;

CREATE TABLE IF NOT EXISTS `unidad_medida` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `codigo` varchar(32) NOT NULL COMMENT 'Identificador estable (ej. metro, unidad)',
  `nombre` varchar(100) NOT NULL COMMENT 'Etiqueta para mostrar',
  `permite_movimiento_decimal` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=cantidades con decimales en movimientos; 0=solo enteros',
  PRIMARY KEY (`id`),
  UNIQUE KEY `codigo` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Si la tabla ya existía sin esta columna:
-- ALTER TABLE `unidad_medida` ADD COLUMN `permite_movimiento_decimal` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=decimales; 0=solo enteros' AFTER `nombre`;

INSERT IGNORE INTO `unidad_medida` (`codigo`, `nombre`) VALUES
('metro', 'Metro'),
('unidad', 'Unidad'),
('kilogramo', 'Kilogramo'),
('litro', 'Litro'),
('metro_cuadrado', 'Metro cuadrado'),
('carrete', 'Carrete'),
('rollo', 'Rollo'),
('pieza', 'Pieza'),
('cono', 'Cono'),
('paquete', 'Paquete');

COMMIT;
