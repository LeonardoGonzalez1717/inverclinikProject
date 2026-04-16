-- Ampliar enum de estado en ventas (ejecutar si la tabla ya existía sin estos valores).
ALTER TABLE `ventas`
  MODIFY COLUMN `estado` ENUM('pendiente','entregado','cancelado','aprobado','por_pagar') DEFAULT 'por_pagar';
