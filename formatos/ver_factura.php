<?php
require_once "../connection/connection.php";

$id_venta = $_GET['id'] ?? null;
if (!$id_venta) die("Venta no encontrada.");

// Consulta completa: Venta + Cliente + Cotización + Tasa
$sql = "SELECT v.*, c.nombre as cliente, c.telefono, c.direccion, 
               cot.codigo_cotizacion, tc.tasa as tasa_vta
        FROM ventas v 
        INNER JOIN clientes c ON v.cliente_id = c.id 
        LEFT JOIN cotizaciones cot ON v.cotizacion_id = cot.id
        LEFT JOIN tasas_cambiarias tc ON v.tasa_cambiaria_id = tc.id
        WHERE v.id = $id_venta";

$res = $conn->query($sql);
$v = $res->fetch_assoc();

// Consulta de detalles
$sql_det = "SELECT dv.*, p.nombre as producto 
            FROM detalle_venta dv 
            INNER JOIN productos p ON dv.producto_id = p.id 
            WHERE dv.venta_id = $id_venta";
$res_det = $conn->query($sql_det);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura_<?php echo $v['numero_factura'] ?? $v['id']; ?></title>
    <style>
        /* Estilos para pantalla y para impresión */
        body { font-family: 'Courier New', Courier, monospace; color: #333; margin: 0; padding: 20px; }
        .factura-box { max-width: 800px; margin: auto; border: 1px solid #eee; padding: 30px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.15); }
        .btn-imprimir { background: #005bbe; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; }
        
        .header { display: flex; justify-content: space-between; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .info-seccion { display: flex; justify-content: space-between; margin: 20px 0; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th { background: #f4f4f4; border: 1px solid #ddd; padding: 8px; }
        table td { border: 1px solid #ddd; padding: 8px; }
        
        .totales { text-align: right; margin-top: 20px; font-size: 1.2em; }
        
        /* Ocultar botón al imprimir */
        @media print {
            .btn-imprimir { display: none; }
            .factura-box { border: none; box-shadow: none; width: 100%; }
        }
    </style>
</head>
<body>

    <div class="factura-box">
        <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir Factura</button>

        <div class="header">
            <div>
                <h2 style="margin:0;">INVERCLINIK C.A.</h2>
                <small>RIF: J-12345678-9 | Turmero, Edo. Aragua</small>
            </div>
            <div style="text-align: right;">
                <h3 style="margin:0; color: #d9534f;">FACTURA: <?php echo $v['numero_factura'] ?? 'N/A'; ?></h3>
                <p>Fecha: <?php echo date('d/m/Y', strtotime($v['fecha'])); ?></p>
            </div>
        </div>

        <div class="info-seccion">
            <div>
                <strong>CLIENTE:</strong> <?php echo htmlspecialchars($v['cliente']); ?><br>
                <strong>TELÉFONO:</strong> <?php echo $v['telefono'] ?? 'S/N'; ?><br>
                <strong>DIRECCIÓN:</strong> <?php echo $v['direccion'] ?? 'N/A'; ?>
            </div>
            <div style="text-align: right;">
                <strong>COTIZACIÓN REF:</strong> <?php echo $v['codigo_cotizacion'] ?? 'VENTA DIRECTA'; ?><br>
                <strong>TASA REF (BCV):</strong> Bs. <?php echo number_format($v['tasa_vta'], 2); ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>CANT</th>
                    <th>DESCRIPCIÓN</th>
                    <th>P. UNIT ($)</th>
                    <th>SUBTOTAL ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php while($d = $res_det->fetch_assoc()): 
                    $sub = $d['cantidad'] * $d['precio_unitario'];
                ?>
                <tr>
                    <td align="center"><?php echo number_format($d['cantidad'], 0); ?></td>
                    <td><?php echo htmlspecialchars($d['producto']); ?></td>
                    <td align="right"><?php echo number_format($d['precio_unitario'], 2); ?></td>
                    <td align="right"><?php echo number_format($sub, 2); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="totales">
            <p><strong>TOTAL DIVISAS: $<?php echo number_format($v['total'], 2); ?></strong></p>
            <p style="color: #666; font-size: 0.9em;">TOTAL EN BOLÍVARES: Bs. <?php echo number_format($v['total'] * $v['tasa_vta'], 2); ?></p>
        </div>

        <div style="margin-top: 50px; font-size: 0.8em; text-align: center; border-top: 1px dashed #ccc; padding-top: 10px;">
            <p>Gracias por confiar en Inverclinik - Especialistas en Confección Textil</p>
        </div>
    </div>

</body>
</html>