-- Migración: tabla roles y users.role_id (reemplaza users.rol)
-- Ejecutar sobre db_inverclinik.

USE db_inverclinik;

-- 1. Crear tabla de roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 2. Insertar los 2 roles (ignorar si ya existen)
INSERT IGNORE INTO `roles` (`id`, `nombre`) VALUES (1, 'admin');
INSERT IGNORE INTO `roles` (`id`, `nombre`) VALUES (2, 'supervisor');

-- 3. Añadir columna role_id a users (si existe columna rol)
ALTER TABLE `users` ADD COLUMN `role_id` int(11) DEFAULT NULL AFTER `correo`;
ALTER TABLE `users` ADD KEY `role_id` (`role_id`);

-- 4. Migrar datos: asignar role_id según el valor actual de rol
UPDATE `users` SET `role_id` = 1 WHERE `rol` IN ('admin', 'administrador', 'superadmin');
UPDATE `users` SET `role_id` = 2 WHERE `rol` IN ('supervisor');
UPDATE `users` SET `role_id` = 1 WHERE `role_id` IS NULL AND `rol` IS NOT NULL AND `rol` != '';
UPDATE `users` SET `role_id` = 1 WHERE `role_id` IS NULL;

-- 5. Eliminar columna rol y añadir FK
ALTER TABLE `users` DROP COLUMN `rol`;
ALTER TABLE `users` MODIFY COLUMN `role_id` int(11) NOT NULL DEFAULT 1;
ALTER TABLE `users` ADD CONSTRAINT `fk_users_rol` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;
