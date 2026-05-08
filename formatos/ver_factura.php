<?php
require_once "../connection/connection.php";

$id_venta = $_GET['id'] ?? null;
if (!$id_venta) die("Venta no encontrada.");

$sql = "SELECT v.*, cl.nombre as cliente, cl.telefono, cl.direccion, cl.numero_documento, tc.tasa
        FROM ventas v
        INNER JOIN clientes cl ON v.cliente_id = cl.id
        LEFT JOIN tasas_cambiarias tc ON v.tasa_cambiaria_id = tc.id
        WHERE v.id = $id_venta";

$res = $conn->query($sql);
$v = $res->fetch_assoc();

$sql_det = "SELECT dv.*, p.nombre as producto_nombre
            FROM detalle_venta dv
            INNER JOIN recetas r ON dv.producto_id = r.id
            inner join productos p on r.producto_id = p.id
            WHERE dv.venta_id = $id_venta";
$res_det = $conn->query($sql_det);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Nota de entrega_<?php echo $v['numero_factura'] ?? $v['id']; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; padding: 20px; background-color: #f9f9f9; }
        
        /* Contenedor principal ajustado a 700px como tu modelo */
        .nota-entrega-box { width: 700px; margin: auto; background: white; padding: 20px; }

        table { width: 100%; border-collapse: collapse; margin-top: 20px; border: 1px solid #000000; }
        table th { padding: 12px; text-align: left; border: 1px solid #000000;  }
        table td { border-bottom: 1px solid #eee; padding: 12px; vertical-align: top; border: 1px solid #000000;  }

        .tabla-cliente { 
            width: 100%; 
            border-collapse: collapse; 
            margin-bottom: 20px;
            border: none; /* Elimina bordes externos */
        }
        .tabla-cliente td { 
            padding: 5px 0; 
            border: none !important; /* Elimina bordes de celdas */
            font-size: 14px;
        }
        
        .notas { font-size: 11px; font-style: italic; margin-top: 5px; }
        .totales { text-align: right; margin-top: 30px; }
        .total-final { font-size: 14px; border-top: 2px solid #000000; display: inline-block; padding-top: 10px; }

        @media print {
            body { background: white; padding: 0; }
            .nota-entrega-box { width: 100%; border: none; }
        }
    </style>
</head>
<body>

    <div class="nota-entrega-box">
        <div style="text-align: center;">
            <img src="../assets/img/brand.png" alt="brand" width="100%" height="120px">
            <p style="margin:0; text-align: left; font-size: 10px;">RIF J-41173381-4</p>
        </div>

        <div style="margin-top: 10px;">
            <div style="text-align: right;">
                <strong style="font-size: 16px;">NOTA DE ENTREGA <?php echo $v['numero_factura'] ?? 'N/A'; ?></strong>
                <p style="margin:0; text-align: left;"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($v['fecha'])); ?></p>
            </div>
        </div>

        <table class="tabla-cliente">
            <tr>
                <td width="15%"><b>Cliente: </b></td>
                <td width="45%"><?php echo htmlspecialchars($v['cliente']); ?></td>
                <td width="15%"><b>RIF/Cédula: </b></td>
                <td width="25%"><?php echo $v['numero_documento'] ?? 'S/N'; ?></td>
            </tr>
            <tr>
                <td><b>Dirección: </b></td>
                <td><?php echo htmlspecialchars($v['direccion']); ?></td>
                <td><b>Teléfono: </b></td>
                <td><?php echo $v['telefono'] ?? 'S/N'; ?></td>
            </tr>
        </table>

        <table>
            <thead>
                <tr>
                    <th width="5%">REF.</th>
                    <th width="55%">DESCRIPCIÓN PRODUCTO</th>
                    <th width="10%">CANT.</th>
                    <th width="15%">P. UNIT ($)</th>
                    <th width="15%">TOTAL ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 0;
                while($d = $res_det->fetch_assoc()): 
                $i++;?>
                <tr>
                    <td align="center"><?php echo $i; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($d['producto_nombre']); ?></strong>
                    </td>
                    <td align="center"><?php echo number_format($d['cantidad'], 0); ?></td>
                    <td align="right"><?php echo number_format($d['precio_unitario'], 2); ?></td>
                    <td align="right"><strong><?php echo number_format($d['subtotal'], 2); ?></strong></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="totales">
            <div class="total-final">
                <b>TOTAL A PAGAR: $<?php echo number_format($v['total'], 2); ?></b>
            </div>
            <?php if($v['tasa']): ?>
            <p style="margin-top:5px; font-size: 0.85em; color: #333;">
                Tasa de cambio (BCV): Bs. <?php echo number_format($v['tasa'], 2); ?><br>
                <strong>Total en Bolívares: Bs. <?php echo number_format($v['total'] * $v['tasa'], 2); ?></strong>
            </p>
            <?php endif; ?>
        </div>

        <div style="margin-top: 50px; text-align: center; font-size: 0.8em; border-top: 1px solid #eee; padding-top: 20px;">
            <p>Inverclinik C.A. | RIF J-41173381-4</p>
            <p>Calle Gradizco, Galpon Nro 1, San Pablo, Turmero, Estado Aragua</p>
            <p>Teléfono: 0244-6612009 | 0414-1148322 | Email: inverclinik@gmail.com</p>
        </div>
    </div>

    <script>
        window.print();
    </script>
</body>
</html>