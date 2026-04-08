-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Servidor: 127.0.0.1
-- Tiempo de generación: 08-04-2026 a las 05:41:06
-- Versión del servidor: 10.4.32-MariaDB
-- Versión de PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de datos: `db_inverclinik`
--

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `almacenes`
--

CREATE TABLE `almacenes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `almacenes`
--

INSERT INTO `almacenes` (`id`, `nombre`, `codigo`, `activo`) VALUES
(1, 'Principal', 'ALM01', 1),
(2, 'Almacen b2', 'ALM-002', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `auditoria`
--

CREATE TABLE `auditoria` (
  `id_evento` int(11) NOT NULL,
  `id_usuario` int(11) DEFAULT NULL COMMENT 'users.id si es staff',
  `id_cliente` int(11) DEFAULT NULL COMMENT 'clientes.id si es portal cliente',
  `nombre_actor` varchar(255) NOT NULL DEFAULT '',
  `modulo` varchar(120) DEFAULT NULL,
  `accion` text NOT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `ip` varchar(45) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `auditoria`
--

INSERT INTO `auditoria` (`id_evento`, `id_usuario`, `id_cliente`, `nombre_actor`, `modulo`, `accion`, `fecha_hora`, `ip`) VALUES
(1, 1, NULL, 'Leonardo', 'Sesión', 'Inicio de sesión exitoso (usuario interno). Correo: leonardojgc2002@gmail.com. Rol: admin', '2026-04-07 16:11:40', '::1'),
(2, 1, NULL, 'Leonardo', 'Perfil', 'Perfil actualizado y contraseña modificada (usuario interno id 1).', '2026-04-07 16:16:12', '::1'),
(3, 1, NULL, 'Leonardo', 'Ventas', 'Venta #3 registrada. Cliente #2. Factura: 2. Total: 780.', '2026-04-07 16:22:21', '::1');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `categorias`
--

CREATE TABLE `categorias` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `categorias`
--

INSERT INTO `categorias` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Infantil', 'asd'),
(3, 'Adulto', 'ss');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `clientes`
--

CREATE TABLE `clientes` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `numero_documento` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `clientes`
--

INSERT INTO `clientes` (`id`, `nombre`, `tipo_documento`, `numero_documento`, `telefono`, `email`, `password`, `direccion`, `role_id`) VALUES
(2, 'Leonardo', 'V', '30611935', NULL, 'leitogonza1717@gmail.com', '$2y$10$5OKzZllnt0ukWawvIVDakueEEayZTwHWscPN9uQ1ErdqM9SOgGfR6', NULL, 3);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `compras`
--

CREATE TABLE `compras` (
  `id` int(11) NOT NULL,
  `proveedor_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','recibido','cancelado') DEFAULT 'pendiente',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `tasa_cambiaria_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizaciones`
--

CREATE TABLE `cotizaciones` (
  `id_cotizacion` int(11) NOT NULL,
  `id_cliente` int(11) NOT NULL,
  `codigo_cotizacion` varchar(20) NOT NULL,
  `codigo_presupuesto_origen` varchar(20) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` int(11) DEFAULT 1 COMMENT '1: Enviada, 2: Aprobada/Venta, 3: Rechazada',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `cotizaciones`
--

INSERT INTO `cotizaciones` (`id_cotizacion`, `id_cliente`, `codigo_cotizacion`, `codigo_presupuesto_origen`, `total`, `status`, `fecha_registro`) VALUES
(1, 2, 'COT-0001', 'PRE-0001', 260.00, 2, '2026-04-07 19:22:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `cotizacion_detalles`
--

CREATE TABLE `cotizacion_detalles` (
  `id` int(11) NOT NULL,
  `id_cotizacion` int(11) NOT NULL,
  `id_receta` int(11) NOT NULL,
  `id_talla` int(11) NOT NULL,
  `id_personalizacion` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notas` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Volcado de datos para la tabla `cotizacion_detalles`
--

INSERT INTO `cotizacion_detalles` (`id`, `id_cotizacion`, `id_receta`, `id_talla`, `id_personalizacion`, `cantidad`, `precio_unitario`, `subtotal`, `notas`, `fecha_creacion`) VALUES
(1, 1, 1, 6, NULL, 2, 130.00, 260.00, '', '2026-04-07 19:22:16');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_compra`
--

CREATE TABLE `detalle_compra` (
  `id` int(11) NOT NULL,
  `compra_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `costo_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `detalle_venta`
--

CREATE TABLE `detalle_venta` (
  `id` int(11) NOT NULL,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL COMMENT 'ID de la Receta (recetas.id)',
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `detalle_venta`
--

INSERT INTO `detalle_venta` (`id`, `venta_id`, `producto_id`, `cantidad`, `precio_unitario`) VALUES
(2, 2, 1, 2.00, 130.00),
(3, 3, 1, 6.00, 130.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `insumos`
--

CREATE TABLE `insumos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `unidad_medida` varchar(20) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `stock_minimo` decimal(12,2) DEFAULT NULL COMMENT 'Stock mínimo deseado',
  `stock_maximo` decimal(12,2) DEFAULT NULL COMMENT 'Stock máximo deseado',
  `almacen_id` int(11) DEFAULT 1 COMMENT 'Almacén asociado al insumo',
  `proveedor_id` int(11) DEFAULT NULL,
  `tasa_cambiaria_id` int(11) DEFAULT NULL COMMENT 'Tasa usada al registrar/actualizar el costo',
  `activo` tinyint(1) DEFAULT 1,
  `adicional` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `insumos`
--

INSERT INTO `insumos` (`id`, `nombre`, `unidad_medida`, `costo_unitario`, `stock_minimo`, `stock_maximo`, `almacen_id`, `proveedor_id`, `tasa_cambiaria_id`, `activo`, `adicional`) VALUES
(1, 'Tela para jeans', 'metro', 10.00, 10.00, 100.00, 1, 1, 1, 1, 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario`
--

CREATE TABLE `inventario` (
  `id` int(11) NOT NULL,
  `tipo_item` enum('insumo','producto') NOT NULL COMMENT 'insumo=id de insumos, producto=id de recetas',
  `tipo_item_id` int(11) NOT NULL COMMENT 'ID del insumo o de la receta según tipo_item',
  `stock_actual` decimal(12,2) NOT NULL DEFAULT 0.00,
  `tipo_movimiento` enum('compra','orden_produccion','manual','ajuste') DEFAULT 'manual',
  `ultima_actualizacion` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `orden_produccion_id` int(11) DEFAULT NULL COMMENT 'Enlace con orden de producción si aplica'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario`
--

INSERT INTO `inventario` (`id`, `tipo_item`, `tipo_item_id`, `stock_actual`, `tipo_movimiento`, `ultima_actualizacion`, `orden_produccion_id`) VALUES
(1, 'insumo', 1, 0.00, 'manual', '2026-04-07 18:31:01', 1),
(10, 'producto', 1, 2.00, 'orden_produccion', '2026-04-07 20:22:21', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `inventario_detalle`
--

CREATE TABLE `inventario_detalle` (
  `id` int(11) NOT NULL,
  `tipo_item` enum('insumo','producto') NOT NULL COMMENT 'insumo=materia prima, producto=producto terminado',
  `insumo_id` int(11) DEFAULT NULL COMMENT 'Obligatorio cuando tipo_item=insumo',
  `almacen_id` int(11) DEFAULT NULL COMMENT 'Almacén cuando tipo_item=insumo (materia prima)',
  `receta_id` int(11) DEFAULT NULL COMMENT 'Obligatorio cuando tipo_item=producto; la receta define producto/talla/tipo',
  `tipo` enum('entrada','salida') NOT NULL,
  `cantidad` decimal(12,2) NOT NULL,
  `origen` varchar(50) DEFAULT 'manual',
  `observaciones` text DEFAULT NULL,
  `fecha_movimiento` timestamp NOT NULL DEFAULT current_timestamp(),
  `orden_produccion_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `inventario_detalle`
--

INSERT INTO `inventario_detalle` (`id`, `tipo_item`, `insumo_id`, `almacen_id`, `receta_id`, `tipo`, `cantidad`, `origen`, `observaciones`, `fecha_movimiento`, `orden_produccion_id`) VALUES
(1, 'insumo', 1, NULL, NULL, 'entrada', 200.00, 'manual', '', '2026-04-07 17:55:43', NULL),
(2, 'insumo', 1, NULL, NULL, 'entrada', 10.00, 'manual', '', '2026-04-07 18:11:49', NULL),
(3, 'insumo', 1, NULL, NULL, 'entrada', 10.00, 'manual', '', '2026-04-07 18:13:16', NULL),
(4, 'insumo', 1, NULL, NULL, 'entrada', 10.00, 'manual', '', '2026-04-07 18:15:24', NULL),
(5, 'insumo', 1, NULL, NULL, 'salida', 140.00, 'manual', '', '2026-04-07 18:15:47', NULL),
(6, 'insumo', 1, NULL, NULL, 'entrada', 9.00, 'manual', '', '2026-04-07 18:20:13', NULL),
(7, 'insumo', 1, NULL, NULL, 'entrada', 5.00, 'manual', '', '2026-04-07 18:20:40', NULL),
(8, 'insumo', 1, NULL, NULL, 'salida', 4.00, 'manual', '', '2026-04-07 18:28:14', NULL),
(9, 'insumo', 1, NULL, NULL, 'salida', 100.00, 'manual', 'Descuento por creación de orden de producción #1', '2026-04-07 18:31:01', 1),
(10, 'producto', NULL, NULL, 1, 'entrada', 10.00, 'manual', 'Entrada por creación de orden de producción #1', '2026-04-07 18:31:01', 1),
(11, 'producto', NULL, NULL, 1, 'salida', 2.00, 'manual', 'Salida por venta #2', '2026-04-07 19:41:32', NULL),
(12, 'producto', NULL, NULL, 1, 'salida', 6.00, 'manual', 'Salida por venta #3', '2026-04-07 20:22:21', NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ordenes_produccion`
--

CREATE TABLE `ordenes_produccion` (
  `id` int(11) NOT NULL,
  `receta_producto_id` int(11) NOT NULL,
  `cantidad_a_producir` decimal(10,2) NOT NULL,
  `fecha_inicio` date DEFAULT NULL,
  `fecha_fin` date DEFAULT NULL,
  `estado` enum('pendiente','en_proceso','finalizado','cancelado') DEFAULT 'pendiente',
  `observaciones` text DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `usuario_id` int(11) DEFAULT NULL,
  `tasa_cambiaria_id` int(11) DEFAULT NULL COMMENT 'Tasa usada al crear la orden'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ordenes_produccion`
--

INSERT INTO `ordenes_produccion` (`id`, `receta_producto_id`, `cantidad_a_producir`, `fecha_inicio`, `fecha_fin`, `estado`, `observaciones`, `creado_en`, `usuario_id`, `tasa_cambiaria_id`) VALUES
(1, 1, 10.00, '2026-04-08', '2026-04-21', 'pendiente', '', '2026-04-07 18:31:01', NULL, NULL);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presupuestos`
--

CREATE TABLE `presupuestos` (
  `id_presupuesto` int(11) NOT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `codigo_presupuesto` varchar(20) DEFAULT NULL,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `total` decimal(10,2) DEFAULT NULL,
  `status` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `presupuestos`
--

INSERT INTO `presupuestos` (`id_presupuesto`, `id_cliente`, `codigo_presupuesto`, `fecha_creacion`, `total`, `status`) VALUES
(1, 2, 'PRE-0001', '2026-04-07 15:10:57', 260.00, 2);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `presupuesto_detalles`
--

CREATE TABLE `presupuesto_detalles` (
  `id_detalle` int(11) NOT NULL,
  `id_presupuesto` int(11) DEFAULT NULL,
  `id_producto` int(11) NOT NULL,
  `cantidad` int(11) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT NULL,
  `subtotal` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `presupuesto_detalles`
--

INSERT INTO `presupuesto_detalles` (`id_detalle`, `id_presupuesto`, `id_producto`, `cantidad`, `precio_unitario`, `subtotal`) VALUES
(1, 1, 1, 2, 130.00, 260.00);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `productos`
--

CREATE TABLE `productos` (
  `id` int(11) NOT NULL,
  `nombre` varchar(255) NOT NULL,
  `categoria` varchar(100) DEFAULT NULL,
  `tipo_genero` varchar(50) DEFAULT NULL,
  `descripcion` text DEFAULT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `fecha_creacion` datetime DEFAULT current_timestamp(),
  `activo` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `productos`
--

INSERT INTO `productos` (`id`, `nombre`, `categoria`, `tipo_genero`, `descripcion`, `imagen`, `precio_unitario`, `fecha_creacion`, `activo`) VALUES
(1, 'Pantalon corporativo', 'Adulto', 'Caballero', 'asdads', 'producto_1775584089_69d543599cea4.png', 0.00, '2026-04-07 13:48:09', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `proveedores`
--

CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `proveedores`
--

INSERT INTO `proveedores` (`id`, `nombre`, `telefono`, `email`, `direccion`) VALUES
(1, 'empresas polarrr', '04243402313', 'leonardojgc2002@gmail.com', 'urbanizacion la floresta, turmero');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `rangos_tallas`
--

CREATE TABLE `rangos_tallas` (
  `id` int(11) NOT NULL,
  `nombre_rango` varchar(50) NOT NULL,
  `tallas_desde` int(11) NOT NULL,
  `tallas_hasta` int(11) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `rangos_tallas`
--

INSERT INTO `rangos_tallas` (`id`, `nombre_rango`, `tallas_desde`, `tallas_hasta`, `descripcion`) VALUES
(1, 'Talla Única', 1, 1, 'Una sola talla'),
(2, 'Niños', 2, 14, 'Tallas infantiles'),
(3, 'XS', 32, 34, 'Extra pequeño'),
(4, 'S', 36, 38, 'Pequeño'),
(5, 'M', 40, 42, 'Mediano'),
(6, 'L', 44, 46, 'Grande'),
(7, 'XL', 48, 50, 'Extra grande'),
(8, 'XXL', 52, 54, 'Doble extra grande');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas`
--

CREATE TABLE `recetas` (
  `id` int(11) NOT NULL,
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
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `recetas`
--

INSERT INTO `recetas` (`id`, `producto_id`, `rango_tallas_id`, `tipo_produccion_id`, `almacen_id`, `stock_minimo`, `stock_maximo`, `observaciones`, `precio_total`, `precio_mayor`, `precio_detal`, `porcentaje_ganancia`, `tasa_cambiaria_id`, `creado_en`) VALUES
(1, 1, 6, 1, 1, 10.00, 100.00, 'Receta para clientes', 130.00, 130.00, 130.00, 30.00, 1, '2026-04-07 17:53:05');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `recetas_productos`
--

CREATE TABLE `recetas_productos` (
  `id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `rango_tallas_id` int(11) NOT NULL,
  `tipo_produccion_id` int(11) NOT NULL,
  `cantidad_por_unidad` decimal(8,4) NOT NULL,
  `precio_unitario` decimal(10,2) DEFAULT 0.00,
  `costo_por_unidad` decimal(10,2) DEFAULT 0.00,
  `observaciones` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `recetas_productos`
--

INSERT INTO `recetas_productos` (`id`, `producto_id`, `insumo_id`, `rango_tallas_id`, `tipo_produccion_id`, `cantidad_por_unidad`, `precio_unitario`, `costo_por_unidad`, `observaciones`) VALUES
(1, 1, 1, 6, 1, 10.0000, 0.00, 100.00, 'Receta para clientes');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `roles`
--

INSERT INTO `roles` (`id`, `nombre`) VALUES
(1, 'admin'),
(3, 'cliente'),
(2, 'supervisor');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tasas_cambiarias`
--

CREATE TABLE `tasas_cambiarias` (
  `id` int(11) NOT NULL,
  `tasa` decimal(18,8) NOT NULL COMMENT 'Tasa USD/BS',
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora del registro',
  `origen` enum('bcv','manual') NOT NULL DEFAULT 'manual',
  `usuario_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro de tasas cambiarias por hora';

--
-- Volcado de datos para la tabla `tasas_cambiarias`
--

INSERT INTO `tasas_cambiarias` (`id`, `tasa`, `fecha_hora`, `origen`, `usuario_id`) VALUES
(1, 450.00000000, '2026-04-07 18:56:14', 'manual', 1);

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `tipos_produccion`
--

CREATE TABLE `tipos_produccion` (
  `id` int(11) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `tipos_produccion`
--

INSERT INTO `tipos_produccion` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Detal', 'Producción minorista'),
(2, 'Mayor', 'Producción mayorista');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `correo`, `role_id`, `createdAt`) VALUES
(1, 'Leonardo', '$2y$10$MeH53cwfpUY57GYn5yriCuZfcUPWaMffgQK7.5g4fa19ygETgydem', 'leonardojgc2002@gmail.com', 1, '2026-04-07 16:54:04');

-- --------------------------------------------------------

--
-- Estructura de tabla para la tabla `ventas`
--

CREATE TABLE `ventas` (
  `id` int(11) NOT NULL,
  `cotizacion_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','entregado','cancelado') DEFAULT 'pendiente',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `tasa_cambiaria_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Volcado de datos para la tabla `ventas`
--

INSERT INTO `ventas` (`id`, `cotizacion_id`, `cliente_id`, `fecha`, `numero_factura`, `total`, `estado`, `orden_produccion_id`, `creado_en`, `tasa_cambiaria_id`) VALUES
(2, 1, 2, '2026-04-07', '1', 260.00, 'entregado', NULL, '2026-04-07 19:41:32', 1),
(3, NULL, 2, '2026-04-07', '2', 780.00, 'entregado', NULL, '2026-04-07 20:22:21', 1);

--
-- Índices para tablas volcadas
--

--
-- Indices de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  ADD PRIMARY KEY (`id_evento`),
  ADD KEY `idx_auditoria_fecha` (`fecha_hora`),
  ADD KEY `idx_auditoria_user` (`id_usuario`),
  ADD KEY `idx_auditoria_cliente` (`id_cliente`);

--
-- Indices de la tabla `categorias`
--
ALTER TABLE `categorias`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_clientes_rol` (`role_id`);

--
-- Indices de la tabla `compras`
--
ALTER TABLE `compras`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  ADD KEY `proveedor_id` (`proveedor_id`),
  ADD KEY `orden_produccion_id` (`orden_produccion_id`);

--
-- Indices de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD PRIMARY KEY (`id_cotizacion`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_cotizacion` (`id_cotizacion`),
  ADD KEY `id_receta` (`id_receta`);

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
  ADD KEY `proveedor` (`proveedor_id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);

--
-- Indices de la tabla `inventario`
--
ALTER TABLE `inventario`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tipo_item` (`tipo_item`,`tipo_item_id`),
  ADD KEY `idx_tipo_item` (`tipo_item`),
  ADD KEY `idx_tipo_item_id` (`tipo_item_id`),
  ADD KEY `idx_orden_produccion` (`orden_produccion_id`);

--
-- Indices de la tabla `inventario_detalle`
--
ALTER TABLE `inventario_detalle`
  ADD PRIMARY KEY (`id`),
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
  ADD PRIMARY KEY (`id`),
  ADD KEY `receta_producto_id` (`receta_producto_id`),
  ADD KEY `fk_ordenes_produccion_usuario` (`usuario_id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);

--
-- Indices de la tabla `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD PRIMARY KEY (`id_presupuesto`),
  ADD UNIQUE KEY `codigo_presupuesto` (`codigo_presupuesto`),
  ADD KEY `id_cliente` (`id_cliente`);

--
-- Indices de la tabla `presupuesto_detalles`
--
ALTER TABLE `presupuesto_detalles`
  ADD PRIMARY KEY (`id_detalle`),
  ADD KEY `id_presupuesto` (`id_presupuesto`),
  ADD KEY `id_producto` (`id_producto`);

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
-- Indices de la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_receta` (`producto_id`,`rango_tallas_id`,`tipo_produccion_id`),
  ADD KEY `rango_tallas_id` (`rango_tallas_id`),
  ADD KEY `tipo_produccion_id` (`tipo_produccion_id`),
  ADD KEY `almacen_id` (`almacen_id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`);

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
-- Indices de la tabla `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `nombre` (`nombre`);

--
-- Indices de la tabla `tasas_cambiarias`
--
ALTER TABLE `tasas_cambiarias`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fecha_hora` (`fecha_hora`),
  ADD KEY `usuario_id` (`usuario_id`);

--
-- Indices de la tabla `tipos_produccion`
--
ALTER TABLE `tipos_produccion`
  ADD PRIMARY KEY (`id`);

--
-- Indices de la tabla `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD KEY `role_id` (`role_id`);

--
-- Indices de la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  ADD KEY `cotizacion_id` (`cotizacion_id`),
  ADD KEY `cliente_id` (`cliente_id`),
  ADD KEY `orden_produccion_id` (`orden_produccion_id`);

--
-- AUTO_INCREMENT de las tablas volcadas
--

--
-- AUTO_INCREMENT de la tabla `almacenes`
--
ALTER TABLE `almacenes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `auditoria`
--
ALTER TABLE `auditoria`
  MODIFY `id_evento` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `categorias`
--
ALTER TABLE `categorias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `clientes`
--
ALTER TABLE `clientes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `compras`
--
ALTER TABLE `compras`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  MODIFY `id_cotizacion` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `detalle_compra`
--
ALTER TABLE `detalle_compra`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de la tabla `detalle_venta`
--
ALTER TABLE `detalle_venta`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `insumos`
--
ALTER TABLE `insumos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `inventario`
--
ALTER TABLE `inventario`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `inventario_detalle`
--
ALTER TABLE `inventario_detalle`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT de la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `presupuestos`
--
ALTER TABLE `presupuestos`
  MODIFY `id_presupuesto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `presupuesto_detalles`
--
ALTER TABLE `presupuesto_detalles`
  MODIFY `id_detalle` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `productos`
--
ALTER TABLE `productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `proveedores`
--
ALTER TABLE `proveedores`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `rangos_tallas`
--
ALTER TABLE `rangos_tallas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT de la tabla `recetas`
--
ALTER TABLE `recetas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `recetas_productos`
--
ALTER TABLE `recetas_productos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT de la tabla `tasas_cambiarias`
--
ALTER TABLE `tasas_cambiarias`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `tipos_produccion`
--
ALTER TABLE `tipos_produccion`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de la tabla `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de la tabla `ventas`
--
ALTER TABLE `ventas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- Restricciones para tablas volcadas
--

--
-- Filtros para la tabla `clientes`
--
ALTER TABLE `clientes`
  ADD CONSTRAINT `fk_clientes_rol` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `compras`
--
ALTER TABLE `compras`
  ADD CONSTRAINT `fk_compras_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compras_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_compras_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `cotizaciones`
--
ALTER TABLE `cotizaciones`
  ADD CONSTRAINT `fk_cotizaciones_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `cotizacion_detalles`
--
ALTER TABLE `cotizacion_detalles`
  ADD CONSTRAINT `cotizacion_detalles_ibfk_1` FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE CASCADE,
  ADD CONSTRAINT `cotizacion_detalles_ibfk_2` FOREIGN KEY (`id_receta`) REFERENCES `recetas` (`id`);

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
  ADD CONSTRAINT `fk_inventario_detalle_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventario_detalle_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_inventario_detalle_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_inventario_detalle_receta` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `ordenes_produccion`
--
ALTER TABLE `ordenes_produccion`
  ADD CONSTRAINT `fk_ordenes_produccion_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordenes_produccion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `ordenes_produccion_ibfk_1` FOREIGN KEY (`receta_producto_id`) REFERENCES `recetas_productos` (`id`);

--
-- Filtros para la tabla `presupuestos`
--
ALTER TABLE `presupuestos`
  ADD CONSTRAINT `fk_presupuestos_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Filtros para la tabla `presupuesto_detalles`
--
ALTER TABLE `presupuesto_detalles`
  ADD CONSTRAINT `fk_presupuesto_detalles_presupuesto` FOREIGN KEY (`id_presupuesto`) REFERENCES `presupuestos` (`id_presupuesto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_presupuesto_detalles_producto` FOREIGN KEY (`id_producto`) REFERENCES `recetas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Filtros para la tabla `recetas`
--
ALTER TABLE `recetas`
  ADD CONSTRAINT `fk_recetas_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_recetas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `recetas_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recetas_ibfk_2` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `recetas_ibfk_3` FOREIGN KEY (`tipo_produccion_id`) REFERENCES `tipos_produccion` (`id`) ON DELETE CASCADE;

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
  ADD CONSTRAINT `fk_users_rol` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE;

--
-- Filtros para la tabla `ventas`
--
ALTER TABLE `ventas`
  ADD CONSTRAINT `fk_venta_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ventas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
