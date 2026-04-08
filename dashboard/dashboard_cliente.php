<?php 
require_once('../template/header.php');

// Asegúrate de que la sesión tenga el ID, si no, redirigir
if (!isset($_SESSION['id_cliente'])) {
    header("Location: login.php");
    exit;
}

$id_cliente = $_SESSION['id_cliente']; 
$sql = "SELECT nombre FROM clientes WHERE id = $id_cliente";
$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);
?>

<div class="main-content">
    <h3>Panel de Control - Bienvenido, <?php echo htmlspecialchars($row['nombre']); ?></h3>
    
    <div class="main-panel">
        <section class="alertas" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
          <div class="alert-card produccion">
            <h3>Mis Pedidos Activos</h3>
            <p>En confección: <strong id="pedidosActivos">0</strong></p>
            <p><small>Próxima entrega estimada: <span id="fechaEntrega">--/--</span></small></p>
          </div>

          <div class="alert-card pagos">
            <h3>Estado de Cuenta</h3>
            <p>Saldo pendiente: <strong id="saldoPendiente">$0.00</strong></p>
            <p><small>Tasa Ref BCV aplicada</small></p>
          </div>

          <div class="alert-card inventario">
            <h3>Historial</h3>
            <p>Órdenes finalizadas: <strong id="totalFinalizadas">0</strong></p>
          </div>
        </section>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
async function cargarDashboardCliente() {
    try {
        const response = await fetch('dashboard_clientes_datos.php');
        const data = await response.json();

        // 1. Llenar Tarjetas
       // En tu función cargarDashboardCliente()...
        document.getElementById('pedidosActivos').textContent = data.kpis.activos;
        document.getElementById('saldoPendiente').innerHTML = `
            $${data.kpis.saldo_usd} <br>
            <small style="color: #666; font-size: 0.8em;">Bs. ${data.kpis.saldo_bs}</small>
        `;
        document.getElementById('totalFinalizadas').textContent = data.kpis.total_finalizados;
        document.getElementById('ultimoPago').textContent = "Cliente desde " + data.kpis.desde;

        // 2. Gráfico de Dona
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
                    legend: { position: 'bottom' },
                    title: { display: true, text: 'Avance de la última orden (' + data.progreso.porcentaje + '%)' }
                }
            }
        });

        // 3. Detalles de productos
        const contenedorProds = document.getElementById('detalleProductos');
        if(data.productos.length > 0) {
            contenedorProds.innerHTML = data.productos.map(p => `
                <div style="display:flex; justify-content:space-between; margin-bottom:10px; border-bottom:1px solid #f4f4f4; padding-bottom:5px;">
                    <span>${p.nombre}</span>
                    <span class="status-badge" style="background:#e3f2fd; color:#005bbe; padding:2px 8px; border-radius:10px; font-size:11px;">${p.estado}</span>
                </div>
            `).join('');
        } else {
            contenedorProds.innerHTML = '<p>No hay productos en confección.</p>';
        }

        // 4. Llenar Documentos (Facturas y Cotizaciones)
        const listaDoc = document.getElementById('listaDocumentos');
        // Asumiendo que 'data.documentos' viene del PHP con los enlaces listos
        listaDoc.innerHTML = data.documentos.map(doc => `
            <tr style="border-bottom: 1px solid #eee;">
                <td style="padding: 10px 0;">${doc.fecha}</td>
                <td><span style="font-size: 11px; padding: 2px 5px; border-radius: 4px; background: #eee;">${doc.tipo}</span></td>
                <td>${doc.referencia}</td>
                <td>$${doc.total}</td>
                <td><a href="${doc.link}" target="_blank" style="color: #005bbe; text-decoration: none;"><i class="fas fa-download"></i> Ver</a></td>
            </tr>
        `).join('');

    } catch (error) {
        console.error("Error al cargar datos del cliente:", error);
    }
}

document.addEventListener('DOMContentLoaded', cargarDashboardCliente);
</script>