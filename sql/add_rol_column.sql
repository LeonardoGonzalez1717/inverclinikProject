-- Script para agregar la columna 'rol' a la tabla 'users'
-- Ejecutar este script en la base de datos para habilitar el sistema de roles

-- Verificar si la columna ya existe antes de agregarla
SET @dbname = DATABASE();
SET @tablename = 'users';
SET @columnname = 'rol';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      (TABLE_SCHEMA = @dbname)
      AND (TABLE_NAME = @tablename)
      AND (COLUMN_NAME = @columnname)
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' ENUM(\'superadmin\', \'administrador\', \'cliente\') DEFAULT \'cliente\' AFTER correo')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Alternativa simple (si prefieres ejecutarlo directamente sin verificación):
-- ALTER TABLE `users` ADD COLUMN `rol` ENUM('superadmin', 'administrador', 'cliente') DEFAULT 'cliente' AFTER `correo`;

