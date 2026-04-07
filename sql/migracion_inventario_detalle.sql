-- Migración: unificar movimientos_inventario_detalle y movimientos_productos_detalle en inventario_detalle
-- Ejecutar solo si ya existen las tablas antiguas y se desea migrar los datos.

-- Crear la nueva tabla unificada si no existe
CREATE TABLE IF NOT EXISTS `inventario_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_item` enum('insumo','producto') NOT NULL COMMENT 'insumo=materia prima, producto=producto terminado',
  `insumo_id` int(11) DEFAULT NULL,
  `producto_id` int(11) DEFAULT NULL,
  `rango_tallas_id` int(11) DEFAULT NULL,
  `tipo_produccion_id` int(11) DEFAULT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `origen` varchar(50) DEFAULT 'manual',
  `observaciones` text DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `orden_produccion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tipo_item` (`tipo_item`),
  KEY `idx_insumo_id` (`insumo_id`),
  KEY `idx_producto` (`producto_id`,`rango_tallas_id`,`tipo_produccion_id`),
  KEY `idx_fecha` (`fecha_movimiento`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_orden_produccion` (`orden_produccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Migrar datos de movimientos_inventario_detalle (insumos)
INSERT INTO inventario_detalle (tipo_item, insumo_id, tipo, cantidad, origen, observaciones, fecha_movimiento, orden_produccion_id)
SELECT 'insumo', insumo_id, tipo, cantidad, origen, observaciones, fecha_movimiento, orden_produccion_id
FROM movimientos_inventario_detalle;

-- Migrar datos de movimientos_productos_detalle (productos)
INSERT INTO inventario_detalle (tipo_item, producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, observaciones, fecha_movimiento)
SELECT 'producto', producto_id, rango_tallas_id, tipo_produccion_id, tipo, cantidad, observaciones, fecha_movimiento
FROM movimientos_productos_detalle;

-- Eliminar tablas antiguas (descomentar tras verificar que la migración fue correcta)
-- ALTER TABLE movimientos_inventario_detalle DROP FOREIGN KEY fk_movimientos_orden_produccion;
-- DROP TABLE movimientos_inventario_detalle;
-- DROP TABLE movimientos_productos_detalle;

-- Añadir FKs a inventario_detalle si no existen (opcional, según tu esquema)
-- ALTER TABLE inventario_detalle ADD CONSTRAINT fk_inventario_detalle_orden_produccion FOREIGN KEY (orden_produccion_id) REFERENCES ordenes_produccion(id) ON DELETE SET NULL;
-- ALTER TABLE inventario_detalle ADD CONSTRAINT fk_inventario_detalle_insumo FOREIGN KEY (insumo_id) REFERENCES insumos(id) ON DELETE CASCADE ON UPDATE CASCADE;
-- ALTER TABLE inventario_detalle ADD CONSTRAINT fk_inventario_detalle_producto FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE ON UPDATE CASCADE;
-- ALTER TABLE inventario_detalle ADD CONSTRAINT fk_inventario_detalle_rango_tallas FOREIGN KEY (rango_tallas_id) REFERENCES rangos_tallas(id) ON DELETE CASCADE ON UPDATE CASCADE;
-- ALTER TABLE inventario_detalle ADD CONSTRAINT fk_inventario_detalle_tipo_produccion FOREIGN KEY (tipo_produccion_id) REFERENCES tipos_produccion(id) ON DELETE CASCADE ON UPDATE CASCADE;
