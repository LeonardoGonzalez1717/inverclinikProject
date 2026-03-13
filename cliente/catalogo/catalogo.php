<?php
session_start();
$isLoggedIn = isset($_SESSION['id_cliente']);
$nombreCliente = $isLoggedIn ? $_SESSION['nombre_cliente'] : "Invitado";
include '../modales_cliente.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>INVERCLINIK - Catálogo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/catalogo_cliente.css">
    <link rel="stylesheet" href="../../assets/css/all.min.css">
</head>
<body>

<div class="container">
    <header class="header">
        <div class="logo">
           <a href="<?php echo (!$isLoggedIn) ? '../../index.php' : '../dashboard_cliente.php';?>"> <h1>INVERCLINIK</h1></a>
        </div>
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
                <div class="product-info"><strong>$25.00</strong></div>
                <p class="product-description">Tela gabardina de alta resistencia, ideal para uso rudo.</p>
                <div class="product-controls">
                    <label>Cant:</label>
                    <input type="number" id="cant-1" class="quantity-input" value="1" min="1">
                </div>
                <button class="btn-add-cart" onclick="agregarAlPedido('Bata Lab', 25.00, 'cant-1')">
                    <i class="fas fa-cart-plus"></i> Añadir al carrito
                </button>
            </div>

            <div class="product-card">
                <div class="product-image">
                    <img src="assets/img/uniforme2.jpg" alt="Filipina">
                    <button class="btn-preview">Vista Previa</button>
                </div>
                <h3>Filipina Médica</h3>
                <div class="product-info"><strong>$18.00</strong></div>
                <p class="product-description">Corte ergonómico, tela antifluidos y fresca.</p>
                <div class="product-controls">
                    <label>Cant:</label>
                    <input type="number" id="cant-2" class="quantity-input" value="1" min="1">
                </div>
                <button class="btn-add-cart" onclick="agregarAlPedido('Filipina Médica', 18.00, 'cant-2')">
                    <i class="fas fa-cart-plus"></i> Añadir al carrito
                </button>
            </div>

        </div>

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
    </div>
</div>

<div id="notificacion" class="notification">¡Producto añadido!</div>

<script src="../../assets/js/jquery-3.7.1.min.js"></script>
<script>
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

    function enviarPedido() {
        if(pedido.length === 0) return alert("Carrito vacío");

        const totalCalculado = pedido.reduce((acc, item) => acc + item.subtotal, 0);

        $.ajax({
            url: '../guardar_presupuesto.php',
            method: 'POST',
            data: {
                carrito: pedido, // El array con los productos
                total: totalCalculado
            },
            success: function(response) {
                console.log("Respuesta del servidor:", response);
                try {
                    const res = (typeof response === 'object') ? response : JSON.parse(response);
                    
                    if(res.status === 'success') {
                        alert("Presupuesto " + res.correlativo + " guardado con éxito.");
                        
                        enviarWhatsApp(res.correlativo);
                        
                        pedido = [];
                        actualizarVista();
                    } else {
                        alert("Error del servidor: " + res.message);
                    }
                } catch (e) {
                    console.error("Error de parsing:", e);
                    // Esto te mostrará el error real en una ventana
                    alert("El servidor respondió algo que no es JSON. Revisa la consola (F12).");
                }
            }
        });
    }

    function enviarWhatsApp(correlativo) {
        const telefonoAdmin = "584243084640"; 
        const nombreCliente = "<?php echo $nombreCliente; ?>";
        
        let mensaje = "¡Hola! Soy *" + nombreCliente + "*.\n";
        mensaje += "📝 *PRESUPUESTO:* `" + correlativo + "`\n\n";
        mensaje += "✅ *DETALLE DEL PEDIDO*\n";
        mensaje += "-----------------------------\n";

        pedido.forEach(item => {
            mensaje += `• ${item.nombre}\n`;
            mensaje += `  Cant: ${item.cantidad} x $${item.precioUnit.toFixed(2)}\n`;
            mensaje += `  Subtotal: $${item.subtotal.toFixed(2)}\n\n`;
        });

        const totalText = document.getElementById('total-pedido').innerText;
        mensaje += "-----------------------------\n";
        mensaje += "💰 *TOTAL ESTIMADO: " + totalText + "*\n\n";
        mensaje += "Deseo coordinar tallas y personalización. Referencia: " + correlativo;

        const urlWhatsapp = "https://api.whatsapp.com/send?phone=" + telefonoAdmin + "&text=" + encodeURIComponent(mensaje);
        window.open(urlWhatsapp, '_blank');
    }

    // function enviarPedido() {
    //     if(pedido.length === 0) return alert("Carrito vacío");

    //     const totalCalculado = pedido.reduce((acc, item) => acc + item.subtotal, 0);

    //     $.ajax({
    //         url: '../guardar_presupuesto.php',
    //         method: 'POST',
    //         data: {
    //             carrito: pedido,
    //             total: totalCalculado
    //         },
    //         success: function(response) {
    //             const res = JSON.parse(response);
    //             if(res.status === 'success') {
    //                 alert("Presupuesto " + res.correlativo + " guardado con éxito.");
                    
    //                 enviarWhatsApp(res.correlativo);
                    
    //                 pedido = [];
    //                 actualizarVista();
    //             }
    //         }
    //     });
    // }

    // function enviarWhatsApp(correlativo) {
    //     const telefonoAdmin = "584243084640"; 
    //     const nombreCliente = "<?php echo $nombreCliente; ?>";
        
    //     let mensaje = "¡Hola! Soy *" + nombreCliente + "*, me gustaría realizar el siguiente pedido:\n\n";
    //     mensaje += "✅ *DETALLE DEL PEDIDO*\n";
    //     mensaje += "-----------------------------\n";

    //     pedido.forEach(item => {
    //         mensaje += `• ${item.nombre}\n`;
    //         mensaje += `  Cant: ${item.cantidad} x $${item.precioUnit.toFixed(2)}\n`;
    //         mensaje += `  Subtotal: $${item.subtotal.toFixed(2)}\n\n`;
    //     });

    //     const totalCalculado = document.getElementById('total-pedido').innerText;
    //     mensaje += "-----------------------------\n";
    //     mensaje += "💰 *TOTAL ESTIMADO: " + totalCalculado + "*\n\n";
    //     mensaje += "Deseo coordinar tallas, colores y personalización. ¡Gracias!";

    //     const urlWhatsapp = "https://api.whatsapp.com/send?phone=" + telefonoAdmin + "&text=" + encodeURIComponent(mensaje);
    //     window.open(urlWhatsapp, '_blank');
    // }

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
            listaContainer.innerHTML = '<p id="vacio-msg">Tu carrito está vacío</p>';
            totalTxt.innerText = "$0.00";
            return;
        }

        pedido.forEach(item => {
            sumaTotal += item.subtotal;
            listaContainer.innerHTML += `
                <div class="cart-item">
                    <div>
                        <h4>${item.nombre}</h4>
                        <small>${item.cantidad} x $${item.precioUnit.toFixed(2)}</small>
                    </div>
                    <div style="display:flex; align-items:center; gap:10px;">
                        <strong>$${item.subtotal.toFixed(2)}</strong>
                        <button onclick="eliminarDelPedido(${item.id})" style="color:red; border:none; background:none; cursor:pointer;">&times;</button>
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
</script>
</body>
</html>