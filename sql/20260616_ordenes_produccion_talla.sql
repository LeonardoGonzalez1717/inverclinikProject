-- Talla concreta a producir en cada orden (depende del rango del producto).
ALTER TABLE `ordenes_produccion`
  ADD COLUMN `talla_id` int(11) DEFAULT NULL COMMENT 'Talla individual a producir (tabla tallas)' AFTER `receta_producto_id`,
  ADD KEY `idx_ordenes_produccion_talla_id` (`talla_id`),
  ADD CONSTRAINT `fk_ordenes_produccion_talla` FOREIGN KEY (`talla_id`) REFERENCES `tallas` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
