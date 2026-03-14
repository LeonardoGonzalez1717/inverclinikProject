-- Migración: columna almacen_id en insumos (relacionar cada insumo con un almacén)
-- Ejecutar una sola vez. Si la columna ya existe, omitir.

-- Asegurar que la tabla almacenes existe
CREATE TABLE IF NOT EXISTS `almacenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO almacenes (nombre, codigo, activo)
SELECT 'Principal', 'ALM01', 1
WHERE NOT EXISTS (SELECT 1 FROM almacenes LIMIT 1);

-- Almacén asociado a cada insumo (inventario de materia prima se registra en este almacén)
ALTER TABLE insumos ADD COLUMN almacen_id int(11) DEFAULT 1 COMMENT 'Almacén asociado al insumo' AFTER stock_maximo;

UPDATE insumos SET almacen_id = (SELECT id FROM almacenes LIMIT 1) WHERE almacen_id IS NULL;
