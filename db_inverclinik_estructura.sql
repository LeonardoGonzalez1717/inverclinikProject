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

CREATE TABLE IF NOT EXISTS `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `numero_documento` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE IF NOT EXISTS `compras` (
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

CREATE TABLE IF NOT EXISTS `detalle_compra` (
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

CREATE TABLE IF NOT EXISTS `detalle_venta` (
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

CREATE TABLE IF NOT EXISTS `insumos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `unidad_medida` varchar(20) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` decimal(12,2) DEFAULT NULL COMMENT 'Stock mínimo deseado',
  `stock_maximo` decimal(12,2) DEFAULT NULL COMMENT 'Stock máximo deseado',
  `almacen_id` int(11) DEFAULT 1 COMMENT 'Almacén asociado al insumo',
  `proveedor_id` int(11) DEFAULT NULL,
  `tasa_cambiaria_id` int(11) DEFAULT NULL COMMENT 'Tasa usada al registrar/actualizar el costo',
  `activo` tinyint(1) DEFAULT 1,
  `adicional` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
-- tipo_item_id = id del insumo (si tipo_item=insumo) o id de la receta (si tipo_item=producto).
-- Enlace con ordenes_produccion cuando el stock proviene o se consume en una orden.
--

CREATE TABLE IF NOT EXISTS `inventario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_item` enum('insumo','producto') NOT NULL COMMENT 'insumo=id de insumos, producto=id de recetas',
  `tipo_item_id` int(11) NOT NULL COMMENT 'ID del insumo o de la receta según tipo_item',
  `stock_actual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tipo_movimiento` enum('compra','orden_produccion','manual','ajuste') DEFAULT 'manual',
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `orden_produccion_id` int(11) DEFAULT NULL COMMENT 'Enlace con orden de producción si aplica',
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_tipo_item` (`tipo_item`,`tipo_item_id`),
  KEY `idx_tipo_item` (`tipo_item`),
  KEY `idx_tipo_item_id` (`tipo_item_id`),
  KEY `idx_orden_produccion` (`orden_produccion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_detalle`
-- Registra movimientos tanto de insumos (materia prima) como de productos terminados.
--
CREATE TABLE IF NOT EXISTS `inventario_detalle` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_item` enum('insumo','producto') NOT NULL COMMENT 'insumo=materia prima, producto=producto terminado',
  `insumo_id` int(11) DEFAULT NULL COMMENT 'Obligatorio cuando tipo_item=insumo',
  `almacen_id` int(11) DEFAULT NULL COMMENT 'Almacén cuando tipo_item=insumo (materia prima)',
  `receta_id` int(11) DEFAULT NULL COMMENT 'Obligatorio cuando tipo_item=producto; la receta define producto/talla/tipo',
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `origen` varchar(50) DEFAULT 'manual',
  `observaciones` text DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `orden_produccion_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_produccion`
--

CREATE TABLE IF NOT EXISTS `ordenes_produccion` (
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

CREATE TABLE IF NOT EXISTS `productos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `tipo_genero` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE IF NOT EXISTS `proveedores` (
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

CREATE TABLE IF NOT EXISTS `rangos_tallas` (
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

CREATE TABLE IF NOT EXISTS `recetas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `producto_id` int(11) NOT NULL,
  `rango_tallas_id` int(11) NOT NULL,
  `tipo_produccion_id` int(11) NOT NULL,
  `almacen_id` int(11) DEFAULT NULL COMMENT 'Almacén al que pertenece la receta/inventario',
  `stock_minimo` decimal(12,2) DEFAULT NULL COMMENT 'Stock mínimo del producto terminado',
  `stock_maximo` decimal(12,2) DEFAULT NULL COMMENT 'Stock máximo del producto terminado',
  `observaciones` text DEFAULT NULL,
  `precio_total` decimal(10,2) DEFAULT 0.00,
  `precio_mayor` decimal(10,2) DEFAULT 0.00,
  `precio_detal` decimal(10,2) DEFAULT 0.00,
  `porcentaje_ganancia` decimal(5,2) DEFAULT NULL COMMENT 'Porcentaje de ganancia aplicado sobre el costo de la receta',
  `tasa_cambiaria_id` int(11) DEFAULT NULL COMMENT 'Tasa usada al registrar el precio',
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas_productos`
--

CREATE TABLE IF NOT EXISTS `recetas_productos` (
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

CREATE TABLE IF NOT EXISTS `tipos_produccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes`
--
CREATE TABLE IF NOT EXISTS `almacenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
-- Registro de auditoría del sistema (usada por lib/Auditoria.php).
--

CREATE TABLE IF NOT EXISTS `auditoria` (
  `id_evento` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) DEFAULT NULL COMMENT 'users.id si es staff',
  `id_cliente` int(11) DEFAULT NULL COMMENT 'clientes.id si es portal cliente',
  `nombre_actor` varchar(255) NOT NULL DEFAULT '',
  `modulo` varchar(120) DEFAULT NULL,
  `accion` text NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL,
  PRIMARY KEY (`id_evento`),
  KEY `idx_auditoria_fecha` (`fecha_hora`),
  KEY `idx_auditoria_user` (`id_usuario`),
  KEY `idx_auditoria_cliente` (`id_cliente`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE IF NOT EXISTS `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
INSERT IGNORE INTO `roles` (`id`, `nombre`) VALUES 
(1, 'admin'),
(2, 'supervisor'),
(3, 'cliente');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE IF NOT EXISTS `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cotizacion_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','entregado','cancelado') DEFAULT 'pendiente',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `tasa_cambiaria_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  KEY `cotizacion_id` (`cotizacion_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasas_cambiarias`
--

CREATE TABLE IF NOT EXISTS `tasas_cambiarias` (
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


CREATE TABLE IF NOT EXISTS `presupuestos` (
  `id_presupuesto` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) DEFAULT NULL,
  `codigo_presupuesto` varchar(20) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) DEFAULT NULL,
  `status` int(11) DEFAULT 0,
  PRIMARY KEY (`id_presupuesto`),
  UNIQUE KEY `codigo_presupuesto` (`codigo_presupuesto`),
  KEY `id_cliente` (`id_cliente`),
  CONSTRAINT `fk_presupuestos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `presupuesto_detalles` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_presupuesto` int(11) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_detalle`),
  KEY `id_presupuesto` (`id_presupuesto`),
  KEY `id_producto` (`id_producto`),
  CONSTRAINT `fk_presupuesto_detalles_presupuesto` FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuestos` (`id_presupuesto`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_presupuesto_detalles_producto` FOREIGN KEY (`id_producto`) REFERENCES `recetas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Estructura de tabla para la tabla `cotizaciones`
--
CREATE TABLE IF NOT EXISTS `cotizaciones` (
  `id_cotizacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `codigo_cotizacion` varchar(20) NOT NULL,
  `codigo_presupuesto_origen` varchar(20) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` int(11) DEFAULT 1 COMMENT '1: Enviada, 2: Aprobada/Venta, 3: Rechazada',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_cotizacion`),
  KEY `id_cliente` (`id_cliente`),
  CONSTRAINT `fk_cotizaciones_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE IF NOT EXISTS `cotizacion_detalles` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `id_cotizacion` INT NOT NULL,
  `id_receta` INT NOT NULL, 
  `id_talla` INT NOT NULL,
  `id_personalizacion` INT DEFAULT NULL, 
  `cantidad` INT NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL, 
  `subtotal` DECIMAL(10,2) NOT NULL, 
  `notas` TEXT DEFAULT NULL,
  `fecha_creacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

  FOREIGN KEY (id_cotizacion) REFERENCES cotizaciones(id_cotizacion) ON DELETE CASCADE,
  FOREIGN KEY (id_receta) REFERENCES recetas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cotizacion_id y activo ya están en CREATE TABLE ventas y productos

ALTER TABLE `detalle_venta`
  MODIFY COLUMN `producto_id` int(11) NOT NULL COMMENT 'ID de la Receta (recetas.id)',
  MODIFY COLUMN `cantidad` decimal(10,2) NOT NULL;


--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `orden_produccion_id` (`orden_produccion_id`);

ALTER TABLE proveedores 
ADD COLUMN cedrif VARCHAR(15) NOT NULL AFTER id;
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
-- Indices de la tabla `inventario` (definidos en CREATE TABLE)
--

--
-- Indices de la tabla `inventario_detalle`
--
ALTER TABLE `inventario_detalle`
  ADD KEY `idx_tipo_item` (`tipo_item`),
  ADD KEY `idx_insumo_id` (`insumo_id`),
  ADD KEY `idx_almacen_id` (`almacen_id`),
  ADD KEY `idx_receta_id` (`receta_id`),
  ADD KEY `idx_fecha` (`fecha_movimiento`),
  ADD KEY `idx_tipo` (`tipo`),
  ADD KEY `idx_orden_produccion` (`orden_produccion_id`);

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
  ADD KEY `almacen_id` (`almacen_id`),
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
-- Filtros para la tabla `detalle_venta` (producto_id = recetas.id)
--
ALTER TABLE `detalle_venta`
  ADD CONSTRAINT `fk_detalle_venta_receta` FOREIGN KEY (`producto_id`) REFERENCES `recetas` (`id`) ON UPDATE CASCADE,
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
  ADD CONSTRAINT `fk_inventario_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `inventario_detalle`
--
ALTER TABLE `inventario_detalle`
  ADD CONSTRAINT `fk_inventario_detalle_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventario_detalle_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventario_detalle_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventario_detalle_receta` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_recetas_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
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
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_clientes_rol` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

-- --------------------------------------------------------

--
-- Datos predeterminados: rangos de tallas
--

INSERT IGNORE INTO `rangos_tallas` (`nombre_rango`, `tallas_desde`, `tallas_hasta`, `descripcion`) VALUES
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

INSERT IGNORE INTO `tipos_produccion` (`nombre`, `descripcion`) VALUES
('Detal', 'Producción minorista'),
('Mayor', 'Producción mayorista');

COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;