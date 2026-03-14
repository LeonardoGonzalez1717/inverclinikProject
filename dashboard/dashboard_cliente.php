<?php 
// Reutilizamos tus headers si son compatibles, o uno simplificado
require_once('../template/header.php');

$id_cliente = $_SESSION['id_cliente']; 
// Consulta rápida para el nombre (ajustar a tu tabla de clientes)
$sql = "SELECT nombre FROM clientes WHERE id = $id_cliente";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
?>

<div class="main-content">
    <h3>Panel de Control - Bienvenido, <?php echo $row['nombre']; ?></h3>
    
    <div class="main-panel">
        <section class="alertas">
          <div class="alert-card produccion">
            <h3> Mis Pedidos Activos</h3>
            <p>En confección: <span id="pedidosActivos">0</span></p>
            <p>Fecha estimada entrega: <span id="fechaEntrega">--/--</span></p>
          </div>

          <div class="alert-card pagos">
            <h3> Estado de Cuenta</h3>
            <p>Saldo pendiente: <span id="saldoPendiente">$0.00</span></p>
            <p>Último pago: <span id="ultimoPago">--</span></p>
          </div>

          <div class="alert-card inventario" style="border-left: 5px solid #28a745;">
            <h3> Historial</h3>
            <p>Órdenes finalizadas: <span id="totalFinalizadas">0</span></p>
          </div>
        </section>

        <section class="estadisticas">
          <!-- <h2> Seguimiento de mi Orden Reciente</h2>
          <div class="graficos" style="display: flex; gap: 20px; align-items: start;">
            
            <div style="flex: 1; max-width: 400px;">
              <canvas id="graficoProgresoOrden"></canvas>
            </div>

            <div class="product-card" style="flex: 1; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
                <h4>Detalles de Manufactura</h4>
                <div id="detalleProductos">
                    <p style="color: #999;">Cargando detalles...</p>
                </div>
            </div>
          </div> -->
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
async function cargarDashboardCliente() {
    try {
        // Llamaremos a un archivo de datos exclusivo para el cliente
        const response = await fetch('dashboard_cliente_datos.php');
        const data = await response.json();

        // 1. Llenar Tarjetas
        document.getElementById('pedidosActivos').textContent = data.resumen.activos;
        document.getElementById('fechaEntrega').textContent = data.resumen.entrega;
        document.getElementById('saldoPendiente').textContent = "$" + data.resumen.saldo;
        document.getElementById('totalFinalizadas').textContent = data.resumen.completados;

        // 2. Gráfico de Dona (Estado de la Orden Actual)
        const ctxDoughnut = document.getElementById('graficoProgresoOrden').getContext('2d');
        new Chart(ctxDoughnut, {
            type: 'doughnut',
            data: {
                labels: ['Completado', 'Restante'],
                datasets: [{
                    data: [data.progreso.porcentaje, 100 - data.progreso.porcentaje],
                    backgroundColor: ['#005bbe', '#e9ecef'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '80%',
                plugins: {
                    title: { display: true, text: 'Avance de Confección (' + data.progreso.porcentaje + '%)' }
                }
            }
        });

        // 3. Llenar detalles de productos
        const contenedor = document.getElementById('detalleProductos');
        contenedor.innerHTML = data.productos.map(p => `
            <div style="display:flex; justify-content:space-between; margin-bottom:10px; border-bottom:1px solid #f4f4f4; padding-bottom:5px;">
                <span>${p.nombre}</span>
                <span class="status-badge" style="background:#d1ecf1; color:#0c5460; padding:2px 8px; border-radius:10px; font-size:12px;">${p.estado}</span>
            </div>
        `).join('');

    } catch (error) {
        console.error("Error al cargar datos del cliente:", error);
    }
}

document.addEventListener('DOMContentLoaded', cargarDashboardCliente);
</script>