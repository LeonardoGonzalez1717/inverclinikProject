-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: db_inverclinik
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `almacenes`
--

DROP TABLE IF EXISTS `almacenes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `almacenes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `codigo` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `almacenes`
--

LOCK TABLES `almacenes` WRITE;
/*!40000 ALTER TABLE `almacenes` DISABLE KEYS */;
INSERT INTO `almacenes` VALUES (1,'Principal','ALM01',1),(2,'Almacen b0','ALM-002',1);
/*!40000 ALTER TABLE `almacenes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `auditoria`
--

DROP TABLE IF EXISTS `auditoria`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auditoria` (
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `auditoria`
--

LOCK TABLES `auditoria` WRITE;
/*!40000 ALTER TABLE `auditoria` DISABLE KEYS */;
INSERT INTO `auditoria` VALUES (1,1,NULL,'Leonardo','Sesión','Inicio de sesión exitoso (usuario interno). Correo: leonardojgc2002@gmail.com. Rol: admin','2026-04-07 16:11:40','::1'),(2,1,NULL,'Leonardo','Perfil','Perfil actualizado y contraseña modificada (usuario interno id 1).','2026-04-07 16:16:12','::1'),(3,1,NULL,'Leonardo','Ventas','Venta #3 registrada. Cliente #2. Factura: 2. Total: 780.','2026-04-07 16:22:21','::1'),(4,NULL,NULL,'(Intento fallido)','Sesión','Intento fallido: contraseña incorrecta. Correo: leonardojgc2002@gmail.com','2026-04-14 12:55:14','::1'),(5,NULL,NULL,'(Intento fallido)','Sesión','Intento fallido: contraseña incorrecta. Correo: leonardojgc2002@gmail.com','2026-04-14 12:55:14','::1'),(6,NULL,NULL,'(Intento fallido)','Sesión','Intento fallido: contraseña incorrecta. Correo: leonardojgc2002@gmail.com','2026-04-14 12:55:15','::1'),(7,NULL,NULL,'(Intento fallido)','Sesión','Intento fallido: contraseña incorrecta. Correo: leonardojgc2002@gmail.com','2026-04-14 12:55:15','::1'),(8,1,NULL,'Leonardo','Sesión','Inicio de sesión exitoso (usuario interno). Correo: leonardojgc2002@gmail.com. Rol: admin','2026-04-14 12:55:20','::1'),(9,NULL,NULL,'(Intento fallido)','Sesión','Intento fallido: contraseña incorrecta. Correo: leonardojgc2002@gmail.com','2026-04-14 13:10:54','::1'),(10,1,NULL,'Leonardo','Sesión','Inicio de sesión exitoso (usuario interno). Correo: leonardojgc2002@gmail.com. Rol: admin','2026-04-14 13:10:57','::1'),(11,NULL,NULL,'Tarea programada','Tasas cambiarias','Ejecucion programada BCV. Fuera de ventana programada (08:30 o 13:30 hora Venezuela).','2026-04-14 13:24:14',NULL),(12,NULL,2,'Leonardo','Sesión','Inicio de sesión exitoso (portal cliente). Email: leitogonza1717@gmail.com. Cliente id: 2','2026-04-14 13:31:20','::1'),(13,NULL,NULL,'(Intento fallido)','Sesión','Intento fallido: contraseña incorrecta. Correo: leonardojgc2002@gmail.com','2026-04-14 14:00:33','::1'),(14,1,NULL,'Leonardo','Sesión','Inicio de sesión exitoso (usuario interno). Correo: leonardojgc2002@gmail.com. Rol: admin','2026-04-14 14:00:36','::1'),(15,1,NULL,'Leonardo','Cotización (cliente)','Cotización creada: COT-MAN-201328 (id 2). Total: 13000.','2026-04-14 14:13:28','::1'),(16,1,NULL,'Leonardo','Cotización (cliente)','Cotización creada: COT-MAN-201328 (id 3). Total: 13000.','2026-04-14 14:13:28','::1'),(17,1,NULL,'Leonardo','Cotización (cliente)','Cotización creada: COT-MAN-201328 (id 4). Total: 13000.','2026-04-14 14:13:28','::1'),(18,1,NULL,'Leonardo','Ventas','Venta #4 registrada. Cliente #2. Factura: 3. Total: 260. Cotización #1.','2026-04-14 14:36:49','::1'),(19,1,NULL,'Leonardo','Sesión','Inicio de sesión exitoso (usuario interno). Correo: leonardojgc2002@gmail.com. Rol: admin','2026-04-16 12:34:53','::1'),(20,1,NULL,'Leonardo','Ventas','Órdenes de producción generadas por falta de stock al registrar venta (venta no guardada). IDs: 2','2026-04-16 12:37:05','::1'),(21,1,NULL,'Leonardo','Sesión','Inicio de sesión exitoso (usuario interno). Correo: leonardojgc2002@gmail.com. Rol: admin','2026-04-16 13:30:49','::1'),(22,NULL,NULL,'Tarea programada','Tasas cambiarias','Ejecucion programada BCV. Tasa BCV actualizada automaticamente. Tasa=479.7775','2026-04-16 13:35:12',NULL),(23,1,NULL,'Leonardo','Almacenes','Almacén actualizado: id 2 — Almacen b1 (ALM-002)','2026-04-16 13:43:24','::1'),(24,1,NULL,'Leonardo','Almacenes','Almacén actualizado: id 2 — Almacen b0 (ALM-002)','2026-04-16 13:44:30','::1'),(25,1,NULL,'Leonardo','Categorías','Categoría actualizada: id 3 — Adultos','2026-04-16 13:46:32','::1');
/*!40000 ALTER TABLE `auditoria` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `categorias`
--

DROP TABLE IF EXISTS `categorias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categorias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `categorias`
--

LOCK TABLES `categorias` WRITE;
/*!40000 ALTER TABLE `categorias` DISABLE KEYS */;
INSERT INTO `categorias` VALUES (1,'Infantil','asd'),(3,'Adultos','ss');
/*!40000 ALTER TABLE `categorias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `clientes`
--

DROP TABLE IF EXISTS `clientes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `clientes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo_documento` varchar(20) DEFAULT NULL,
  `numero_documento` varchar(30) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  `role_id` int(11) NOT NULL DEFAULT 3,
  PRIMARY KEY (`id`),
  KEY `fk_clientes_rol` (`role_id`),
  CONSTRAINT `fk_clientes_rol` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `clientes`
--

LOCK TABLES `clientes` WRITE;
/*!40000 ALTER TABLE `clientes` DISABLE KEYS */;
INSERT INTO `clientes` VALUES (2,'Leonardo','V','30611935',NULL,'leitogonza1717@gmail.com','$2y$10$5OKzZllnt0ukWawvIVDakueEEayZTwHWscPN9uQ1ErdqM9SOgGfR6',NULL,3);
/*!40000 ALTER TABLE `clientes` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `compras`
--

DROP TABLE IF EXISTS `compras`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  KEY `proveedor_id` (`proveedor_id`),
  KEY `orden_produccion_id` (`orden_produccion_id`),
  CONSTRAINT `fk_compras_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_compras_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_compras_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `compras`
--

LOCK TABLES `compras` WRITE;
/*!40000 ALTER TABLE `compras` DISABLE KEYS */;
/*!40000 ALTER TABLE `compras` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizacion_detalles`
--

DROP TABLE IF EXISTS `cotizacion_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cotizacion_detalles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_cotizacion` int(11) NOT NULL,
  `id_receta` int(11) NOT NULL,
  `id_talla` int(11) NOT NULL,
  `id_personalizacion` int(11) DEFAULT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `notas` text DEFAULT NULL,
  `fecha_creacion` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `id_cotizacion` (`id_cotizacion`),
  KEY `id_receta` (`id_receta`),
  CONSTRAINT `cotizacion_detalles_ibfk_1` FOREIGN KEY (`id_cotizacion`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE CASCADE,
  CONSTRAINT `cotizacion_detalles_ibfk_2` FOREIGN KEY (`id_receta`) REFERENCES `recetas` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizacion_detalles`
--

LOCK TABLES `cotizacion_detalles` WRITE;
/*!40000 ALTER TABLE `cotizacion_detalles` DISABLE KEYS */;
INSERT INTO `cotizacion_detalles` VALUES (1,1,1,6,NULL,2,130.00,260.00,'','2026-04-07 19:22:16'),(2,2,1,6,NULL,100,130.00,13000.00,'','2026-04-14 18:13:28'),(3,3,1,6,NULL,100,130.00,13000.00,'','2026-04-14 18:13:28'),(4,4,1,6,NULL,100,130.00,13000.00,'','2026-04-14 18:13:28');
/*!40000 ALTER TABLE `cotizacion_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `cotizaciones`
--

DROP TABLE IF EXISTS `cotizaciones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cotizaciones` (
  `id_cotizacion` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `codigo_cotizacion` varchar(20) NOT NULL,
  `codigo_presupuesto_origen` varchar(20) DEFAULT NULL,
  `total` decimal(10,2) NOT NULL,
  `status` int(11) DEFAULT 1 COMMENT '1: Enviada, 2: Aprobada/Venta, 3: Rechazada',
  `fecha_registro` timestamp NOT NULL DEFAULT current_timestamp(),
  `comprobante_referencia` varchar(120) DEFAULT NULL,
  `comprobante_archivo` varchar(255) DEFAULT NULL,
  `comprobante_fecha` datetime DEFAULT NULL,
  PRIMARY KEY (`id_cotizacion`),
  KEY `id_cliente` (`id_cliente`),
  CONSTRAINT `fk_cotizaciones_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `cotizaciones`
--

LOCK TABLES `cotizaciones` WRITE;
/*!40000 ALTER TABLE `cotizaciones` DISABLE KEYS */;
INSERT INTO `cotizaciones` VALUES (1,2,'COT-0001','PRE-0001',260.00,2,'2026-04-07 19:22:16','02020202020',NULL,'2026-04-14 13:59:09'),(2,2,'COT-MAN-201328','VENTA DIRECTA',13000.00,1,'2026-04-14 18:13:28',NULL,NULL,NULL),(3,2,'COT-MAN-201328','VENTA DIRECTA',13000.00,1,'2026-04-14 18:13:28',NULL,NULL,NULL),(4,2,'COT-MAN-201328','VENTA DIRECTA',13000.00,1,'2026-04-14 18:13:28',NULL,NULL,NULL);
/*!40000 ALTER TABLE `cotizaciones` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_compra`
--

DROP TABLE IF EXISTS `detalle_compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_compra` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `compra_id` int(11) NOT NULL,
  `insumo_id` int(11) NOT NULL,
  `cantidad` decimal(10,2) NOT NULL,
  `costo_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `costo_unitario`) STORED,
  PRIMARY KEY (`id`),
  KEY `compra_id` (`compra_id`),
  KEY `insumo_id` (`insumo_id`),
  CONSTRAINT `fk_detalle_compra_compra` FOREIGN KEY (`compra_id`) REFERENCES `compras` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_compra_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_compra`
--

LOCK TABLES `detalle_compra` WRITE;
/*!40000 ALTER TABLE `detalle_compra` DISABLE KEYS */;
/*!40000 ALTER TABLE `detalle_compra` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detalle_venta`
--

DROP TABLE IF EXISTS `detalle_venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `detalle_venta` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `venta_id` int(11) NOT NULL,
  `producto_id` int(11) NOT NULL COMMENT 'ID de la Receta (recetas.id)',
  `cantidad` decimal(10,2) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL,
  `subtotal` decimal(12,2) GENERATED ALWAYS AS (`cantidad` * `precio_unitario`) STORED,
  PRIMARY KEY (`id`),
  KEY `venta_id` (`venta_id`),
  KEY `producto_id` (`producto_id`),
  CONSTRAINT `fk_detalle_venta_receta` FOREIGN KEY (`producto_id`) REFERENCES `recetas` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_venta_venta` FOREIGN KEY (`venta_id`) REFERENCES `ventas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detalle_venta`
--

LOCK TABLES `detalle_venta` WRITE;
/*!40000 ALTER TABLE `detalle_venta` DISABLE KEYS */;
INSERT INTO `detalle_venta` VALUES (3,3,1,6.00,130.00,780.00),(4,4,1,2.00,130.00,260.00);
/*!40000 ALTER TABLE `detalle_venta` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `insumos`
--

DROP TABLE IF EXISTS `insumos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `insumos` (
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
  PRIMARY KEY (`id`),
  KEY `proveedor` (`proveedor_id`),
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  CONSTRAINT `fk_insumos_proveedor` FOREIGN KEY (`proveedor_id`) REFERENCES `proveedores` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_insumos_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `insumos`
--

LOCK TABLES `insumos` WRITE;
/*!40000 ALTER TABLE `insumos` DISABLE KEYS */;
INSERT INTO `insumos` VALUES (1,'Tela para jeans','metro',10.00,10.00,100.00,1,1,NULL,1,1),(2,'Algodon','metro',10.00,1.00,100.00,2,1,NULL,1,1),(3,'Botones','unidad',10.00,1.00,10.00,2,1,NULL,1,1),(4,'Botones especiales','unidad',10.00,1.00,10.00,1,1,NULL,1,1);
/*!40000 ALTER TABLE `insumos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventario`
--

DROP TABLE IF EXISTS `inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario` (
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
  KEY `idx_orden_produccion` (`orden_produccion_id`),
  CONSTRAINT `fk_inventario_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventario`
--

LOCK TABLES `inventario` WRITE;
/*!40000 ALTER TABLE `inventario` DISABLE KEYS */;
INSERT INTO `inventario` VALUES (1,'insumo',1,0.00,'manual','2026-04-07 18:31:01',1),(10,'producto',1,0.00,'orden_produccion','2026-04-14 18:36:49',1);
/*!40000 ALTER TABLE `inventario` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `inventario_detalle`
--

DROP TABLE IF EXISTS `inventario_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `inventario_detalle` (
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
  PRIMARY KEY (`id`),
  KEY `idx_tipo_item` (`tipo_item`),
  KEY `idx_insumo_id` (`insumo_id`),
  KEY `idx_almacen_id` (`almacen_id`),
  KEY `idx_receta_id` (`receta_id`),
  KEY `idx_fecha` (`fecha_movimiento`),
  KEY `idx_tipo` (`tipo`),
  KEY `idx_orden_produccion` (`orden_produccion_id`),
  CONSTRAINT `fk_inventario_detalle_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_inventario_detalle_insumo` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_inventario_detalle_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inventario_detalle_receta` FOREIGN KEY (`receta_id`) REFERENCES `recetas` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `inventario_detalle`
--

LOCK TABLES `inventario_detalle` WRITE;
/*!40000 ALTER TABLE `inventario_detalle` DISABLE KEYS */;
INSERT INTO `inventario_detalle` VALUES (1,'insumo',1,NULL,NULL,'entrada',200.00,'manual','','2026-04-07 17:55:43',NULL),(2,'insumo',1,NULL,NULL,'entrada',10.00,'manual','','2026-04-07 18:11:49',NULL),(3,'insumo',1,NULL,NULL,'entrada',10.00,'manual','','2026-04-07 18:13:16',NULL),(4,'insumo',1,NULL,NULL,'entrada',10.00,'manual','','2026-04-07 18:15:24',NULL),(5,'insumo',1,NULL,NULL,'salida',140.00,'manual','','2026-04-07 18:15:47',NULL),(6,'insumo',1,NULL,NULL,'entrada',9.00,'manual','','2026-04-07 18:20:13',NULL),(7,'insumo',1,NULL,NULL,'entrada',5.00,'manual','','2026-04-07 18:20:40',NULL),(8,'insumo',1,NULL,NULL,'salida',4.00,'manual','','2026-04-07 18:28:14',NULL),(9,'insumo',1,NULL,NULL,'salida',100.00,'manual','Descuento por creación de orden de producción #1','2026-04-07 18:31:01',1),(10,'producto',NULL,NULL,1,'entrada',10.00,'manual','Entrada por creación de orden de producción #1','2026-04-07 18:31:01',1),(11,'producto',NULL,NULL,1,'salida',2.00,'manual','Salida por venta #2','2026-04-07 19:41:32',NULL),(12,'producto',NULL,NULL,1,'salida',6.00,'manual','Salida por venta #3','2026-04-07 20:22:21',NULL),(13,'producto',NULL,NULL,1,'salida',2.00,'manual','Salida por venta #4','2026-04-14 18:36:49',NULL);
/*!40000 ALTER TABLE `inventario_detalle` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ordenes_produccion`
--

DROP TABLE IF EXISTS `ordenes_produccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  PRIMARY KEY (`id`),
  KEY `receta_producto_id` (`receta_producto_id`),
  KEY `fk_ordenes_produccion_usuario` (`usuario_id`),
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  CONSTRAINT `fk_ordenes_produccion_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ordenes_produccion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `ordenes_produccion_ibfk_1` FOREIGN KEY (`receta_producto_id`) REFERENCES `recetas_productos` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ordenes_produccion`
--

LOCK TABLES `ordenes_produccion` WRITE;
/*!40000 ALTER TABLE `ordenes_produccion` DISABLE KEYS */;
INSERT INTO `ordenes_produccion` VALUES (1,1,10.00,'2026-04-08','2026-04-21','pendiente','','2026-04-07 18:31:01',NULL,NULL),(2,1,100.00,'2026-04-16',NULL,'pendiente','Orden generada por falta de stock al intentar registrar una venta (fecha ref. 2026-04-16). Cantidad a cubrir: 100,00 u.','2026-04-16 16:37:04',NULL,NULL);
/*!40000 ALTER TABLE `ordenes_produccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuesto_detalles`
--

DROP TABLE IF EXISTS `presupuesto_detalles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuesto_detalles` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuesto_detalles`
--

LOCK TABLES `presupuesto_detalles` WRITE;
/*!40000 ALTER TABLE `presupuesto_detalles` DISABLE KEYS */;
INSERT INTO `presupuesto_detalles` VALUES (1,1,1,2,130.00,260.00);
/*!40000 ALTER TABLE `presupuesto_detalles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `presupuestos`
--

DROP TABLE IF EXISTS `presupuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuestos` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `presupuestos`
--

LOCK TABLES `presupuestos` WRITE;
/*!40000 ALTER TABLE `presupuestos` DISABLE KEYS */;
INSERT INTO `presupuestos` VALUES (1,2,'PRE-0001','2026-04-07 15:10:57',260.00,2);
/*!40000 ALTER TABLE `presupuestos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `productos`
--

DROP TABLE IF EXISTS `productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `productos` (
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `productos`
--

LOCK TABLES `productos` WRITE;
/*!40000 ALTER TABLE `productos` DISABLE KEYS */;
INSERT INTO `productos` VALUES (1,'Pantalon corporativo','Adulto','Caballero','asdads','producto_1775584089_69d543599cea4.png',0.00,'2026-04-07 13:48:09',1);
/*!40000 ALTER TABLE `productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `proveedores`
--

DROP TABLE IF EXISTS `proveedores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `direccion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `proveedores`
--

LOCK TABLES `proveedores` WRITE;
/*!40000 ALTER TABLE `proveedores` DISABLE KEYS */;
INSERT INTO `proveedores` VALUES (1,'empresas polarrr','04243402313','leonardojgc2002@gmail.com','urbanizacion la floresta, turmero');
/*!40000 ALTER TABLE `proveedores` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `rangos_tallas`
--

DROP TABLE IF EXISTS `rangos_tallas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `rangos_tallas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_rango` varchar(50) NOT NULL,
  `tallas_desde` int(11) NOT NULL,
  `tallas_hasta` int(11) NOT NULL,
  `descripcion` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `rangos_tallas`
--

LOCK TABLES `rangos_tallas` WRITE;
/*!40000 ALTER TABLE `rangos_tallas` DISABLE KEYS */;
INSERT INTO `rangos_tallas` VALUES (1,'Talla Única',1,1,'Una sola talla'),(2,'Niños',2,14,'Tallas infantiles'),(3,'XS',32,34,'Extra pequeño'),(4,'S',36,38,'Pequeño'),(5,'M',40,42,'Mediano'),(6,'L',44,46,'Grande'),(7,'XL',48,50,'Extra grande'),(8,'XXL',52,54,'Doble extra grande');
/*!40000 ALTER TABLE `rangos_tallas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recetas`
--

DROP TABLE IF EXISTS `recetas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `recetas` (
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_receta` (`producto_id`,`rango_tallas_id`,`tipo_produccion_id`),
  KEY `rango_tallas_id` (`rango_tallas_id`),
  KEY `tipo_produccion_id` (`tipo_produccion_id`),
  KEY `almacen_id` (`almacen_id`),
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  CONSTRAINT `fk_recetas_almacen` FOREIGN KEY (`almacen_id`) REFERENCES `almacenes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_recetas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `recetas_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recetas_ibfk_2` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recetas_ibfk_3` FOREIGN KEY (`tipo_produccion_id`) REFERENCES `tipos_produccion` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recetas`
--

LOCK TABLES `recetas` WRITE;
/*!40000 ALTER TABLE `recetas` DISABLE KEYS */;
INSERT INTO `recetas` VALUES (1,1,6,1,1,10.00,100.00,'Receta para clientes',130.00,130.00,130.00,30.00,NULL,'2026-04-07 17:53:05');
/*!40000 ALTER TABLE `recetas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `recetas_productos`
--

DROP TABLE IF EXISTS `recetas_productos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_receta` (`producto_id`,`insumo_id`,`rango_tallas_id`,`tipo_produccion_id`),
  KEY `insumo_id` (`insumo_id`),
  KEY `rango_tallas_id` (`rango_tallas_id`),
  KEY `tipo_produccion_id` (`tipo_produccion_id`),
  CONSTRAINT `recetas_productos_ibfk_1` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `recetas_productos_ibfk_2` FOREIGN KEY (`insumo_id`) REFERENCES `insumos` (`id`),
  CONSTRAINT `recetas_productos_ibfk_3` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`),
  CONSTRAINT `recetas_productos_ibfk_4` FOREIGN KEY (`tipo_produccion_id`) REFERENCES `tipos_produccion` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `recetas_productos`
--

LOCK TABLES `recetas_productos` WRITE;
/*!40000 ALTER TABLE `recetas_productos` DISABLE KEYS */;
INSERT INTO `recetas_productos` VALUES (1,1,1,6,1,10.0000,0.00,100.00,'Receta para clientes');
/*!40000 ALTER TABLE `recetas_productos` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `roles`
--

DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `roles`
--

LOCK TABLES `roles` WRITE;
/*!40000 ALTER TABLE `roles` DISABLE KEYS */;
INSERT INTO `roles` VALUES (1,'admin'),(3,'cliente'),(2,'supervisor');
/*!40000 ALTER TABLE `roles` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tasas_cambiarias`
--

DROP TABLE IF EXISTS `tasas_cambiarias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tasas_cambiarias` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tasa` decimal(18,8) NOT NULL COMMENT 'Tasa USD/BS',
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp() COMMENT 'Fecha y hora del registro',
  `origen` enum('bcv','manual') NOT NULL DEFAULT 'manual',
  `usuario_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fecha_hora` (`fecha_hora`),
  KEY `usuario_id` (`usuario_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Registro de tasas cambiarias por hora';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tasas_cambiarias`
--

LOCK TABLES `tasas_cambiarias` WRITE;
/*!40000 ALTER TABLE `tasas_cambiarias` DISABLE KEYS */;
INSERT INTO `tasas_cambiarias` VALUES (4,479.77750000,'2026-04-16 13:35:11','bcv',NULL);
/*!40000 ALTER TABLE `tasas_cambiarias` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `tipos_produccion`
--

DROP TABLE IF EXISTS `tipos_produccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tipos_produccion` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `descripcion` text DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `tipos_produccion`
--

LOCK TABLES `tipos_produccion` WRITE;
/*!40000 ALTER TABLE `tipos_produccion` DISABLE KEYS */;
INSERT INTO `tipos_produccion` VALUES (1,'Detal','Producción minorista'),(2,'Mayor','Producción mayorista');
/*!40000 ALTER TABLE `tipos_produccion` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `correo` varchar(255) NOT NULL,
  `role_id` int(11) NOT NULL DEFAULT 1,
  `createdAt` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `fk_users_rol` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `users`
--

LOCK TABLES `users` WRITE;
/*!40000 ALTER TABLE `users` DISABLE KEYS */;
INSERT INTO `users` VALUES (1,'Leonardo','$2y$10$MeH53cwfpUY57GYn5yriCuZfcUPWaMffgQK7.5g4fa19ygETgydem','leonardojgc2002@gmail.com',1,'2026-04-07 16:54:04');
/*!40000 ALTER TABLE `users` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `ventas`
--

DROP TABLE IF EXISTS `ventas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ventas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `cotizacion_id` int(11) DEFAULT NULL,
  `cliente_id` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `numero_factura` varchar(50) DEFAULT NULL,
  `total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `estado` enum('pendiente','entregado','cancelado','aprobado','por_pagar') DEFAULT 'por_pagar',
  `orden_produccion_id` int(11) DEFAULT NULL,
  `creado_en` timestamp NOT NULL DEFAULT current_timestamp(),
  `tasa_cambiaria_id` int(11) DEFAULT NULL,
  `comprobante_referencia` varchar(120) DEFAULT NULL COMMENT 'Referencia de comprobante de pago',
  PRIMARY KEY (`id`),
  KEY `tasa_cambiaria_id` (`tasa_cambiaria_id`),
  KEY `cotizacion_id` (`cotizacion_id`),
  KEY `cliente_id` (`cliente_id`),
  KEY `orden_produccion_id` (`orden_produccion_id`),
  CONSTRAINT `fk_venta_cotizacion` FOREIGN KEY (`cotizacion_id`) REFERENCES `cotizaciones` (`id_cotizacion`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ventas_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `fk_ventas_orden_produccion` FOREIGN KEY (`orden_produccion_id`) REFERENCES `ordenes_produccion` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_ventas_tasa_cambiaria` FOREIGN KEY (`tasa_cambiaria_id`) REFERENCES `tasas_cambiarias` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `ventas`
--

LOCK TABLES `ventas` WRITE;
/*!40000 ALTER TABLE `ventas` DISABLE KEYS */;
INSERT INTO `ventas` VALUES (3,NULL,2,'2026-04-07','2',780.00,'entregado',NULL,'2026-04-07 20:22:21',NULL,NULL),(4,1,2,'2026-04-14','3',260.00,'aprobado',NULL,'2026-04-14 18:36:49',NULL,'02020202020');
/*!40000 ALTER TABLE `ventas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Dumping events for database 'db_inverclinik'
--

--
-- Dumping routines for database 'db_inverclinik'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-04-16 13:52:22
