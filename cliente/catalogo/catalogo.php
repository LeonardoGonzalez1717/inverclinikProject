<?php
session_start();
include '../../connection/connection.php';

$isLoggedIn = (isset($_SESSION['id_cliente']) || isset($_SESSION['iduser']));
$tipoUsuario = $_SESSION['tipo'] ?? '';
$role_id = isset($_SESSION['role_id']) ? (int)$_SESSION['role_id'] : 0;

$isAdmin = ($tipoUsuario === 'usuario' && $role_id === 1);

if ($tipoUsuario === 'cliente' || $role_id === 3) {
    $nombreUsuario = $_SESSION['nombre_cliente'] ?? 'Cliente';
    $urlInicio = '../../dashboard/dashboard_cliente.php';
} elseif ($isAdmin) {
    $nombreUsuario = $_SESSION['username'] ?? 'Admin';
    $urlInicio = '../../dashboard/dashboard.php';
} else {
    $nombreUsuario = "Invitado";
    $urlInicio = '../../index.php';
}
include '../modales_cliente.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>INVERCLINIK - Catálogo por Tallas</title>
    <script src="../../assets/js/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/select2.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/catalogo_cliente.css">
    <link rel="stylesheet" href="../../assets/css/all.min.css">
</head>
<body>

<div class="container">
    <header class="header">
        <div class="logo">
           <a href="<?php echo $urlInicio; ?>"> <h1>INVERCLINIK</h1></a>
        </div>
        <div class="user-info">
            <p>Bienvenido, <strong><?php echo $nombreUsuario; ?></strong></p>
            <?php if($isLoggedIn): ?>
                <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
            <?php endif; ?>
        </div>
    </header>

    <h2 class="catalog-title">Catálogo de Productos</h2>

    <div class="main-layout">
        <div class="products-grid">
            <?php
            $sql = "SELECT 
                        r.id AS id_receta, 
                        p.nombre, 
                        p.imagen, 
                        p.descripcion, 
                        t.nombre_rango AS nombre_talla, 
                        r.precio_detal, 
                        r.precio_mayor  
                    FROM recetas r
                    INNER JOIN productos p ON r.producto_id = p.id
                    INNER JOIN rangos_tallas t ON r.rango_tallas_id = t.id
                    WHERE p.activo = 1";
            
            $result = mysqli_query($conn, $sql);
            while($p = mysqli_fetch_assoc($result)):
                $nombreCompleto = $p['nombre'] . " (Talla: " . $p['nombre_talla'] . ")";
            ?>
            <div class="product-card" id="card-receta-<?php echo $p['id_receta']; ?>" style="position: relative;">
                <?php if($isAdmin): ?>
                <div class="admin-actions" style="position: absolute; top: 10px; right: 10px; z-index: 5;">
                    <button class="btn-delete" onclick="eliminarReceta(<?php echo $p['id_receta']; ?>)" title="Quitar del catálogo" style="background:#dc3545; border:none; padding:5px 10px; border-radius:4px; color:white; cursor:pointer;"><i class="fas fa-trash"></i></button>
                </div>
                <?php endif; ?>

                <div class="product-image">
                    <img src="<?php echo $p['imagen']; ?>" alt="<?php echo $p['nombre']; ?>">
                </div>
                
                <h3><?php echo $p['nombre']; ?></h3>
                <p style="color: #005bbe; font-weight: bold; margin: 5px 0;">Talla: <?php echo $p['nombre_talla']; ?></p>
                
                <div class="product-info"><strong>$<?php echo number_format($p['precio_detal'], 2); ?></strong></div>
                <p class="product-description"><?php echo $p['descripcion']; ?></p>
                
                <div class="product-controls">
                    <label>Cant:</label>
                    <input type="number" id="cant-<?php echo $p['id_receta']; ?>" class="quantity-input" value="1" min="1">
                </div>
                
                <button class="btn-add-cart" 
                    onclick="agregarAlPedido('<?php echo $nombreCompleto; ?>', <?php echo $p['precio_detal']; ?>, <?php echo $p['precio_mayor']; ?>, 'cant-<?php echo $p['id_receta']; ?>', <?php echo $p['id_receta']; ?>)">
                    <i class="fas fa-cart-plus"></i> Añadir
                </button>
            </div>
            <?php endwhile; ?>
        </div>

        <?php if(!$isAdmin): ?>
            <aside class="sidebar-pedido">
                <h3>Mi Pedido</h3>
                <div id="lista-pedido">
                    <p id="vacio-msg">Tu carrito está vacío</p>
                </div>
                <div class="cart-total-section">
                    <div class="total-row">
                        <span>Total:</span>
                        <span id="total-pedido">$0.00</span>
                    </div>
                    <?php if(!$isLoggedIn): ?>
                        <button type="button" class="btn-add-cart" style="background-color: #6c757d;" onclick="abrirModalAcceso()">
                            Inicie Sesión para Comprar
                        </button>
                    <?php else: ?>
                        <button id="btn-whatsapp" type="button" onclick="enviarPedido()">
                            <i class="fab fa-whatsapp"></i> Confirmar por WhatsApp
                        </button>
                    <?php endif; ?>
                </div>
            </aside>
        <?php endif; ?>
    </div>
</div>

<div id="notificacion" class="notification">¡Producto añadido!</div>

<script>
    let pedido = [];

    function abrirModalAcceso() {
        const modal = document.getElementById('modalAcceso');
        if(modal) {
            modal.style.display = 'block';
        } else {
            alert("Por favor, inicie sesión o regístrese para continuar.");
            window.location.href = "../../index.php";
        }
    }

    function agregarAlPedido(nombre, pDetal, pMayor, inputId, recetaId) {
        const input = document.getElementById(inputId);
        const cantidad = parseInt(input.value);
        if(cantidad < 1 || isNaN(cantidad)) return;

        const productoExistente = pedido.find(item => item.recetaId === recetaId);
        
        if (productoExistente) {
            productoExistente.cantidad += cantidad;
        } else {
            pedido.push({
                recetaId: recetaId,
                nombre: nombre,
                cantidad: cantidad,
                pDetal: pDetal,
                pMayor: pMayor,
                precioUnit: pDetal,
                subtotal: 0
            });
        }
        
        input.value = 1; 
        aplicarReglaDePrecios(); 
        actualizarVista();
        mostrarNotificacion("¡" + nombre + " añadido!");
    }

    function quitarDelPedido(recetaId) {
        pedido = pedido.filter(item => item.recetaId !== recetaId);
        aplicarReglaDePrecios();
        actualizarVista();
    }

    function aplicarReglaDePrecios() {
        const totalProductos = pedido.reduce((acc, item) => acc + item.cantidad, 0);
        const esPrecioMayor = totalProductos >= 12;

        pedido.forEach(item => {
            item.precioUnit = esPrecioMayor ? item.pMayor : item.pDetal;
            item.subtotal = item.cantidad * item.precioUnit;
        });
    }

    function actualizarVista() {
        const listaContainer = document.getElementById('lista-pedido');
        const totalTxt = document.getElementById('total-pedido');
        listaContainer.innerHTML = "";
        let sumaTotal = 0;

        if (pedido.length === 0) {
            listaContainer.innerHTML = '<p id="vacio-msg">Tu carrito está vacío</p>';
            totalTxt.innerText = "$0.00";
            return;
        }

        pedido.forEach(item => {
            sumaTotal += item.subtotal;
            listaContainer.innerHTML += `
                <div class="cart-item" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">
                    <div style="flex:1;">
                        <h4 style="margin:0; font-size:0.85rem;">${item.nombre}</h4>
                        <small>${item.cantidad} x $${item.precioUnit.toFixed(2)}</small>
                    </div>
                    <div style="text-align:right; display:flex; align-items:center; gap:10px;">
                        <strong>$${item.subtotal.toFixed(2)}</strong>
                        <button onclick="quitarDelPedido(${item.recetaId})" style="background:none; border:none; color:#dc3545; cursor:pointer; font-size:1.1rem;" title="Quitar">
                            <i class="fas fa-times-circle"></i>
                        </button>
                    </div>
                </div>`;
        });
        totalTxt.innerText = `$${sumaTotal.toFixed(2)}`;
    }

    function enviarPedido() {
        if(pedido.length === 0) return alert("Carrito vacío");
        const totalCalculado = pedido.reduce((acc, item) => acc + item.subtotal, 0);

        $.ajax({
            url: '../guardar_presupuesto.php',
            method: 'POST',
            data: { 
                carrito: pedido, 
                total: totalCalculado 
            },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    alert("Presupuesto " + res.correlativo + " generado con éxito.");
                    enviarWhatsApp(res.correlativo);
                    pedido = [];
                    actualizarVista();
                } else {
                    alert("Error: " + res.message);
                }
            }
        });
    }

    function enviarWhatsApp(correlativo) {
        const telefonoAdmin = "584243084640"; 
        const nombreParaMensaje = "<?php echo $nombreUsuario; ?>";
        
        let mensaje = "¡Hola! Soy *" + nombreParaMensaje + "*.\n";
        mensaje += "📝 *PRESUPUESTO:* `" + correlativo + "`\n\n";
        mensaje += "✅ *DETALLE DEL PEDIDO*\n";
        mensaje += "-----------------------------\n";

        pedido.forEach(item => {
            mensaje += `• ${item.nombre}\n`;
            mensaje += `   Cant: ${item.cantidad} x $${item.precioUnit.toFixed(2)}\n`;
            mensaje += `   Subtotal: $${item.subtotal.toFixed(2)}\n\n`;
        });

        const totalText = document.getElementById('total-pedido').innerText;
        mensaje += "-----------------------------\n";
        mensaje += "💰 *TOTAL ESTIMADO: " + totalText + "*\n\n";
        mensaje += "Referencia: " + correlativo;

        const urlWhatsapp = "https://api.whatsapp.com/send?phone=" + telefonoAdmin + "&text=" + encodeURIComponent(mensaje);
        window.open(urlWhatsapp, '_blank');
    }

    function mostrarNotificacion(msj) {
        const n = document.getElementById('notificacion');
        n.innerText = msj;
        n.classList.add('show');
        setTimeout(() => n.classList.remove('show'), 2500);
    }
</script>
</body>
</html>