-- Migración: tabla almacenes y columna almacen_id en recetas
-- Ejecutar si la base de datos ya existe y no tiene almacenes.

-- Crear tabla almacenes
CREATE TABLE IF NOT EXISTS `almacenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Insertar almacén por defecto si la tabla está vacía
INSERT INTO almacenes (nombre, codigo, activo)
SELECT 'Principal', 'ALM01', 1
WHERE NOT EXISTS (SELECT 1 FROM almacenes LIMIT 1);

-- Agregar columna almacen_id a recetas (omitir si ya existe)
ALTER TABLE recetas ADD COLUMN almacen_id int(11) DEFAULT NULL COMMENT 'Almacén al que pertenece la receta/inventario' AFTER tipo_produccion_id;

-- Asignar almacén por defecto a recetas existentes
UPDATE recetas SET almacen_id = (SELECT id FROM almacenes LIMIT 1) WHERE almacen_id IS NULL;

-- Índice y FK
ALTER TABLE recetas ADD KEY almacen_id (almacen_id);
ALTER TABLE recetas ADD CONSTRAINT fk_recetas_almacen FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL ON UPDATE CASCADE;
