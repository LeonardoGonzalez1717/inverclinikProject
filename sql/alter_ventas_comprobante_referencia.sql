ALTER TABLE `ventas`
  ADD COLUMN `comprobante_referencia` varchar(120) DEFAULT NULL COMMENT 'Referencia o número de comprobante de pago asociado a la venta' AFTER `tasa_cambiaria_id`;
