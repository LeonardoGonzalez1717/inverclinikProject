<?php
require_once "../connection/connection.php";

$id_cotizacion = $_GET['id'] ?? null;
if (!$id_cotizacion) die("Cotización no encontrada.");

// 1. Consulta de cabecera: Cotización + Cliente
$sql = "SELECT c.*, cl.nombre as cliente, cl.telefono, cl.direccion, cl.correo
        FROM cotizaciones c
        INNER JOIN clientes cl ON c.id_cliente = cl.id
        WHERE c.id = $id_cotizacion";

$res = $conn->query($sql);
$c = $res->fetch_assoc();

// 2. Consulta de detalles (incluyendo Talla y Producto/Receta)
$sql_det = "SELECT cd.*, r.nombre_receta, t.nombre_talla, p.nombre as producto_nombre
            FROM cotizacion_detalles cd
            INNER JOIN recetas r ON cd.id_receta = r.id
            INNER JOIN productos p ON r.producto_id = p.id
            LEFT JOIN tallas t ON cd.id_talla = t.id
            WHERE cd.id_cotizacion = $id_cotizacion";
$res_det = $conn->query($sql_det);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización_<?php echo $c['codigo_cotizacion']; ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #333; margin: 0; padding: 20px; background-color: #f9f9f9; }
        .cotizacion-box { max-width: 850px; margin: auto; border: 1px solid #ddd; padding: 40px; background: white; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .btn-imprimir { background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; margin-bottom: 20px; font-weight: bold; }
        
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #005bbe; padding-bottom: 15px; }
        .logo-text { font-size: 28px; font-weight: bold; color: #005bbe; margin: 0; }
        
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 25px 0; }
        .info-card { padding: 15px; background: #f8f9fa; border-radius: 8px; border: 1px solid #eee; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        table th { background: #005bbe; color: white; padding: 12px; text-align: left; }
        table td { border-bottom: 1px solid #eee; padding: 12px; vertical-align: top; }
        
        .notas { font-size: 0.85em; color: #666; font-style: italic; margin-top: 5px; }
        .totales { text-align: right; margin-top: 30px; }
        .total-final { font-size: 1.5em; color: #333; border-top: 2px solid #005bbe; display: inline-block; padding-top: 10px; }

        @media print {
            .btn-imprimir { display: none; }
            body { background: white; padding: 0; }
            .cotizacion-box { border: none; box-shadow: none; width: 100%; max-width: 100%; }
        }
    </style>
</head>
<body>

    <div class="cotizacion-box">
        <button class="btn-imprimir" onclick="window.print()">🖨️ Imprimir Cotización</button>

        <div class="header">
            <div>
                <p class="logo-text">INVERCLINIK C.A.</p>
                <small>Confección Textil de Alta Calidad</small>
            </div>
            <div style="text-align: right;">
                <h2 style="margin:0; color: #555;">COTIZACIÓN</h2>
                <p style="margin:5px 0;"><strong>Nro:</strong> <?php echo $c['codigo_cotizacion']; ?></p>
                <p style="margin:0;"><strong>Fecha:</strong> <?php echo date('d/m/Y', strtotime($c['fecha_creacion'])); ?></p>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-card">
                <strong>DATOS DEL CLIENTE:</strong><br>
                <?php echo htmlspecialchars($c['cliente']); ?><br>
                Tel: <?php echo $c['telefono'] ?? 'S/N'; ?><br>
                <?php echo htmlspecialchars($c['correo']); ?>
            </div>
            <div class="info-card">
                <strong>VALIDEZ Y CONDICIONES:</strong><br>
                Validez: 15 días continuos.<br>
                Forma de pago: 50% anticipo / 50% entrega.<br>
                Tiempo estimado: Según volumen de pedido.
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="10%">CANT</th>
                    <th width="50%">DESCRIPCIÓN / DETALLES</th>
                    <th width="20%">P. UNIT ($)</th>
                    <th width="20%">SUBTOTAL ($)</th>
                </tr>
            </thead>
            <tbody>
                <?php while($d = $res_det->fetch_assoc()): ?>
                <tr>
                    <td align="center"><strong><?php echo number_format($d['cantidad'], 0); ?></strong></td>
                    <td>
                        <strong><?php echo htmlspecialchars($d['producto_nombre']); ?></strong><br>
                        <small>Talla: <?php echo $d['nombre_talla'] ?? 'Única'; ?></small>
                        <?php if(!empty($d['notas'])): ?>
                            <div class="notas">Obs: <?php echo htmlspecialchars($d['notas']); ?></div>
                        <?php endif; ?>
                    </td>
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
                <i>Precios sujetos a cambios según disponibilidad de materia prima.</i>
            </p>
        </div>

        <div style="margin-top: 60px; text-align: center; font-size: 0.8em; border-top: 1px solid #eee; padding-top: 20px;">
            <p>Inverclinik C.A. | RIF J-12345678-9 | Turmero, Estado Aragua</p>
            <p><strong>"Calidad que se siente en cada costura"</strong></p>
        </div>
    </div>

</body>
</html>