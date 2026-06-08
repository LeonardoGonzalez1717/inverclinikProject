-- Migración: tallas individuales por rango de tallas
-- Ejecutar en bases de datos existentes que usan tallas_desde / tallas_hasta.

CREATE TABLE IF NOT EXISTS `tallas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rango_tallas_id` int(11) NOT NULL,
  `nombre` varchar(20) NOT NULL,
  `orden` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_rango_talla` (`rango_tallas_id`,`nombre`),
  KEY `rango_tallas_id` (`rango_tallas_id`),
  CONSTRAINT `fk_tallas_rango` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrar rangos existentes (solo si aún tienen columnas tallas_desde / tallas_hasta)
-- Nota: ejecutar el bloque PHP ensureSchemaTallas() en registrar_rangos_tallas_data.php
-- o correr manualmente los INSERT generados desde la app al abrir el módulo.
