-- Añade tasa_cambiaria_id a compras y ventas para reportes (equivalente en Bs. con la tasa del momento).
-- Ejecutar sobre db_inverclinik.

USE db_inverclinik;

-- Compras
ALTER TABLE `compras` ADD COLUMN `tasa_cambiaria_id` int(11) DEFAULT NULL AFTER `creado_en`;
ALTER TABLE `compras` ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);
ALTER TABLE `compras` ADD CONSTRAINT `fk_compras_tasa_cambiaria` 
  FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- Ventas
ALTER TABLE `ventas` ADD COLUMN `tasa_cambiaria_id` int(11) DEFAULT NULL AFTER `creado_en`;
ALTER TABLE `ventas` ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);
ALTER TABLE `ventas` ADD CONSTRAINT `fk_ventas_tasa_cambiaria` 
  FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
