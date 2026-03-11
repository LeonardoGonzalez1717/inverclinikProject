-- Migración: agregar FK tasa_cambiaria_id a insumos, recetas y ordenes_produccion
-- Ejecutar sobre db_inverclinik cuando las tablas ya existan.
-- La tabla tasas_cambiarias debe existir previamente.

USE db_inverclinik;

-- Insumos: guarda qué tasa se usó al registrar/actualizar el costo en dólares
ALTER TABLE `insumos`
  ADD COLUMN `tasa_cambiaria_id` int(11) DEFAULT NULL AFTER `proveedor_id`,
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  ADD CONSTRAINT `fk_insumos_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Recetas: guarda qué tasa se usó al registrar el precio total
ALTER TABLE `recetas`
  ADD COLUMN `tasa_cambiaria_id` int(11) DEFAULT NULL AFTER `precio_total`,
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  ADD CONSTRAINT `fk_recetas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Órdenes de producción: guarda qué tasa se usó al crear la orden (para el equivalente en Bs.)
ALTER TABLE `ordenes_produccion`
  ADD COLUMN `tasa_cambiaria_id` int(11) DEFAULT NULL AFTER `usuario_id`,
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  ADD CONSTRAINT `fk_ordenes_produccion_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
