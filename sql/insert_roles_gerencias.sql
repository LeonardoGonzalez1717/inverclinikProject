-- Nuevos roles de gerencia (ids 4, 5, 6).
-- Ejecutar sobre db_inverclinik después de tener la tabla `roles` con ids 1–3.

USE db_inverclinik;

-- Gerencia de producción: órdenes de producción e inventario asociado
INSERT IGNORE INTO `roles` (`id`, `nombre`) VALUES (4, 'Gerencia de producción');

-- Gerencia comercial: ventas
INSERT IGNORE INTO `roles` (`id`, `nombre`) VALUES (5, 'Gerencia comercial');

-- Gerencia administrativa: compras
INSERT IGNORE INTO `roles` (`id`, `nombre`) VALUES (6, 'Gerencia administrativa');
