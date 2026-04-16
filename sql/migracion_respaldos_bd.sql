-- Registro de respaldos generados (metadatos). Los archivos .sql siguen en storage/respaldos_bd/
-- Ejecutar una vez en la base de datos (phpMyAdmin o mysql CLI).

CREATE TABLE IF NOT EXISTS `respaldos_bd` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre_archivo` varchar(255) NOT NULL,
  `tamano_bytes` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `creado_en` datetime NOT NULL,
  `origen` enum('automatico','manual') NOT NULL DEFAULT 'automatico',
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre_archivo` (`nombre_archivo`),
  KEY `idx_creado_en` (`creado_en`),
  KEY `fk_respaldos_bd_usuario` (`usuario_id`),
  CONSTRAINT `fk_respaldos_bd_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
