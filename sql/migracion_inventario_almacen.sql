-- Migración: almacen_id en inventario (materia prima) e inventario_detalle
-- Ejecutar si la base de datos ya existe sin almacen en inventario.

-- Asegurar que existan almacenes
CREATE TABLE IF NOT EXISTS almacenes (
  id int(11) NOT NULL AUTO_INCREMENT,
  nombre varchar(100) NOT NULL,
  codigo varchar(20) DEFAULT NULL,
  activo tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO almacenes (nombre, codigo, activo) SELECT 'Principal', 'ALM01', 1 WHERE NOT EXISTS (SELECT 1 FROM almacenes LIMIT 1);

-- inventario: agregar almacen_id y cambiar PK (ejecutar paso a paso si hay error)
ALTER TABLE inventario ADD COLUMN almacen_id int(11) NULL AFTER insumo_id;
UPDATE inventario SET almacen_id = (SELECT id FROM almacenes LIMIT 1) WHERE almacen_id IS NULL;
ALTER TABLE inventario MODIFY almacen_id int(11) NOT NULL DEFAULT 1;
ALTER TABLE inventario DROP PRIMARY KEY;
ALTER TABLE inventario ADD PRIMARY KEY (insumo_id, almacen_id);
ALTER TABLE inventario ADD KEY idx_almacen_id (almacen_id);
ALTER TABLE inventario ADD CONSTRAINT fk_inventario_almacen FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE CASCADE ON UPDATE CASCADE;

-- inventario_detalle: agregar almacen_id (solo para movimientos de insumo)
ALTER TABLE inventario_detalle ADD COLUMN almacen_id int(11) DEFAULT NULL AFTER insumo_id;
ALTER TABLE inventario_detalle ADD KEY idx_almacen_id (almacen_id);
ALTER TABLE inventario_detalle ADD CONSTRAINT fk_inventario_detalle_almacen FOREIGN KEY (almacen_id) REFERENCES almacenes(id) ON DELETE SET NULL ON UPDATE CASCADE;
