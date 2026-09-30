<?php
require_once __DIR__ . '/orden_numero.php';

/**
 * Gestión de unidades individuales con identificadores únicos para órdenes de producción
 * y tablas asociadas al proceso de devoluciones.
 */
class OrdenProduccionUnidades
{
    public static function asegurarTablas(mysqli $conn): void
    {
        static $hecho = false;
        if ($hecho) {
            return;
        }
        $hecho = true;

        // 1. Tabla de unidades individuales producidas
        $conn->query("
            CREATE TABLE IF NOT EXISTS `ordenes_produccion_unidades` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `orden_produccion_id` INT(11) NOT NULL,
                `receta_id` INT(11) NOT NULL,
                `talla_id` INT(11) DEFAULT NULL,
                `numero_identificador` VARCHAR(60) NOT NULL,
                `numero_secuencia` INT(11) NOT NULL,
                `estado` ENUM('disponible','vendido','devuelto','en_revision','baja') NOT NULL DEFAULT 'disponible',
                `fecha_creacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `observaciones` TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_numero_identificador` (`numero_identificador`),
                KEY `idx_orden_id` (`orden_produccion_id`),
                KEY `idx_receta_id` (`receta_id`),
                KEY `idx_estado` (`estado`),
                CONSTRAINT `fk_op_unidades_orden` FOREIGN KEY (`orden_produccion_id`) 
                    REFERENCES `ordenes_produccion` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        // 2. Tabla de devoluciones (cabecera)
        $conn->query("
            CREATE TABLE IF NOT EXISTS `devoluciones` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `codigo_devolucion` VARCHAR(50) NOT NULL,
                `fecha` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `cliente_id` INT(11) DEFAULT NULL,
                `motivo` TEXT NOT NULL,
                `descripcion_motivo` TEXT DEFAULT NULL,
                `accion_inventario` VARCHAR(50) NOT NULL DEFAULT 'reingresar_stock',
                `usuario_id` INT(11) DEFAULT NULL,
                `observaciones` TEXT DEFAULT NULL,
                `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_codigo_devolucion` (`codigo_devolucion`),
                KEY `idx_cliente_id` (`cliente_id`),
                KEY `idx_fecha` (`fecha`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");

        @$conn->query("ALTER TABLE `devoluciones` MODIFY COLUMN `motivo` TEXT NOT NULL");
        @$conn->query("ALTER TABLE `devoluciones` MODIFY COLUMN `accion_inventario` VARCHAR(50) NOT NULL DEFAULT 'reingresar_stock'");

        // 3. Tabla detalle de devoluciones
        $conn->query("
            CREATE TABLE IF NOT EXISTS `devoluciones_detalle` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `devolucion_id` INT(11) NOT NULL,
                `unidad_id` INT(11) NOT NULL,
                `orden_produccion_id` INT(11) NOT NULL,
                `estado_unidad_posterior` ENUM('devuelto_stock','en_reparacion','descartado') NOT NULL DEFAULT 'devuelto_stock',
                `observaciones` TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_devolucion_id` (`devolucion_id`),
                KEY `idx_unidad_id` (`unidad_id`),
                KEY `idx_op_id` (`orden_produccion_id`),
                CONSTRAINT `fk_dev_detalle_devolucion` FOREIGN KEY (`devolucion_id`) 
                    REFERENCES `devoluciones` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT `fk_dev_detalle_unidad` FOREIGN KEY (`unidad_id`) 
                    REFERENCES `ordenes_produccion_unidades` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    }

    /**
     * Genera el formato de identificador único para una unidad:
     * Formato: OP{ordenId}-U{secuencia con padding} (Ej: OP5-U001, OP5-U002)
     */
    public static function generarCodigoIdentificador(int $ordenId, int $secuencia): string
    {
        return sprintf("OP%d-U%03d", $ordenId, $secuencia);
    }

    /**
     * Genera el código para una nueva devolución (Ej: DEV-1-AGOST-26 o DEV-001)
     */
    public static function generarCodigoDevolucion(int $devolucionId, ?string $fecha = null): string
    {
        $nOrden = numero_orden_produccion($devolucionId, $fecha);
        return $nOrden !== '' ? 'DEV-' . $nOrden : sprintf('DEV-%04d', $devolucionId);
    }

    /**
     * Sincroniza / crea las unidades de una orden de producción.
     * Si la orden especifica $cantidad unidades, genera los registros que falten.
     */
    public static function sincronizarUnidadesOrden(
        mysqli $conn,
        int $ordenId,
        int $recetaId,
        ?int $tallaId,
        float $cantidad
    ): int {
        self::asegurarTablas($conn);

        $cantEntera = (int) ceil($cantidad);
        if ($cantEntera <= 0 || $ordenId <= 0 || $recetaId <= 0) {
            return 0;
        }

        // Consultar cuántas unidades ya existen para esta orden
        $st = $conn->prepare("SELECT COUNT(*) AS total, COALESCE(MAX(numero_secuencia), 0) AS max_seq FROM ordenes_produccion_unidades WHERE orden_produccion_id = ?");
        $st->bind_param('i', $ordenId);
        $st->execute();
        $res = $st->get_result()->fetch_assoc();
        $st->close();

        $totalExistentes = (int) ($res['total'] ?? 0);
        $maxSeq = (int) ($res['max_seq'] ?? 0);

        if ($totalExistentes >= $cantEntera) {
            return 0; // Ya están generadas
        }

        $creadas = 0;
        $stmtInsert = $conn->prepare("
            INSERT IGNORE INTO ordenes_produccion_unidades 
                (orden_produccion_id, receta_id, talla_id, numero_identificador, numero_secuencia, estado)
            VALUES (?, ?, ?, ?, ?, 'disponible')
        ");

        for ($seq = $maxSeq + 1; $seq <= $cantEntera; $seq++) {
            $codigo = self::generarCodigoIdentificador($ordenId, $seq);
            $stmtInsert->bind_param('iiisi', $ordenId, $recetaId, $tallaId, $codigo, $seq);
            if ($stmtInsert->execute() && $conn->affected_rows > 0) {
                $creadas++;
            }
        }
        $stmtInsert->close();

        return $creadas;
    }

    /**
     * Retrocompatibilidad: Sincroniza unidades de todas las órdenes que no tengan registros aún.
     */
    public static function sincronizarTodasLasOrdenes(mysqli $conn): void
    {
        self::asegurarTablas($conn);

        $sql = "
            SELECT op.id, op.cantidad_a_producir, op.talla_id,
                   COALESCE(r.id, 0) AS receta_id
            FROM ordenes_produccion op
            INNER JOIN recetas_productos rp ON rp.id = op.receta_producto_id
            LEFT JOIN recetas r ON r.producto_id = rp.producto_id 
                AND r.rango_tallas_id = rp.rango_tallas_id 
                AND r.tipo_produccion_id = rp.tipo_produccion_id
            LEFT JOIN ordenes_produccion_unidades u ON u.orden_produccion_id = op.id
            WHERE u.id IS NULL
            GROUP BY op.id
        ";
        $res = $conn->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $opId = (int) $row['id'];
                $recId = (int) $row['receta_id'];
                $tallaId = !empty($row['talla_id']) ? (int) $row['talla_id'] : null;
                $cant = (float) $row['cantidad_a_producir'];
                if ($recId > 0 && $cant > 0) {
                    self::sincronizarUnidadesOrden($conn, $opId, $recId, $tallaId, $cant);
                }
            }
        }
    }

    /**
     * Obtiene el listado de unidades de una orden de producción.
     */
    public static function obtenerUnidadesPorOrden(mysqli $conn, int $ordenId): array
    {
        self::asegurarTablas($conn);

        $sql = "
            SELECT u.id, u.numero_identificador, u.numero_secuencia, u.estado, u.fecha_creacion, u.observaciones,
                   COALESCE(p.nombre, 'Producto') AS producto_nombre,
                   COALESCE(t.nombre, 'Única') AS talla_nombre,
                   op.id AS orden_id,
                   op.creado_en AS orden_fecha
            FROM ordenes_produccion_unidades u
            INNER JOIN ordenes_produccion op ON op.id = u.orden_produccion_id
            LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
            LEFT JOIN productos p ON p.id = rp.producto_id
            LEFT JOIN tallas t ON t.id = u.talla_id
            WHERE u.orden_produccion_id = ?
            ORDER BY u.numero_secuencia ASC
        ";
        $st = $conn->prepare($sql);
        $st->bind_param('i', $ordenId);
        $st->execute();
        $res = $st->get_result();
        $unidades = [];
        while ($row = $res->fetch_assoc()) {
            $row['numero_orden'] = numero_orden_produccion((int)$row['orden_id'], $row['orden_fecha']);
            $unidades[] = $row;
        }
        $st->close();

        return $unidades;
    }

    /**
     * Busca una unidad por su número identificador (o ID) con toda su información trazable.
     */
    public static function buscarUnidadPorIdentificador(mysqli $conn, string $identificador): ?array
    {
        self::asegurarTablas($conn);
        self::sincronizarTodasLasOrdenes($conn);

        $identificador = trim($identificador);
        if ($identificador === '') {
            return null;
        }

        $sql = "
            SELECT u.id AS unidad_id, u.numero_identificador, u.numero_secuencia, u.estado AS estado_unidad,
                   u.orden_produccion_id, u.receta_id, u.talla_id, u.fecha_creacion AS unidad_fecha_creacion,
                   p.id AS producto_id, COALESCE(p.nombre, 'Producto') AS producto_nombre, p.categoria,
                   COALESCE(t.nombre, 'Sin talla') AS talla_nombre,
                   op.id AS orden_id, op.estado AS estado_orden, op.creado_en AS orden_creado_en,
                   op.fecha_inicio AS orden_fecha_inicio, op.fecha_fin AS orden_fecha_fin,
                   op.venta_id,
                   v.numero_factura AS venta_factura,
                   c.id AS cliente_id, c.nombre AS cliente_nombre, c.numero_documento AS cliente_documento
            FROM ordenes_produccion_unidades u
            INNER JOIN ordenes_produccion op ON op.id = u.orden_produccion_id
            LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
            LEFT JOIN productos p ON p.id = rp.producto_id
            LEFT JOIN tallas t ON t.id = u.talla_id
            LEFT JOIN ventas v ON v.id = op.venta_id
            LEFT JOIN clientes c ON c.id = v.cliente_id
            WHERE u.numero_identificador = ? OR u.numero_identificador LIKE ?
            LIMIT 1
        ";
        $st = $conn->prepare($sql);
        $likeParam = '%' . $identificador . '%';
        $st->bind_param('ss', $identificador, $likeParam);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row) {
            $row['numero_orden'] = numero_orden_produccion((int)$row['orden_id'], $row['orden_creado_en']);
        }

        return $row;
    }
}
