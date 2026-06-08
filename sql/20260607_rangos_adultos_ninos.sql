-- Consolidar rangos de tallas: solo Niños y Adultos.
-- Las tallas S, M, L, XL, etc. quedan dentro del rango Adultos.

INSERT IGNORE INTO `rangos_tallas` (`nombre_rango`, `descripcion`) VALUES
('Niños', 'Tallas infantiles'),
('Adultos', 'Tallas para adultos');

INSERT IGNORE INTO `tallas` (`rango_tallas_id`, `nombre`, `orden`)
SELECT rt.id, v.nombre, v.orden
FROM `rangos_tallas` rt
JOIN (
  SELECT 'Niños' AS rango, '2' AS nombre, 0 AS orden UNION ALL
  SELECT 'Niños', '4', 1 UNION ALL
  SELECT 'Niños', '6', 2 UNION ALL
  SELECT 'Niños', '8', 3 UNION ALL
  SELECT 'Niños', '10', 4 UNION ALL
  SELECT 'Niños', '12', 5 UNION ALL
  SELECT 'Niños', '14', 6
) v ON v.rango = rt.`nombre_rango`;

INSERT IGNORE INTO `tallas` (`rango_tallas_id`, `nombre`, `orden`)
SELECT rt.id, v.nombre, v.orden
FROM `rangos_tallas` rt
JOIN (
  SELECT 'Adultos' AS rango, 'XS' AS nombre, 0 AS orden UNION ALL
  SELECT 'Adultos', 'S', 1 UNION ALL
  SELECT 'Adultos', 'M', 2 UNION ALL
  SELECT 'Adultos', 'L', 3 UNION ALL
  SELECT 'Adultos', 'XL', 4 UNION ALL
  SELECT 'Adultos', 'XXL', 5 UNION ALL
  SELECT 'Adultos', 'Única', 6
) v ON v.rango = rt.`nombre_rango`;

-- Reasignar productos y recetas de rangos antiguos a Adultos
UPDATE `productos` p
INNER JOIN `rangos_tallas` rt_old ON rt_old.id = p.`rango_tallas_id`
INNER JOIN `rangos_tallas` rt_new ON rt_new.`nombre_rango` = 'Adultos'
SET p.`rango_tallas_id` = rt_new.id
WHERE rt_old.`nombre_rango` IN ('Talla Única', 'XS', 'S', 'M', 'L', 'XL', 'XXL');

UPDATE `recetas` r
INNER JOIN `rangos_tallas` rt_old ON rt_old.id = r.`rango_tallas_id`
INNER JOIN `rangos_tallas` rt_new ON rt_new.`nombre_rango` = 'Adultos'
SET r.`rango_tallas_id` = rt_new.id
WHERE rt_old.`nombre_rango` IN ('Talla Única', 'XS', 'S', 'M', 'L', 'XL', 'XXL');

UPDATE `recetas_productos` rp
INNER JOIN `rangos_tallas` rt_old ON rt_old.id = rp.`rango_tallas_id`
INNER JOIN `rangos_tallas` rt_new ON rt_new.`nombre_rango` = 'Adultos'
SET rp.`rango_tallas_id` = rt_new.id
WHERE rt_old.`nombre_rango` IN ('Talla Única', 'XS', 'S', 'M', 'L', 'XL', 'XXL');

-- Inventario y cotizaciones si aplican
UPDATE `inventario_productos` ip
INNER JOIN `rangos_tallas` rt_old ON rt_old.id = ip.`rango_tallas_id`
INNER JOIN `rangos_tallas` rt_new ON rt_new.`nombre_rango` = 'Adultos'
SET ip.`rango_tallas_id` = rt_new.id
WHERE rt_old.`nombre_rango` IN ('Talla Única', 'XS', 'S', 'M', 'L', 'XL', 'XXL');

UPDATE `inventario_detalle` idet
INNER JOIN `rangos_tallas` rt_old ON rt_old.id = idet.`rango_tallas_id`
INNER JOIN `rangos_tallas` rt_new ON rt_new.`nombre_rango` = 'Adultos'
SET idet.`rango_tallas_id` = rt_new.id
WHERE rt_old.`nombre_rango` IN ('Talla Única', 'XS', 'S', 'M', 'L', 'XL', 'XXL');

-- Eliminar rangos obsoletos (cascade elimina sus tallas)
DELETE FROM `rangos_tallas`
WHERE `nombre_rango` IN ('Talla Única', 'XS', 'S', 'M', 'L', 'XL', 'XXL');
