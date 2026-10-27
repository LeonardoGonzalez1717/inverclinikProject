<?php
require_once "../connection/connection.php";
require_once "../lib/orden_numero.php";

$id_devolucion = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id_devolucion <= 0) {
    die("ID de devolución no válido.");
}

$sql = "
    SELECT d.id, d.codigo_devolucion, d.fecha, d.motivo, d.descripcion_motivo,
           d.accion_inventario, COALESCE(d.estado, 'pendiente') AS estado,
           d.fecha_inspeccion, d.usuario_inspeccion_id, d.motivo_inspeccion,
           COALESCE(d.tipo_origen, 'orden_produccion') AS tipo_origen,
           d.observaciones, d.creado_en,
           u_reg.username AS usuario_registro_nombre,
           u_insp.username AS inspector_nombre,
           dd.orden_produccion_id, dd.venta_id, dd.detalle_venta_id,
           COALESCE(dd.cantidad, 1.00) AS cantidad,
           COALESCE(p_op.id, p_v.id, dd.producto_id) AS producto_id,
           COALESCE(p_op.nombre, p_v.nombre, p_dd.nombre, 'Producto') AS producto_nombre,
           COALESCE(p_op.categoria, p_v.categoria, p_dd.categoria, '') AS categoria,
           COALESCE(t_op.nombre, rt_v.nombre_rango, 'Única') AS talla_nombre,
           op.creado_en AS orden_creado_en, op.estado AS estado_orden,
           v.numero_factura, v.fecha AS venta_fecha,
           cl.nombre AS cliente_nombre, cl.numero_documento AS cliente_doc
    FROM devoluciones d
    LEFT JOIN devoluciones_detalle dd ON dd.devolucion_id = d.id
    LEFT JOIN ordenes_produccion op ON op.id = dd.orden_produccion_id
    LEFT JOIN recetas_productos rp ON rp.id = op.receta_producto_id
    LEFT JOIN productos p_op ON p_op.id = rp.producto_id
    LEFT JOIN tallas t_op ON t_op.id = op.talla_id
    LEFT JOIN ventas v ON v.id = COALESCE(d.venta_id, dd.venta_id)
    LEFT JOIN clientes cl ON cl.id = v.cliente_id
    LEFT JOIN recetas r_v ON r_v.id = dd.receta_id
    LEFT JOIN productos p_v ON p_v.id = r_v.producto_id
    LEFT JOIN rangos_tallas rt_v ON rt_v.id = r_v.rango_tallas_id
    LEFT JOIN productos p_dd ON p_dd.id = dd.producto_id
    LEFT JOIN users u_reg ON u_reg.id = d.usuario_id
    LEFT JOIN users u_insp ON u_insp.id = d.usuario_inspeccion_id
    WHERE d.id = ?
    LIMIT 1
";

$st = $conn->prepare($sql);
$st->bind_param('i', $id_devolucion);
$st->execute();
$res = $st->get_result();
$d = $res ? $res->fetch_assoc() : null;
$st->close();

if (!$d) {
    die("Devolución no encontrada.");
}

$esVenta = ($d['tipo_origen'] === 'venta' || (!empty($d['venta_id']) && empty($d['orden_produccion_id'])));
$tipoOrigenLabel = $esVenta ? 'Devolución de Venta' : 'Devolución de Orden de Producción';
$origenDoc = '-';
if ($esVenta) {
    $origenDoc = 'Factura: ' . (!empty($d['numero_factura']) ? $d['numero_factura'] : ('Venta #' . ($d['venta_id'] ?? '')));
    if (!empty($d['cliente_nombre'])) {
        $origenDoc .= ' — Cliente: ' . $d['cliente_nombre'];
    }
} else {
    $numOp = !empty($d['orden_produccion_id']) ? numero_orden_produccion((int)$d['orden_produccion_id'], $d['orden_creado_en']) : '-';
    $origenDoc = 'Orden de Producción: ' . $numOp;
}

$cant = (float)($d['cantidad'] ?? 1);
$cantFormatted = (floor($cant) == $cant) ? (int)$cant : number_format($cant, 2);

$estadoTexto = 'Pendiente de Inspección';
if ($d['estado'] === 'aprobado') {
    $estadoTexto = 'Aprobado (+ ' . $cantFormatted . ' unidades reingresadas al stock)';
} elseif ($d['estado'] === 'rechazado') {
    $estadoTexto = 'Rechazado';
}

$fechaDevolucion = !empty($d['fecha']) ? date('d/m/Y h:i A', strtotime($d['fecha'])) : date('d/m/Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Comprobante de Devolución_<?php echo htmlspecialchars($d['codigo_devolucion'] ?? 'DEV'); ?></title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #222;
            margin: 0;
            padding: 20px;
            background-color: #f9f9f9;
        }

        .comprobante-box {
            width: 720px;
            margin: auto;
            background: white;
            padding: 25px 30px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.1);
        }

        .no-print {
            text-align: right;
            margin-bottom: 15px;
        }

        .btn-accion {
            display: inline-block;
            padding: 7px 16px;
            font-size: 13px;
            font-weight: 600;
            border-radius: 4px;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
        }

        .btn-imprimir {
            background-color: #0056b3;
            color: #fff;
        }

        .btn-imprimir:hover {
            background-color: #004085;
        }

        .btn-cerrar {
            background-color: #f1f5f9;
            color: #334155;
            border-color: #cbd5e1;
            margin-right: 8px;
        }

        table.tabla-datos {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            border: 1px solid #000;
        }

        table.tabla-datos th,
        table.tabla-datos td {
            border: 1px solid #000;
            padding: 8px 10px;
            font-size: 13px;
            vertical-align: top;
        }

        table.tabla-datos th {
            background-color: #f5f5f5;
            font-weight: 600;
        }

        .tabla-info {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 10px;
        }

        .tabla-info td {
            padding: 4px 0;
            font-size: 13px;
            border: none !important;
        }

        .seccion-firmas {
            margin-top: 50px;
            width: 100%;
        }

        .seccion-firmas table {
            width: 100%;
            border-collapse: collapse;
            border: none;
        }

        .seccion-firmas td {
            border: none;
            padding: 0 15px;
            text-align: center;
            vertical-align: top;
        }

        .linea-firma {
            border-top: 1px solid #000;
            padding-top: 6px;
            font-size: 12px;
        }

        .footer-empresa {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 12px;
        }

        @media print {
            body {
                background: white;
                padding: 0;
                margin: 0;
            }
            .comprobante-box {
                width: 100%;
                border: none;
                box-shadow: none;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            table.tabla-datos th {
                background-color: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="comprobante-box">
        <div class="no-print">
            <button type="button" class="btn-accion btn-cerrar" onclick="window.close();">Cerrar</button>
            <button type="button" class="btn-accion btn-imprimir" onclick="window.print();">Imprimir Comprobante</button>
        </div>

        <div style="text-align: center;">
            <img src="../assets/img/brand.png" alt="Inverclinik C.A." width="100%" height="110px" style="object-fit: contain;">
            <p style="margin: 3px 0 0 0; text-align: left; font-size: 11px; font-weight: bold;">RIF J-41173381-4</p>
        </div>

        <div style="margin-top: 10px; border-top: 2px solid #000; border-bottom: 2px solid #000; padding: 6px 0;">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="border: none; font-size: 16px; font-weight: bold; text-transform: uppercase;">
                        Comprobante de Devolución
                    </td>
                    <td style="border: none; text-align: right; font-size: 16px; font-weight: bold;">
                        N° <?php echo htmlspecialchars($d['codigo_devolucion'] ?? 'DEV'); ?>
                    </td>
                </tr>
            </table>
        </div>

        <table class="tabla-info">
            <tr>
                <td style="width: 22%;"><strong>Fecha de Registro:</strong></td>
                <td style="width: 38%;"><?php echo htmlspecialchars($fechaDevolucion); ?></td>
                <td style="width: 18%;"><strong>Tipo Origen:</strong></td>
                <td style="width: 22%;"><?php echo htmlspecialchars($tipoOrigenLabel); ?></td>
            </tr>
            <tr>
                <td><strong>Documento Origen:</strong></td>
                <td colspan="3"><?php echo htmlspecialchars($origenDoc); ?></td>
            </tr>
            <?php if (!empty($d['usuario_registro_nombre'])): ?>
            <tr>
                <td><strong>Registrado Por:</strong></td>
                <td colspan="3"><?php echo htmlspecialchars($d['usuario_registro_nombre']); ?></td>
            </tr>
            <?php endif; ?>
        </table>

        <table class="tabla-datos">
            <thead>
                <tr>
                    <th style="width: 45%;">PRODUCTO DEVUELTO</th>
                    <th style="width: 15%; text-align: center;">TALLA</th>
                    <th style="width: 15%; text-align: center;">CANTIDAD</th>
                    <th style="width: 25%;">ESTADO / INVENTARIO</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($d['producto_nombre'] ?? 'Producto'); ?></strong>
                        <?php if (!empty($d['categoria'])): ?>
                            <br><small style="color: #666;">Cat: <?php echo htmlspecialchars($d['categoria']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($d['talla_nombre'] ?? 'Única'); ?></td>
                    <td style="text-align: center; font-weight: bold; font-size: 14px;"><?php echo $cantFormatted; ?> unds</td>
                    <td><?php echo htmlspecialchars($estadoTexto); ?></td>
                </tr>
            </tbody>
        </table>

        <table class="tabla-datos" style="margin-top: 12px;">
            <tbody>
                <tr>
                    <th style="width: 28%;">Motivo de la Devolución:</th>
                    <td><?php echo nl2br(htmlspecialchars($d['motivo'] ?? 'No especificado')); ?></td>
                </tr>
                <?php if (!empty($d['observaciones'])): ?>
                <tr>
                    <th>Observaciones Internas:</th>
                    <td><?php echo nl2br(htmlspecialchars($d['observaciones'])); ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($d['motivo_inspeccion'])): ?>
                <tr>
                    <th>Detalle de Inspección:</th>
                    <td>
                        <?php echo nl2br(htmlspecialchars($d['motivo_inspeccion'])); ?>
                        <?php if (!empty($d['inspector_nombre'])): ?>
                            <br><small style="color: #666;">Inspeccionado por: <?php echo htmlspecialchars($d['inspector_nombre']); ?> (<?php echo date('d/m/Y h:i A', strtotime($d['fecha_inspeccion'])); ?>)</small>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="seccion-firmas">
            <table>
                <tr>
                    <td style="width: 42%;">
                        <div class="linea-firma">
                            <strong>Entregado Por</strong><br>
                            Firma y Cédula
                        </div>
                    </td>
                    <td style="width: 16%;"></td>
                    <td style="width: 42%;">
                        <div class="linea-firma">
                            <strong>Recibido / Control de Calidad</strong><br>
                            Inverclinik C.A.
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <div class="footer-empresa">
            <p style="margin: 2px 0;"><strong>Inverclinik C.A. | RIF J-41173381-4</strong></p>
            <p style="margin: 2px 0;">Calle Gradizco, Galpón Nro 1, San Pablo, Turmero, Estado Aragua</p>
            <p style="margin: 2px 0;">Teléfono: 0244-6612009 | 0414-1148322 | Email: inverclinik@gmail.com</p>
        </div>
    </div>

    <script>
        window.addEventListener('load', function() {
            window.print();
        });
    </script>
</body>
</html>
