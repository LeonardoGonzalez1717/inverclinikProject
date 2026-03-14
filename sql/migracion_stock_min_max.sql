-- Migración: stock mínimo y máximo en insumos (materia prima) y recetas (productos terminados)
-- Ejecutar una sola vez. Si la columna ya existe, omitir esa línea.

-- Insumos (materia prima): stock min/max se definen al crear/editar el insumo
ALTER TABLE insumos ADD COLUMN stock_minimo decimal(12,2) DEFAULT NULL COMMENT 'Stock mínimo deseado' AFTER costo_unitario;
ALTER TABLE insumos ADD COLUMN stock_maximo decimal(12,2) DEFAULT NULL COMMENT 'Stock máximo deseado' AFTER stock_minimo;

-- Recetas (productos terminados)
ALTER TABLE recetas ADD COLUMN stock_minimo decimal(12,2) DEFAULT NULL COMMENT 'Stock mínimo del producto terminado';
ALTER TABLE recetas ADD COLUMN stock_maximo decimal(12,2) DEFAULT NULL COMMENT 'Stock máximo del producto terminado';

-- Opcional: si inventario ya tenía stock_minimo/stock_maximo, quitarlos (ejecutar solo si existen)
-- ALTER TABLE inventario DROP COLUMN stock_minimo;
-- ALTER TABLE inventario DROP COLUMN stock_maximo;
