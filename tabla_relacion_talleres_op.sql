CREATE TABLE ordenes_talleres (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orden_produccion_id INT NOT NULL,
    taller_id INT NOT NULL,
    fecha_asignacion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_entrega DATETIME NULL, 
    recibido TINYINT(1) NOT NULL DEFAULT 0, 
    observaciones TEXT NULL,
    CONSTRAINT fk_talleres_orden FOREIGN KEY (orden_produccion_id) 
        REFERENCES ordenes_produccion(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE,
        
    CONSTRAINT fk_talleres_proveedor FOREIGN KEY (taller_id) 
        REFERENCES talleres(id)
        ON DELETE RESTRICT 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;