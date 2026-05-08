CREATE TABLE IF NOT EXISTS formas_pago (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL UNIQUE,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO formas_pago (nombre, activo)
SELECT 'pago movil', 1
WHERE NOT EXISTS (
    SELECT 1 FROM formas_pago WHERE LOWER(TRIM(nombre)) = 'pago movil'
);

INSERT INTO formas_pago (nombre, activo)
SELECT 'transferencia bancaria', 1
WHERE NOT EXISTS (
    SELECT 1 FROM formas_pago WHERE LOWER(TRIM(nombre)) = 'transferencia bancaria'
);

INSERT INTO formas_pago (nombre, activo)
SELECT 'efectivo', 1
WHERE NOT EXISTS (
    SELECT 1 FROM formas_pago WHERE LOWER(TRIM(nombre)) = 'efectivo'
);

INSERT INTO formas_pago (nombre, activo)
SELECT 'divisa', 1
WHERE NOT EXISTS (
    SELECT 1 FROM formas_pago WHERE LOWER(TRIM(nombre)) = 'divisa'
);

ALTER TABLE ventas
    ADD COLUMN IF NOT EXISTS forma_pago_id INT NULL AFTER comprobante_referencia;

SET @tiene_fk_forma_pago := (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ventas'
      AND COLUMN_NAME = 'forma_pago_id'
      AND REFERENCED_TABLE_NAME = 'formas_pago'
);

SET @sql_fk_forma_pago := IF(
    @tiene_fk_forma_pago = 0,
    'ALTER TABLE ventas ADD CONSTRAINT fk_ventas_forma_pago FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id) ON UPDATE CASCADE ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_fk_forma_pago FROM @sql_fk_forma_pago;
EXECUTE stmt_fk_forma_pago;
DEALLOCATE PREPARE stmt_fk_forma_pago;
