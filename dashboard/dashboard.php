<?php 
require_once('../template/header.php');
require_once '../helpers/Modal.php';

$id = $_SESSION['iduser'];
$sql = "SELECT * from users where id = $id";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

?>

<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="main-content">
    <h3>Bienvenido, <?php echo $row['username']; ?></h3>
    <div class="main-panel">
        <section class="alertas">
          <div class="alert-card produccion">
            <h3> Gestión de Producción</h3>
            <p>Órdenes activas: <span id="ordenesActivas"></span></p>
            <p>Retrasos detectados: <span id="retrasos"></span></p>
          </div>
          <div class="alert-card pagos">
            <h3> Pagos/Cobros</h3>
            <p>Ventas por cobrar: <span id="ventasPendientes"></span></p>
            <p>Compras por pagar: <span id="comprasPendientes"></span></p>
          </div>
          <div class="alert-card inventario">
            <h3> Disponibilidad de Insumos</h3>
            <p>Stock Crítico: <span id="insumosBajos"></span></p>
          </div>
        </section>
  
        <section class="estadisticas">
          <h2> Estadísticas del mes</h2>
          <div class="graficos">
            <div style="width: 100px; height: 300px;">
              <canvas id="graficoVentas"></canvas>
            </div>
            <div style="width: 100px; height: 500px;">
              <canvas id="graficoProduccion" style="width: 100px; height: 100px;"></canvas>
            </div>
          </div>
          <div class="indicadores">
            
          </div>
        </section>
    </div>
  </div>
</body>
</html>

<script>
  async function cargarDashboard() {
    try {
        const response = await fetch('dashboard_datos.php');
        const data = await response.json();

        // 1. Llenar Tarjetas (KPIs)
        document.getElementById('ordenesActivas').textContent = data.kpis.activas;
        document.getElementById('retrasos').textContent = data.kpis.retrasos;
        document.getElementById('ventasPendientes').textContent = data.kpis.v_pendientes;
        document.getElementById('comprasPendientes').textContent = data.kpis.c_pagar;
        document.getElementById('insumosBajos').textContent = data.kpis.bajos;

        // Alerta visual de retrasos
        if(parseInt(data.kpis.retrasos) > 0) {
            document.querySelector('.alert-card.produccion').style.borderLeft = "5px solid red";
        }

        // 2. Gráfico de Torta (Producción)
        const ctxPie = document.getElementById('graficoProduccion').getContext('2d');
        new Chart(ctxPie, {
          type: 'pie',
          data: {
            labels: data.pie_chart.map(item => item.nombre),
            datasets: [{
                data: data.pie_chart.map(item => item.total),
                backgroundColor: ['#005bbe', '#f3c924', '#4a90e2', '#ff6384', '#36a2eb']
            }]
          },
          options: { responsive: true }
        });

        const ctxBar = document.getElementById('graficoVentas').getContext('2d'); 
        
        const estadosColores = {
            'pendiente': '#f3c924',
            'en_proceso': '#36a2eb',
            'finalizado': '#005bbe',
            'cancelado': '#dc3545'
        };

        new Chart(ctxBar, {
          type: 'bar',
          data: {
              labels: data.bar_chart.map(item => item.estado),
              datasets: [{
                  label: 'Cantidad de Órdenes',
                  data: data.bar_chart.map(item => item.total),
                  backgroundColor: data.bar_chart.map(item => estadosColores[item.estado] || '#6c757d')
              }]
          },
          options: {
              responsive: true,
              scales: {
                  y: { beginAtZero: true, ticks: { stepSize: 1 } }
              },
              plugins: {
                  title: { display: true, text: 'Flujo de Trabajo Actual' }
              }
          }
      });

    } catch (error) {
        console.error("Error al cargar datos:", error);
    }
  }

  document.addEventListener('DOMContentLoaded', cargarDashboard);
</script>