-- Migración: rango de tallas en productos (fuente de verdad)
-- El rango ya no se define en la guía de corte sino al crear/editar el producto.

ALTER TABLE `productos`
  ADD COLUMN IF NOT EXISTS `rango_tallas_id` int(11) DEFAULT NULL AFTER `tipo_genero`;

-- MySQL < 8.0 no soporta IF NOT EXISTS en ADD COLUMN; la app ejecuta ensureSchemaProductoRango().

UPDATE `productos` p
INNER JOIN (
  SELECT `producto_id`, MIN(`rango_tallas_id`) AS `rango_tallas_id`
  FROM `recetas`
  GROUP BY `producto_id`
) r ON r.`producto_id` = p.`id`
SET p.`rango_tallas_id` = r.`rango_tallas_id`
WHERE p.`rango_tallas_id` IS NULL;
