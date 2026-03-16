-- Migración: inventario con tipo_item + tipo_item_id (id de insumo o de receta).
-- Quita insumo_id, almacen_id, referencia_id. Añade enlace orden_produccion_id.
-- Ejecutar sobre una BD que tenga inventario (insumo_id, almacen_id) e inventario_productos.

-- 1) Crear tabla nueva (si no existe)
CREATE TABLE IF NOT EXISTS inventario_nueva (
  id int(11) NOT NULL AUTO_INCREMENT,
  tipo_item enum('insumo','producto') NOT NULL COMMENT 'insumo=id de insumos, producto=id de recetas',
  tipo_item_id int(11) NOT NULL COMMENT 'ID del insumo o de la receta según tipo_item',
  stock_actual decimal(12,2) NOT NULL DEFAULT 0.00,
  tipo_movimiento enum('compra','orden_produccion','manual','ajuste') DEFAULT 'manual',
  ultima_actualizacion timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  orden_produccion_id int(11) DEFAULT NULL COMMENT 'Enlace con orden de producción si aplica',
  PRIMARY KEY (id),
  UNIQUE KEY unique_tipo_item (tipo_item, tipo_item_id),
  KEY idx_tipo_item (tipo_item),
  KEY idx_tipo_item_id (tipo_item_id),
  KEY idx_orden_produccion (orden_produccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2) Copiar insumos: un registro por insumo_id (sumar stock si había varios almacenes)
INSERT IGNORE INTO inventario_nueva (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion)
SELECT 'insumo', insumo_id, COALESCE(SUM(stock_actual), 0), COALESCE(MAX(tipo_movimiento), 'manual'), MAX(ultima_actualizacion)
FROM inventario
GROUP BY insumo_id;

-- 3) Copiar productos: mapear (producto_id, rango_tallas_id, tipo_produccion_id) a receta_id
INSERT IGNORE INTO inventario_nueva (tipo_item, tipo_item_id, stock_actual, tipo_movimiento, ultima_actualizacion)
SELECT 'producto', r.id, ip.stock_actual, 'orden_produccion', ip.ultima_actualizacion
FROM inventario_productos ip
INNER JOIN recetas r ON r.producto_id = ip.producto_id
  AND r.rango_tallas_id = ip.rango_tallas_id
  AND r.tipo_produccion_id = ip.tipo_produccion_id;

-- 4) Reemplazar tabla (descomentar tras revisar datos)
-- RENAME TABLE inventario TO inventario_old, inventario_nueva TO inventario;

-- 5) FK a ordenes_produccion (ejecutar después del RENAME)
-- ALTER TABLE inventario
--   ADD CONSTRAINT fk_inventario_orden_produccion
--   FOREIGN KEY (orden_produccion_id) REFERENCES ordenes_produccion(id) ON DELETE SET NULL ON UPDATE CASCADE;

-- 6) Eliminar tablas viejas (solo cuando todo funcione)
-- DROP TABLE IF EXISTS inventario_old;
-- DROP TABLE IF EXISTS inventario_productos;
