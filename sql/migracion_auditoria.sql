-- Tabla de auditoría (usuario/cliente, módulo, acción, fecha, IP)
-- Ejecutar en bases que aún no incluyan esta tabla (ver también db_inverclinik_estructura.sql).

CREATE TABLE IF NOT EXISTS auditoria (
    id_evento INT NOT NULL AUTO_INCREMENT,
    id_usuario INT NULL COMMENT 'users.id si es personal interno',
    id_cliente INT NULL COMMENT 'clientes.id si es portal cliente',
    nombre_actor VARCHAR(255) NOT NULL DEFAULT '',
    modulo VARCHAR(120) NULL,
    accion TEXT NOT NULL,
    fecha_hora DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45) NULL,
    PRIMARY KEY (id_evento),
    KEY idx_auditoria_fecha (fecha_hora),
    KEY idx_auditoria_user (id_usuario),
    KEY idx_auditoria_cliente (id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
