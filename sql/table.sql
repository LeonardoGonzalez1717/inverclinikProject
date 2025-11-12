-- Tabla de usuarios
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `numero_documento` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `compras` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','recibido','cancelado') DEFAULT 'pendiente',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_compra` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `costo_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detalle_venta` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `insumos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `unidad_medida` varchar(20) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `proveedor_id` int(11) DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `inventario` (
  `insumo_id` int(11) NOT NULL,
  `stock_actual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ordenes_produccion` (
  `id` int(11) NOT NULL,
  `receta_producto_id` int(11) NOT NULL,
  `cantidad_a_producir` decimal(10,2) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','finalizado','cancelado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `tipo_genero` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `contacto` varchar(100) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `rangos_tallas` (
  `id` int(11) NOT NULL,
  `nombre_rango` varchar(50) NOT NULL,
  `tallas_desde` int(11) NOT NULL,
  `tallas_hasta` int(11) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `recetas_productos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `rango_tallas_id` int(11) NOT NULL,
  `tipo_produccion_id` int(11) NOT NULL,
  `cantidad_por_unidad` decimal(8,4) NOT NULL,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tipos_produccion` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` int(100) NOT NULL,
  `login` varchar(255) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','entregado','cancelado') DEFAULT 'pendiente',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `orden_produccion_id` (`orden_produccion_id`);

--
-- Indices de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD PRIMARY KEY (`id`),
  ADD KEY `compra_id` (`compra_id`),
  ADD KEY `insumo_id` (`insumo_id`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD PRIMARY KEY (`id`),
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `proveedor` (`proveedor_id`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`insumo_id`);

--
-- Indices de la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  ADD PRIMARY KEY (`id`),
  ADD KEY `receta_producto_id` (`receta_producto_id`),
  ADD KEY `fk_ordenes_produccion_usuario` (`usuario_id`);

--
-- Indices de la tabla `productos`
--
ALTER TABLE `productos`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `rangos_tallas`
--
ALTER TABLE `rangos_tallas`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `recetas_productos`
--
ALTER TABLE `recetas_productos`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uk_receta` (`producto_id`,`insumo_id`,`rango_tallas_id`,`tipo_produccion_id`),
  ADD KEY `insumo_id` (`insumo_id`),
  ADD KEY `rango_tallas_id` (`rango_tallas_id`),
  ADD KEY `tipo_produccion_id` (`tipo_produccion_id`);

--
-- Indices de la tabla `tipos_produccion`
--
ALTER TABLE `tipos_produccion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `orden_produccion_id` (`orden_produccion_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `rangos_tallas`
--
ALTER TABLE `rangos_tallas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `recetas_productos`
--
ALTER TABLE `recetas_productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT de la tabla `tipos_produccion`
--
ALTER TABLE `tipos_produccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compras_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compras_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD CONSTRAINT `fk_detalle_compra_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_compra_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `fk_detalle_venta_producto` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_detalle_venta_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD CONSTRAINT `fk_insumos_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `fk_inventario_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  ADD CONSTRAINT `fk_ordenes_produccion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `ordenes_produccion_ibfk_1` FOREIGN KEY (`receta_producto_id`) REFERENCES `recetas_productos` (`id`);

--
-- Filtros para la tabla `recetas_productos`
--
ALTER TABLE `recetas_productos`
  ADD CONSTRAINT `recetas_productos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recetas_productos_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`),
  ADD CONSTRAINT `recetas_productos_ibfk_3` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`),
  ADD CONSTRAINT `recetas_productos_ibfk_4` FOREIGN KEY (`tipo_produccion_id`) REFERENCES `tipos_produccion` (`id`);

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;


-- Tabla de proveedores
CREATE TABLE proveedores (
    id_proveedor INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    rif VARCHAR(20) UNIQUE NOT NULL,
    direccion TEXT,
    telefono VARCHAR(20),
    correo VARCHAR(100)
);

-- Inventario de materia prima
CREATE TABLE inventario_materia_prima (
    id_insumo INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    unidad_medida VARCHAR(20),
    cantidad DECIMAL(10,2) NOT NULL
);

-- Stock de productos terminados
CREATE TABLE stock_productos (
    id_producto INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    talla VARCHAR(10),
    color VARCHAR(50),
    cantidad INT NOT NULL
);

-- Tabla de prendas (modelos de uniforme)
CREATE TABLE prendas (
    id_prenda INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion TEXT
);

-- Receta por prenda (relación prenda ↔ insumos)
CREATE TABLE receta_prenda (
    id_receta INT PRIMARY KEY AUTO_INCREMENT,
    id_prenda INT,
    id_insumo INT,
    cantidad_requerida DECIMAL(10,2),
    unidad_medida VARCHAR(20),
    FOREIGN KEY (id_prenda) REFERENCES prendas(id_prenda),
    FOREIGN KEY (id_insumo) REFERENCES inventario_materia_prima(id_insumo)
);

-- Ventas
CREATE TABLE ventas (
    id_venta INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE NOT NULL,
    cliente VARCHAR(100),
    tipo_venta ENUM('encargo', 'stock') NOT NULL,
    producto VARCHAR(100),
    cantidad INT,
    monto DECIMAL(10,2),
    id_usuario INT,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- Órdenes de producción
CREATE TABLE ordenes_produccion (
    id_orden INT PRIMARY KEY AUTO_INCREMENT,
    id_venta INT,
    id_prenda INT,
    cantidad INT,
    fecha_inicio DATE,
    fecha_fin DATE,
    estado ENUM('pendiente', 'en_proceso', 'finalizada') DEFAULT 'pendiente',
    id_usuario INT,
    FOREIGN KEY (id_venta) REFERENCES ventas(id_venta),
    FOREIGN KEY (id_prenda) REFERENCES prendas(id_prenda),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- Compras de insumos
CREATE TABLE compras (
    id_compra INT PRIMARY KEY AUTO_INCREMENT,
    fecha DATE NOT NULL,
    insumo VARCHAR(100),
    cantidad DECIMAL(10,2),
    costo DECIMAL(10,2),
    id_proveedor INT,
    id_usuario INT,
    FOREIGN KEY (id_proveedor) REFERENCES proveedores(id_proveedor),
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- Movimientos de inventario
CREATE TABLE movimientos_inventario (
    id_movimiento INT PRIMARY KEY AUTO_INCREMENT,
    tipo ENUM('entrada', 'salida') NOT NULL,
    origen ENUM('produccion', 'venta_stock', 'compra') NOT NULL,
    id_referencia INT,
    item VARCHAR(100),
    cantidad DECIMAL(10,2),
    fecha DATE
);

-- Auditoría
CREATE TABLE auditoria (
    id_evento INT PRIMARY KEY AUTO_INCREMENT,
    id_usuario INT,
    accion TEXT,
    fecha_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
);

-- TRIGGERS
CREATE TRIGGER descontar_insumos_produccion
AFTER INSERT ON ordenes_produccion
FOR EACH ROW
BEGIN
  DECLARE done INT DEFAULT FALSE;
  DECLARE insumo_id INT;
  DECLARE cantidad_unitaria DECIMAL(10,2);
  DECLARE cur CURSOR FOR
    SELECT id_insumo, cantidad_requerida
    FROM receta_prenda
    WHERE id_prenda = NEW.id_prenda;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;

  OPEN cur;
  read_loop: LOOP
    FETCH cur INTO insumo_id, cantidad_unitaria;
    IF done THEN
      LEAVE read_loop;
    END IF;
    UPDATE inventario_materia_prima
    SET cantidad = cantidad - (cantidad_unitaria * NEW.cantidad)
    WHERE id_insumo = insumo_id;
  END LOOP;
  CLOSE cur;
END;

CREATE TRIGGER agregar_stock_al_finalizar
AFTER UPDATE ON ordenes_produccion
FOR EACH ROW
BEGIN
  IF NEW.estado = 'finalizada' AND OLD.estado != 'finalizada' THEN
    INSERT INTO stock_productos (nombre, talla, color, cantidad)
    VALUES (
      (SELECT nombre FROM prendas WHERE id_prenda = NEW.id_prenda),
      'M', 'Azul', NEW.cantidad
    )
    ON DUPLICATE KEY UPDATE cantidad = cantidad + NEW.cantidad;
  END IF;
END;

CREATE TRIGGER registrar_movimiento_venta
AFTER INSERT ON ventas
FOR EACH ROW
BEGIN
  INSERT INTO movimientos_inventario (tipo, origen, id_referencia, item, cantidad, fecha)
  VALUES (
    'salida',
    IF(NEW.tipo_venta = 'stock', 'venta_stock', 'produccion'),
    NEW.id_venta,
    NEW.producto,
    NEW.cantidad,
    NEW.fecha
  );
END;

-- VISTAS
CREATE VIEW vista_ventas_mensuales AS
SELECT 
  DATE_FORMAT(fecha, '%Y-%m') AS mes,
  tipo_venta,
  COUNT(*) AS total_ventas,
  SUM(monto) AS monto_total
FROM ventas
GROUP BY DATE_FORMAT(fecha, '%Y-%m'), tipo_venta;

CREATE VIEW vista_insumos_consumidos AS
SELECT 
  r.id_insumo,
  i.nombre AS insumo,
  SUM(r.cantidad_requerida * op.cantidad) AS total_consumido
FROM receta_prenda r
JOIN ordenes_produccion op ON r.id_prenda = op.id_prenda
JOIN inventario_materia_prima i ON r.id_insumo = i.id_insumo
GROUP BY r.id_insumo, i.nombre;

CREATE VIEW vista_ordenes_activas AS
SELECT 
  op.id_orden,
  p.nombre AS prenda,
  op.cantidad,
  op.estado,
  op.fecha_inicio,
  u.nombre AS responsable
FROM ordenes_produccion op
JOIN prendas p ON op.id_prenda = p.id_prenda
JOIN usuarios u ON op.id_usuario = u.id_usuario
WHERE op.estado IN ('pendiente', 'en_proceso');

CREATE VIEW vista_stock_actual AS
SELECT 
  nombre,
  talla,
  color,
  cantidad
FROM stock_productos
WHERE cantidad > 0;

CREATE VIEW vista_movimientos_inventario AS
SELECT 
  tipo,
  origen,
  item,
  cantidad,
  fecha,
  id_referencia
FROM movimientos_inventario
ORDER BY fecha DESC;

CREATE VIEW vista_compras_por_proveedor AS
SELECT 
  p.nombre AS proveedor,
  c.insumo,
  SUM(c.cantidad) AS total_comprado,
  SUM(c.costo) AS total_gastado
FROM compras c
JOIN proveedores p ON c.id_proveedor = p.id_proveedor
GROUP BY p.nombre, c.insumo;