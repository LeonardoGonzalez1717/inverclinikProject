-- Ejecutar en MySQL si la base ya existe sin estas columnas.
ALTER TABLE `cotizaciones`
  ADD COLUMN `comprobante_referencia` varchar(120) DEFAULT NULL COMMENT 'Número de referencia del pago indicado por el cliente' AFTER `fecha_registro`,
  ADD COLUMN `comprobante_archivo` varchar(255) DEFAULT NULL COMMENT 'Nombre de archivo en uploads/comprobantes_cotizaciones/' AFTER `comprobante_referencia`,
  ADD COLUMN `comprobante_fecha` datetime DEFAULT NULL COMMENT 'Última carga o actualización del comprobante' AFTER `comprobante_archivo`;
