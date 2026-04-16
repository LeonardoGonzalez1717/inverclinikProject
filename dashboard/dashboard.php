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
        .dashboard-filtro {
            display: flex; flex-wrap: wrap; align-items: flex-end; gap: 12px 16px;
            margin-bottom: 24px; padding: 16px 18px;
            background: #fff; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.08);
            border-left: 4px solid #005bbe;
        }
        .dashboard-filtro label { font-weight: 600; color: #333; margin-bottom: 4px; display: block; font-size: 0.9rem; }
        .dashboard-filtro .form-group { margin-bottom: 0; min-width: 160px; }
        .dashboard-filtro .btn-aplicar { background: #005bbe; border: none; color: #fff; padding: 8px 18px; border-radius: 6px; cursor: pointer; font-weight: 600; }
        .dashboard-filtro .btn-aplicar:hover { filter: brightness(1.05); }
        .dashboard-filtro .periodo-texto { width: 100%; font-size: 0.85rem; color: #666; margin: 0; }
    </style>
</head>
<body>
    <div class="main-content">
        <h3>Bienvenido, <?php echo $row['username']; ?></h3>
        
        <div class="main-panel">
            <form class="dashboard-filtro" id="formFiltroDashboard" action="#" method="get" autocomplete="off">
                <div class="form-group">
                    <label for="fecha_desde">Desde</label>
                    <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" />
                </div>
                <div class="form-group">
                    <label for="fecha_hasta">Hasta</label>
                    <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" />
                </div>
                <div class="form-group">
                    <button type="submit" class="btn-aplicar">Aplicar</button>
                </div>
            </form>

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
                <h2 id="tituloEstadisticas">Análisis de rendimiento</h2>
                <p id="subtituloPeriodo" class="text-muted" style="margin-top:-8px;"></p>
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
let chartDona = null;
let chartBar = null;

function tituloDonaProductos(filtro) {
    if (!filtro || !filtro.aplicar_filtro_fecha) return 'Top 5 productos (todas las cotizaciones)';
    if (filtro.modo_fecha === 'desde') return 'Top 5 productos (cotizaciones desde ' + filtro.fecha_desde + ')';
    if (filtro.modo_fecha === 'hasta') return 'Top 5 productos (cotizaciones hasta ' + filtro.fecha_hasta + ')';
    return 'Top 5 productos (cotizaciones en el período)';
}

function tituloBarrasPresupuestos(filtro) {
    if (!filtro || !filtro.aplicar_filtro_fecha) return 'Presupuestos por mes — histórico completo ($)';
    if (filtro.modo_fecha === 'desde') return 'Presupuestos por mes desde ' + filtro.fecha_desde + ' ($)';
    if (filtro.modo_fecha === 'hasta') return 'Presupuestos por mes hasta ' + filtro.fecha_hasta + ' ($)';
    return 'Presupuestos por mes en el período ($)';
}

function urlDashboardDatos() {
    const fd = document.getElementById('fecha_desde').value;
    const fh = document.getElementById('fecha_hasta').value;
    const params = new URLSearchParams();
    if (fd) params.set('fecha_desde', fd);
    if (fh) params.set('fecha_hasta', fh);
    const q = params.toString();
    return 'dashboard_datos.php' + (q ? '?' + q : '');
}

async function cargarDashboard() {
    try {
        const response = await fetch(urlDashboardDatos());
        const data = await response.json();

        const filtro = data.filtro || {};
        document.getElementById('fecha_desde').value = filtro.fecha_desde || '';
        document.getElementById('fecha_hasta').value = filtro.fecha_hasta || '';
        const sub = document.getElementById('subtituloPeriodo');
        if (!filtro.aplicar_filtro_fecha) {
            sub.textContent = 'Sin filtro de fechas: totales históricos en finanzas y gráficos.';
        } else if (filtro.modo_fecha === 'desde') {
            sub.textContent = 'Filtro: desde ' + filtro.fecha_desde + ' (hacia adelante, sin límite superior).';
        } else if (filtro.modo_fecha === 'hasta') {
            sub.textContent = 'Filtro: hasta ' + filtro.fecha_hasta + ' (desde el inicio de los registros).';
        } else {
            sub.textContent = 'Período filtrado: ' + filtro.fecha_desde + ' — ' + filtro.fecha_hasta;
        }

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
        if (chartDona) chartDona.destroy();
        chartDona = new Chart(ctxDona, {
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
                    title: { display: true, text: tituloDonaProductos(data.filtro) },
                    legend: { position: 'bottom' }
                }
            }
        });

        // 3. Gráfico de Barras (Presupuestos por mes en el período)
        const ctxBar = document.getElementById('graficoVentasBarras').getContext('2d');
        if (chartBar) chartBar.destroy();
        chartBar = new Chart(ctxBar, {
            type: 'bar',
            data: {
                labels: data.ventas_mes.map(item => item.mes),
                datasets: [{
                    label: 'Total presupuestos ($)',
                    data: data.ventas_mes.map(item => item.monto_total),
                    backgroundColor: '#4a90e2',
                }]
            },
            options: {
                indexAxis: 'y', // <--- ¡ESTO vuelve las barras horizontales!
                responsive: true,
                plugins: {
                    title: { display: true, text: tituloBarrasPresupuestos(data.filtro) }
                }
            }
        });

    } catch (error) {
        console.error("Error al cargar el dashboard:", error);
    }
}

document.addEventListener('DOMContentLoaded', function () {
    cargarDashboard();

    document.getElementById('formFiltroDashboard').addEventListener('submit', function (e) {
        e.preventDefault();
        cargarDashboard();
    });
});
</script>
</body>
</html>