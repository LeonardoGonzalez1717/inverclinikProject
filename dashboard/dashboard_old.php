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
  <link rel="stylesheet" href="style.css" />
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
  <div class="dashboard-container">
    <!-- Menú lateral -->
    <aside class="sidebar" style ="height: 100%;">
      <div class="logo"><a href="#" title="Inicio"> INVERCLINIK</a></div>
      <nav>
        <ul>
          <li><a href="#"> Ventas</a></li>
          <li><a href="#"> Producción</a></li>
          <li><a href="#"> Inventario</a></li>
          <li><a href="#"> Compras</a></li>
          <li><a href="#"> Prendas</a></li>
          <li><a href="#"> Reportes</a></li>
          <li><a href="#"> Auditoría</a></li>
        </ul>
      </nav>
      <div class="perfil">
        <button onclick="abrirEditarPerfil(<?php echo $id; ?>)">Perfil</button>
        <button> Gestionar Perfiles</button>
        <button>Cerrar sesión</button>
      </div>
    </aside>

    <!-- Panel principal -->
    <main class="main-panel">
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
    </main>
  </div>
</body>
</html>

<script>
  function abrirEditarPerfil(usuario) {
        modoModal = 'editar';
        
        // Llenar los campos
        $('#username').val(usuario.username);
        $('#correo').val(usuario.correo);
        
        // Ocultar campo de contraseña (opcional, o puedes dejarlo para cambiarla)
        $('#password').closest('.form-group').hide();
        
        // Cambiar título y botón
        $('#crearUsuarioModalLabel').text('Editar Perfil');
        $('#guardarUsuario')
            .text('Actualizar')
            .removeClass('btn-primary')
            .addClass('btn-warning');
        
        // Mostrar el modal
        $('#crearUsuarioModal').modal('show');
    }

    // Manejar el clic en "Guardar/Actualizar"
    $("#guardarUsuario").click(function() {
        const username = $("#username").val();
        const correo = $("#correo").val();
        const password = $("#password").val();

        if (!username || !correo) {
            alert("Completa todos los campos obligatorios");
            return;
        }

        if (modoModal === 'crear') {
            if (!password) {
                alert("La contraseña es obligatoria al crear un usuario");
                return;
            }
            // Crear nuevo usuario
            $.post("user_options/crear_usuario_data.php", {
                username: username,
                correo: correo,
                password: password
            }, function(resp) {
                if (resp.success) {
                    alert("Usuario creado");
                    $("#crearUsuarioModal").modal("hide");
                } else {
                    alert("Error: " + resp.message);
                }
            }, "json");
        } else if (modoModal === 'editar') {
            // Actualizar usuario actual
            $.post("user_options/actualizar_usuario_data.php", {
                id: usuarioActual.id,
                username: username,
                correo: correo
                // Si quieres permitir cambiar contraseña, descomenta:
                // password: password || null
            }, function(resp) {
                if (resp.success) {
                    alert("Perfil actualizado");
                    $("#crearUsuarioModal").modal("hide");
                    // Opcional: actualizar el nombre en la barra lateral
                    $('.user-image-back span').text('Hola, ' + username);
                } else {
                    alert("Error: " + resp.message);
                }
            }, "json");
        }
    });

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