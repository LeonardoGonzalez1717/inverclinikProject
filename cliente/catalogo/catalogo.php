<?php
session_start();
$isLoggedIn = isset($_SESSION['id_cliente']);
$nombreCliente = $isLoggedIn ? $_SESSION['nombre_cliente'] : "Invitado";
// Asegúrate de que la ruta a modales_cliente.php sea correcta
include '../modales_cliente.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>INVERCLINIK - Catálogo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/catalogo_cliente.css">
</head>
<body>

<div class="container">
    <header class="header">
        <h1>INVERCLINIK</h1>
        <div class="user-info">
            <p>Bienvenido, <strong><?php echo $nombreCliente; ?></strong></p>
            <?php if($isLoggedIn): ?>
                <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
            <?php endif; ?>
        </div>
    </header>

    <h2 class="catalog-title">Catálogo</h2>

    <div class="main-layout">
        <div class="products-grid">
            
            <div class="product-card">
                <div class="product-image">
                    <img src="assets/img/uniforme1.jpg" alt="Bata Lab">
                    <button class="btn-preview">Vista Previa</button>
                </div>
                <h3>Bata de Laboratorio</h3>
                <div class="product-info"><strong>Precio: $25.00</strong></div>
                <p class="product-description">Tela gabardina, alta resistencia.</p>
                <div style="margin: 15px 0; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <label>Cant:</label>
                    <input type="number" id="cant-1" class="quantity-input" value="1" min="1" style="width: 50px;">
                </div>
                <button class="btn-add-cart" onclick="agregarAlPedido('Bata Lab', 25.00, 'cant-1')">+ Añadir</button>
            </div>

            <div class="product-card">
                <div class="product-image">
                    <img src="assets/img/uniforme2.jpg" alt="Filipina">
                    <button class="btn-preview">Vista Previa</button>
                </div>
                <h3>Bata Médica</h3>
                <div class="product-info"><strong>Precio: $18.00</strong></div>
                <p class="product-description">Corte ergonómico y antifluidos.</p>
                <div style="margin: 15px 0; display: flex; align-items: center; justify-content: center; gap: 10px;">
                    <label>Cant:</label>
                    <input type="number" id="cant-2" class="quantity-input" value="1" min="1" style="width: 50px;">
                </div>
                <button class="btn-add-cart" onclick="agregarAlPedido('Filipina Médica', 18.00, 'cant-2')">+ Añadir</button>
            </div>

            </div>

        <aside class="sidebar-pedido">
            <h3 style="color: #005bbe; margin-bottom: 15px; border-bottom: 2px solid #eee; padding-bottom: 10px;">
                Mi Pedido
            </h3>
            <div id="lista-pedido">
                <p id="vacio-msg" style="color: #999; text-align: center; padding-top: 30px;">Tu carrito está vacío</p>
            </div>
            <div style="border-top: 2px solid #eee; padding-top: 15px; margin-top: 10px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 20px;">
                    <span style="font-weight: bold; font-size: 1.2rem;">Total:</span>
                    <span id="total-pedido" style="font-weight: bold; font-size: 1.2rem; color: #005bbe;">$0.00</span>
                </div>
                <?php if(!$isLoggedIn): ?>
                    <button type="button" class="btn-add-cart" style="background-color: #6c757d;" onclick="abrirModalAcceso()">Inicie Sesión para Comprar</button>
                <?php else: ?>
                    <button id="btn-comprar" class="btn-checkout" onclick="enviarPedido()" style="width:100%; padding:10px; background:#28a745; color:white; border:none; border-radius:6px; cursor:pointer; font-weight:bold;">Confirmar Orden</button>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>

<div id="notificacion" class="notification">¡Producto añadido!</div>
</body>
</html>

<script src="../../assets/js/jquery-3.7.1.min.js"></script>
<script>
    // Variable global del pedido
    let pedido = [];

    function agregarAlPedido(nombre, precio, inputId) {
        const input = document.getElementById(inputId);
        const cantidad = parseInt(input.value);
        
        if(cantidad < 1 || isNaN(cantidad)) return;

        const productoExistente = pedido.find(item => item.nombre === nombre);

        if (productoExistente) {
            productoExistente.cantidad += cantidad;
            productoExistente.subtotal = productoExistente.cantidad * productoExistente.precioUnit;
        } else {
            pedido.push({
                id: Date.now(),
                nombre: nombre,
                cantidad: cantidad,
                precioUnit: precio,
                subtotal: precio * cantidad
            });
        }

        input.value = 1; 
        mostrarNotificacion(`Agregado: ${cantidad}x ${nombre}`);
        actualizarVista();
    }

    function eliminarDelPedido(id) {
        pedido = pedido.filter(item => item.id !== id);
        actualizarVista();
    }

    function actualizarVista() {
        const listaContainer = document.getElementById('lista-pedido');
        const totalTxt = document.getElementById('total-pedido');
        
        listaContainer.innerHTML = "";
        let sumaTotal = 0;

        if (pedido.length === 0) {
            listaContainer.innerHTML = '<p id="vacio-msg" style="color: #999; text-align: center; padding-top: 30px;">Tu carrito está vacío</p>';
            totalTxt.innerText = "$0.00";
            return;
        }

        pedido.forEach(item => {
            sumaTotal += item.subtotal;
            listaContainer.innerHTML += `
                <div class="cart-item" style="display:flex; justify-content:space-between; align-items:center; padding:10px 0; border-bottom:1px solid #eee;">
                    <div style="flex-grow: 1;">
                        <h4 style="margin:0; font-size:14px; color:#333;">${item.nombre}</h4>
                        <small style="color:#666;">${item.cantidad} x $${item.precioUnit.toFixed(2)}</small>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <strong style="color:#005bbe;">$${item.subtotal.toFixed(2)}</strong>
                        <button onclick="eliminarDelPedido(${item.id})" style="background:none; border:none; color:#dc3545; cursor:pointer; font-size:18px;">&times;</button>
                    </div>
                </div>`;
        });

        totalTxt.innerText = `$${sumaTotal.toFixed(2)}`;
    }

    function mostrarNotificacion(msj) {
        const n = document.getElementById('notificacion');
        n.innerText = msj;
        n.classList.add('show');
        setTimeout(() => n.classList.remove('show'), 2500);
    }

    function abrirModalAcceso() {
        $("#modal-cliente").css("display", "flex");
    }

    function enviarPedido() {
        if(pedido.length === 0) return alert("Carrito vacío");
        alert("Orden enviada a producción con éxito.");
        pedido = [];
        actualizarVista();
    }
</script>