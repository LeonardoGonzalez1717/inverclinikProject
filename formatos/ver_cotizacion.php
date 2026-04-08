<?php
require_once "../connection/connection.php";

$id_cotizacion = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id_cotizacion <= 0) {
    die("Cotización no encontrada.");
}

$sql = "SELECT c.*, cl.nombre AS cliente, cl.telefono, cl.direccion,
        COALESCE(cl.email, '') AS correo
        FROM cotizaciones c
        INNER JOIN clientes cl ON c.id_cliente = cl.id
        WHERE c.id_cotizacion = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $id_cotizacion);
$stmt->execute();
$res = $stmt->get_result();
$c = $res ? $res->fetch_assoc() : null;
$stmt->close();

if (!$c) {
    die("Cotización no encontrada.");
}

$fechaCab = !empty($c['fecha_registro']) ? $c['fecha_registro'] : date('Y-m-d');

$sql_det = "SELECT cd.*, p.nombre AS producto_nombre, rt.nombre_rango AS nombre_talla
            FROM cotizacion_detalles cd
            INNER JOIN recetas r ON cd.id_receta = r.id
            INNER JOIN productos p ON r.producto_id = p.id
            LEFT JOIN rangos_tallas rt ON rt.id = cd.id_talla
            WHERE cd.id_cotizacion = ?";
$stmtDet = $conn->prepare($sql_det);
$stmtDet->bind_param('i', $id_cotizacion);
$stmtDet->execute();
$res_det = $stmtDet->get_result();
?>

<head>
    <title>Cotización_<?php echo $c['codigo_cotizacion']; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; padding: 20px; background-color: #f9f9f9; }
        .cotizacion-box { max-width: 850px; margin: auto; border: 1px solid #ddd; padding: 40px; background: white; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-imprimir { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; font-weight: bold; }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #005bbe; padding-bottom: 15px; }
        .logo-text { font-size: 28px; font-weight: bold; color: #005bbe; margin: 0; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 25px 0; }
        .info-card { padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee; }
        
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
            .btn-imprimir { display: none; }
            body { background: white; padding: 0; }
            .cotizacion-box { border: none; box-shadow: none; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div style="width:700px; margin: auto; text-align: center;">
            <img src="../assets/img/brand.png" alt="brand" width="100%" height="120px">
            <p style="margin:0; text-align: left; font-size: 10px;">RIF J-41173381-4</p>
        <div>
            <div style="text-align: right;">
                <h2 style="margin:0; color: #555;">COTIZACIÓN</h2>
                <p style="margin:5px 0;"><strong>Nro:</strong> <?php echo $c['codigo_cotizacion']; ?></p>
                <p style="margin:0;"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($fechaCab)); ?></p>
            </div>
        </div>

        <table class="tabla-cliente">
            <tr>
                <td><b>Cliente: </b></td>
                <td><?php echo htmlspecialchars($c['cliente']); ?></td>

                <td><b>RIF/Cedula: </b></td>
                <td><?php echo $c['numero_documento'] ?? 'S/N'; ?></td>
            </tr>
            <tr>
                <td><b>Direccion: </b></td>
                <td><?php echo htmlspecialchars($c['direccion']); ?></td>

                <td><b>Telefono: </b></td>
                <td><?php echo $c['telefono'] ?? 'S/N'; ?></td>
            </tr>
        </table>
        <table>
            <thead>
                <tr>
                    <th width="5%">REF.</th>
                    <th width="45%">PRODUCTO</th>
                    <th width="10%">CANT.</th>
                    <th width="20%">PRECIO UNIT. ($)</th>
                    <th width="20%">TOTAL ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $i = 0;
                while($d = $res_det->fetch_assoc()): 
                $i++;?>
                <tr>
                    <td align="center"><strong><?php echo $i; ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($d['producto_nombre']); ?></strong><br>
                        <small>Talla: <?php echo $d['talla'] ?? 'Única'; ?></small>
                        <?php if(!empty($d['notas'])): ?>
                            <div class="notas">Obs: <?php echo htmlspecialchars($d['notas']); ?></div>
                        <?php endif; ?>
                    </td>
                    <td align="center"><strong><?php echo number_format($d['cantidad'], 0); ?></strong></td>

                    <td align="right">$<?php echo number_format($d['precio_unitario'], 2); ?></td>
                    <td align="right"><strong>$<?php echo number_format($d['subtotal'], 2); ?></strong></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <div class="totales">
            <div class="total-final">
                <strong>TOTAL PRESUPUESTADO: $<?php echo number_format($c['total'], 2); ?></strong>
            </div>
            <p style="margin-top:10px; font-size: 0.9em; color: #777;">
                <i>Precios no incluyen IVA.</i>
            </p>
        </div>

        <div style="margin-top: 60px; text-align: center; font-size: 0.8em; border-top: 1px solid #eee; padding-top: 20px;">
            <p>Inverclinik C.A. | RIF J-41173381-4</p>
            <p>Calle Gradizco, Galpon Nro 1, San Pablo, Turmero, Estado Aragua</p>
            <p>Telefono: 0244-6612009 | 0414-1148322 | 0414-1148322 / Email: inverclinik@gmail.com</p>
        </div>
    </div>

</body>
</html>
<script>
    window.print();
</script>