<?php
require_once "../template/header.php";
require_once "../connection/connection.php";
require_once "../template/navbar.php"; // Incluir la navbar

$sql = "
   SELECT rp.id, p.nombre AS producto_nombre, i.nombre AS insumo_nombre, rt.nombre_rango AS rango_tallas_nombre, tp.nombre AS tipo_produccion_nombre, rp.cantidad_por_unidad, rp.observaciones 
   FROM recetas_productos rp 
    INNER JOIN productos p ON rp.producto_id = p.id 
    INNER JOIN insumos i ON rp.insumo_id = i.id 
    INNER JOIN rangos_tallas rt ON rp.rango_tallas_id = rt.id 
    INNER JOIN tipos_produccion tp ON rp.tipo_produccion_id = tp.id ORDER BY rp.id;
";

$resultado = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Recetas</title>
    <link rel="stylesheet" href="../css/style.css" />
    <link rel="stylesheet" href="../css/navbar.css" />
    <style>
        /* Estilos generales */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        /* Contenedor principal */
        .container-wrapper {
            min-height: 100vh;
            padding: 20px;
        }

        .container-inner {
            background-color: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Encabezados */
        .main-title {
            color: #0056b3; /* Azul principal */
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
            border-bottom: 3px solid #ffc107; /* Amarillo */
            padding-bottom: 15px;
        }

        .subtitle {
            color: #004085; /* Azul oscuro */
            font-size: 18px;
            text-align: center;
            margin-top: 0;
            margin-bottom: 25px;
        }

        /* Tabla */
        .recipe-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .recipe-table th {
            background-color: #0056b3; /* Azul principal */
            color: white;
            font-weight: bold;
            padding: 14px;
            text-align: left;
        }

        .recipe-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }

        .recipe-table tr:nth-child(even) {
            background-color: #f2f7ff; /* Azul claro */
        }

        .recipe-table tr:hover {
            background-color: #e7f3ff; /* Azul muy claro */
        }

        /* Mensaje cuando no hay datos */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }

        /* Estilo para el contenedor de la tabla */
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Recetas</h2>
                <h5 class="subtitle">Lista de Recetas (con nombres reales)</h5>
                
                <div class="table-container">
                    <table class="recipe-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Producto</th>
                                <th>Insumo</th>
                                <th>Rango de Tallas</th>
                                <th>Tipo de Producción</th>
                                <th>Cantidad por Unidad</th>
                                <th>Observaciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($resultado && $resultado->num_rows > 0): ?>
                                <?php while ($fila = $resultado->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($fila['id']); ?></td>
                                        <td><?php echo htmlspecialchars($fila['producto_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($fila['insumo_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($fila['rango_tallas_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($fila['tipo_produccion_nombre']); ?></td>
                                        <td><?php echo htmlspecialchars($fila['cantidad_por_unidad']); ?></td>
                                        <td><?php echo htmlspecialchars($fila['observaciones'] ?? ''); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="no-data">No se encontraron recetas registradas</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php
    $conn->close();
    ?>
</body>
</html>