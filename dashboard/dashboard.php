<?php 
require_once('../template/header.php');
require_once '../helpers/Modal.php';

$id = $_SESSION['iduser'];
$sql = "SELECT * FROM users WHERE id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .main-panel { padding: 20px; }
        .alertas { display: flex; gap: 20px; margin-bottom: 30px; }
        .alert-card { 
            flex: 1; padding: 15px; border-radius: 8px; 
            background: #fff; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            border-left: 5px solid #005bbe;
        }
        .graficos-container { 
            display: flex; gap: 30px; flex-wrap: wrap; margin-top: 20px; 
        }
        .chart-box { 
            flex: 1; min-width: 300px; background: #fff; 
            padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        canvas { width: 100% !important; height: auto !important; }
    </style>
</head>
<body>
    <div class="main-content">
        <h3>Bienvenido, <?php echo $row['username']; ?></h3>
        
        <div class="main-panel">
            <section class="alertas">
                <div class="alert-card produccion" id="card-prod">
                    <h3> Producción</h3>
                    <p>Órdenes activas: <span id="ordenesActivas">0</span></p>
                    <p>Retrasos: <span id="retrasos">0</span></p>
                </div>
                <div class="alert-card pagos">
                    <h3> Finanzas</h3>
                    <p>Ventas Pendientes: <span id="ventasPendientes">0</span></p>
                    <p>Cotizaciones Pendientes: <span id="cotiPendientes">0</span></p>
                </div>
                <div class="alert-card inventario">
                    <h3>Inventario</h3>
                    <p>Insumos bajo stock crítico: <span id="insumosBajos">0</span></p>
                    <p>Productos bajo stock crítico: <span id="ProductosBajos">0</span></p>
                </div>
            </section>

            <section class="estadisticas">
                <h2>Análisis de Rendimiento Mensual</h2>
                <div class="graficos-container" style="display: flex; gap: 20px; align-items: flex-start;">
                    <div class="chart-box" style="flex: 1;">
                        <canvas id="graficoDonaProductos"></canvas>
                    </div>
                    <div class="chart-box" style="flex: 1.5;"> <canvas id="graficoVentasBarras"></canvas>
                    </div>
                </div>
            </section>
        </div>
    </div>

<script>
async function cargarDashboard() {
    try {
        const response = await fetch('dashboard_datos.php');
        const data = await response.json();

        // 1. Llenar KPIs
        document.getElementById('ordenesActivas').textContent = data.kpis.activas;
        document.getElementById('retrasos').textContent = data.kpis.retrasos;
        document.getElementById('ventasPendientes').textContent = data.kpis.v_pendientes;
        document.getElementById('cotiPendientes').textContent = data.kpis.coti_pendientes_cant;
        document.getElementById('insumosBajos').textContent = data.kpis.insumos_bajos;
        document.getElementById('ProductosBajos').textContent = data.kpis.productos_bajos;

        if(parseInt(data.kpis.retrasos) > 0) {
            document.getElementById('card-prod').style.borderLeft = "5px solid #dc3545";
        }

        // 2. Gráfico de Dona (Productos Top)
        const ctxDona = document.getElementById('graficoDonaProductos').getContext('2d');
        new Chart(ctxDona, {
            type: 'doughnut',
            data: {
                labels: data.productos_top.map(item => item.nombre),
                datasets: [{
                    data: data.productos_top.map(item => item.total_vendido),
                    backgroundColor: ['#005bbe', '#f3c924', '#28a745', '#e44d26', '#6f42c1']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Top 5 Productos Más Vendidos' },
                    legend: { position: 'bottom' }
                }
            }
        });

        // 3. Gráfico de Barras (Ventas Mensuales)
        const ctxBar = document.getElementById('graficoVentasBarras').getContext('2d');
        new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: data.ventas_mes.map(item => item.mes),
                datasets: [{
                    label: 'Ventas Totales ($)',
                    data: data.ventas_mes.map(item => item.monto_total),
                    backgroundColor: '#4a90e2',
                }]
            },
            options: {
                indexAxis: 'y', // <--- ¡ESTO vuelve las barras horizontales!
                responsive: true,
                plugins: {
                    title: { display: true, text: 'Rendimiento por Mes' }
                }
            }
        });

    } catch (error) {
        console.error("Error al cargar el dashboard:", error);
    }
}

document.addEventListener('DOMContentLoaded', cargarDashboard);
</script>
</body>
</html>