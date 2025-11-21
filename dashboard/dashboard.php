<?php 
require_once('../template/header.php');
require_once '../helpers/Modal.php';

$id = $_GET['iduser'] ?? 1;
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
            <h3> Producción</h3>
            <p>Órdenes activas: <span id="ordenesActivas">3</span></p>
            <p>Retrasos detectados: <span id="retrasos">1</span></p>
          </div>
          <div class="alert-card pagos">
            <h3> Pagos/Cobros</h3>
            <p>Ventas por cobrar: <span id="ventasPendientes">2</span></p>
            <p>Compras por pagar: <span id="comprasPendientes">1</span></p>
          </div>
          <div class="alert-card inventario">
            <h3> Falta Materia Prima</h3>
            <p>Insumos bajos: <span id="insumosBajos">4</span></p>
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
            <p>Total ventas: <strong>$12,500</strong></p>
            <p>Uniformes producidos: <strong>320</strong></p>
            <p>Insumo más usado: <strong>Tela Jean</strong></p>
          </div>
        </section>
    </div>
  </div>
</body>
</html>

<script>
const ctxVentas = document.getElementById('graficoVentas').getContext('2d');
new Chart(ctxVentas, {
  type: 'bar',
  data: {
    labels: ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'],
    datasets: [
      {
        label: 'Encargo',
        data: [12, 19, 14, 17, 20, 15],
        backgroundColor: '#005bbe'
      },
      {
        label: 'Stock',
        data: [8, 11, 9, 10, 12, 9],
        backgroundColor: '#f3c924'
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'top' },
      title: {
        display: true,
        text: 'Ventas mensuales por tipo'
      }
    }
  }
});

const ctxProduccion = document.getElementById('graficoProduccion').getContext('2d');
new Chart(ctxProduccion, {
  type: 'pie',
  data: {
    labels: ['Bata médica', 'Mono industrial', 'Pijama quirúrgico'],
    datasets: [{
      data: [45, 30, 25],
      backgroundColor: ['#005bbe', '#f3c924', '#4a90e2']
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { position: 'bottom' },
      title: {
        display: true,
        text: 'Distribución de producción por prenda'
      }
    }
  }
});
</script>