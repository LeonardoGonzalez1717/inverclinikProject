-- phpMyAdmin SQL Dump
-- Estructura de base de datos: `db_inverclinik`
-- Solo creación de tablas, PRIMARY KEY y FOREIGN KEY

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `numero_documento` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `proveedor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','recibido','cancelado') DEFAULT 'pendiente',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `tasa_cambiaria_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_compra`
--

CREATE TABLE `detalle_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `costo_unitario`) STORED,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos`
--

CREATE TABLE `insumos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `unidad_medida` varchar(20) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `proveedor_id` int(11) DEFAULT NULL,
  `tasa_cambiaria_id` int(11) DEFAULT NULL COMMENT 'Tasa usada al registrar/actualizar el costo',
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `insumo_id` int(11) NOT NULL,
  `stock_actual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tipo_movimiento` enum('compra','orden_produccion','manual','ajuste') DEFAULT 'manual',
  `referencia_id` int(11) DEFAULT NULL,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_productos`
--

CREATE TABLE `inventario_productos` (
  `producto_id` int(11) NOT NULL,
  `rango_tallas_id` int(11) NOT NULL,
  `tipo_produccion_id` int(11) NOT NULL,
  `stock_actual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_inventario_detalle`
--

CREATE TABLE `movimientos_inventario_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `insumo_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `stock_anterior` decimal(12,2) NOT NULL DEFAULT 0.00,
  `stock_posterior` decimal(12,2) NOT NULL DEFAULT 0.00,
  `origen` varchar(50) DEFAULT 'manual',
  `observaciones` text DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `orden_produccion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `movimientos_productos_detalle`
--

CREATE TABLE `movimientos_productos_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `rango_tallas_id` int(11) NOT NULL,
  `tipo_produccion_id` int(11) NOT NULL,
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_produccion`
--

CREATE TABLE `ordenes_produccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `receta_producto_id` int(11) NOT NULL,
  `cantidad_a_producir` decimal(10,2) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','finalizado','cancelado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `tasa_cambiaria_id` int(11) DEFAULT NULL COMMENT 'Tasa usada al crear la orden',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `tipo_genero` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rangos_tallas`
--

CREATE TABLE `rangos_tallas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rango` varchar(50) NOT NULL,
  `tallas_desde` int(11) NOT NULL,
  `tallas_hasta` int(11) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas`
--

CREATE TABLE `recetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `rango_tallas_id` int(11) NOT NULL,
  `tipo_produccion_id` int(11) NOT NULL,
  `observaciones` text DEFAULT NULL,
  `precio_total` decimal(10,2) DEFAULT 0.00,
  `tasa_cambiaria_id` int(11) DEFAULT NULL COMMENT 'Tasa usada al registrar el precio',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas_productos`
--

CREATE TABLE `recetas_productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `rango_tallas_id` int(11) NOT NULL,
  `tipo_produccion_id` int(11) NOT NULL,
  `cantidad_por_unidad` decimal(8,4) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `costo_por_unidad` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_produccion`
--

CREATE TABLE `tipos_produccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Datos iniciales de roles
--
INSERT INTO `roles` (`id`, `nombre`) VALUES (1, 'admin'), (2, 'supervisor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cliente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','entregado','cancelado') DEFAULT 'pendiente',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `tasa_cambiaria_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasas_cambiarias`
--

CREATE TABLE `tasas_cambiarias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tasa` decimal(18,8) NOT NULL COMMENT 'Tasa USD/BS',
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora del registro',
  `origen` enum('bcv','manual') NOT NULL DEFAULT 'manual',
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fecha_hora` (`fecha_hora`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro de tasas cambiarias por hora';

-- --------------------------------------------------------
--
-- Estructura de tabla para la tabla `presupuestos`
--


CREATE TABLE presupuestos (
    id_presupuesto INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    codigo_presupuesto VARCHAR(20) UNIQUE,
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2),
    FOREIGN KEY (id_cliente) REFERENCES clientes(id)
);

CREATE TABLE presupuesto_detalles (
    id_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_presupuesto INT,
    id_producto INT(11) NOT NULL,
    cantidad INT,
    precio_unitario DECIMAL(10,2),
    subtotal DECIMAL(10,2),
    FOREIGN KEY (id_presupuesto) REFERENCES presupuestos(id_presupuesto)
);

ALTER TABLE presupuestos 
ADD COLUMN status INT DEFAULT 0 AFTER total;

-- Cabecera de Cotizaciones
CREATE TABLE cotizaciones (
    id_cotizacion INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT NOT NULL,
    codigo_cotizacion VARCHAR(20) NOT NULL, 
    codigo_presupuesto_origen VARCHAR(20),  
    total DECIMAL(10,2) NOT NULL,
    status INT DEFAULT 1,                   -- 1: Enviada, 2: Aprobada/Venta, 3: Rechazada
    fecha_registro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_cliente) REFERENCES clientes(id)
) ENGINE=InnoDB;

CREATE TABLE cotizacion_detalles (
    id_cot_detalle INT AUTO_INCREMENT PRIMARY KEY,
    id_cotizacion INT NOT NULL,
    id_producto INT NOT NULL,
    cantidad INT NOT NULL,
    talla VARCHAR(10),
    personalizacion VARCHAR(50),            
    notas TEXT,                             
    precio_unitario DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_cotizacion) REFERENCES cotizaciones(id_cotizacion) ON DELETE CASCADE,
    FOREIGN KEY (id_producto) REFERENCES productos(id)
) ENGINE=InnoDB;

ALTER TABLE productos 
ADD COLUMN activo TINYINT(1) DEFAULT 0;

ALTER TABLE presupuestos_detalles 
ADD COLUMN id_producto INT(11) NOT NULL AFTER id_presupuesto;

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `orden_produccion_id` (`orden_produccion_id`);

--
-- Indices de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  ADD KEY `compra_id` (`compra_id`),
  ADD KEY `insumo_id` (`insumo_id`);

--
-- Indices de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  ADD KEY `venta_id` (`venta_id`),
  ADD KEY `producto_id` (`producto_id`);

--
-- Indices de la tabla `insumos`
--
ALTER TABLE `insumos`
  ADD KEY `proveedor` (`proveedor_id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`insumo_id`),
  ADD KEY `idx_tipo_movimiento` (`tipo_movimiento`);

--
-- Indices de la tabla `inventario_productos`
--
ALTER TABLE `inventario_productos`
  ADD PRIMARY KEY (`producto_id`,`rango_tallas_id`,`tipo_produccion_id`);

--
-- Indices de la tabla `movimientos_inventario_detalle`
--
ALTER TABLE `movimientos_inventario_detalle`
  ADD KEY `idx_insumo_id` (`insumo_id`),
  ADD KEY `idx_fecha` (`fecha_movimiento`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_orden_produccion` (`orden_produccion_id`);

--
-- Indices de la tabla `movimientos_productos_detalle`
--
ALTER TABLE `movimientos_productos_detalle`
  ADD KEY `idx_producto` (`producto_id`,`rango_tallas_id`,`tipo_produccion_id`),
  ADD KEY `idx_fecha` (`fecha_movimiento`),
  ADD KEY `idx_tipo` (`tipo`);

--
-- Indices de la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  ADD KEY `receta_producto_id` (`receta_producto_id`),
  ADD KEY `fk_ordenes_produccion_usuario` (`usuario_id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);

--
-- Indices de la tabla `proveedores`
--

--
-- Indices de la tabla `rangos_tallas`
--

--
-- Indices de la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD UNIQUE KEY `unique_receta` (`producto_id`,`rango_tallas_id`,`tipo_produccion_id`),
  ADD KEY `rango_tallas_id` (`rango_tallas_id`),
  ADD KEY `tipo_produccion_id` (`tipo_produccion_id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);

--
-- Indices de la tabla `recetas_productos`
--
ALTER TABLE `recetas_productos`
  ADD UNIQUE KEY `uk_receta` (`producto_id`,`insumo_id`,`rango_tallas_id`,`tipo_produccion_id`),
  ADD KEY `insumo_id` (`insumo_id`),
  ADD KEY `rango_tallas_id` (`rango_tallas_id`),
  ADD KEY `tipo_produccion_id` (`tipo_produccion_id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD KEY `role_id` (`role_id`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `orden_produccion_id` (`orden_produccion_id`);

-- --------------------------------------------------------

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compras_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compras_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compras_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_insumos_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_insumos_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD CONSTRAINT `fk_inventario_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `movimientos_inventario_detalle`
--
ALTER TABLE `movimientos_inventario_detalle`
  ADD CONSTRAINT `fk_movimientos_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL;

--
-- Filtros para la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  ADD CONSTRAINT `fk_ordenes_produccion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `ordenes_produccion_ibfk_1` FOREIGN KEY (`receta_producto_id`) REFERENCES `recetas_productos` (`id`),
  ADD CONSTRAINT `fk_ordenes_produccion_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD CONSTRAINT `recetas_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recetas_ibfk_2` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recetas_ibfk_3` FOREIGN KEY (`tipo_produccion_id`) REFERENCES `tipos_produccion` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_recetas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `recetas_productos`
--
ALTER TABLE `recetas_productos`
  ADD CONSTRAINT `recetas_productos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recetas_productos_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`),
  ADD CONSTRAINT `recetas_productos_ibfk_3` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`),
  ADD CONSTRAINT `recetas_productos_ibfk_4` FOREIGN KEY (`tipo_produccion_id`) REFERENCES `tipos_produccion` (`id`);

--
-- Filtros para la tabla `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_rol` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- --------------------------------------------------------

--
-- Datos predeterminados: rangos de tallas
--

INSERT INTO `rangos_tallas` (`nombre_rango`, `tallas_desde`, `tallas_hasta`, `descripcion`) VALUES
('Talla Única', 1, 1, 'Una sola talla'),
('Niños', 2, 14, 'Tallas infantiles'),
('XS', 32, 34, 'Extra pequeño'),
('S', 36, 38, 'Pequeño'),
('M', 40, 42, 'Mediano'),
('L', 44, 46, 'Grande'),
('XL', 48, 50, 'Extra grande'),
('XXL', 52, 54, 'Doble extra grande');

--
-- Datos predeterminados: tipos de producción
--

INSERT INTO `tipos_produccion` (`nombre`, `descripcion`) VALUES
('Serie', 'Producción en serie o volumen'),
('Unidad', 'Producción unitaria o pedido único'),
('Muestra', 'Muestra o prototipo');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

      
      
      /*tabla de categoria*/

      CREATE TABLE categorias (
    id INT(11) NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (id)
);