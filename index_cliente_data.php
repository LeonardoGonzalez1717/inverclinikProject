<?php
session_start();
require_once 'connection/connection.php';

// Verificar que el cliente esté autenticado
if (!isset($_SESSION['idcliente']) || !isset($_SESSION['tipo']) || $_SESSION['tipo'] !== 'cliente') {
    header('Location: index.php');
    exit;
}

$idcliente = $_SESSION['idcliente'];

// Obtener información del cliente
$sqlCliente = "SELECT id, nombre, email FROM clientes WHERE id = ?";
$stmtCliente = $conn->prepare($sqlCliente);
$stmtCliente->bind_param("i", $idcliente);
$stmtCliente->execute();
$resultCliente = $stmtCliente->get_result();
$cliente = $resultCliente->fetch_assoc();
$stmtCliente->close();

// Verificar si existe la columna imagen, si no, agregarla
$checkColumn = $conn->query("SHOW COLUMNS FROM productos LIKE 'imagen'");
if ($checkColumn->num_rows == 0) {
    $conn->query("ALTER TABLE productos ADD COLUMN imagen VARCHAR(255) NULL AFTER descripcion");
}

// Obtener productos
$sql = "SELECT id, nombre, categoria, tipo_genero, descripcion, imagen, fecha_creacion FROM productos ORDER BY fecha_creacion DESC, nombre ASC";
$result = $conn->query($sql);
$productos = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $productos[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Productos - INVERCLINIK</title>
    <link rel="stylesheet" href="css/catalogo_cliente.css">
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>INVERCLINIK - Catálogo de Productos</h1>
            <div class="user-info">
                <p><strong>Bienvenido:</strong> <?= htmlspecialchars($cliente['nombre']) ?></p>
                <p><strong>Email:</strong> <?= htmlspecialchars($cliente['email']) ?></p>
                <div style="display: flex; gap: 10px; align-items: center; margin-top: 10px;">
                    <button class="btn-cart" id="btn-cart">
                        🛒 Carrito (<span id="cart-count">0</span>)
                    </button>
                    <a href="logout.php" class="btn-logout">Cerrar Sesión</a>
                </div>
            </div>
        </div>

        <h2 class="catalog-title">Nuestros Productos</h2>

        <?php if (empty($productos)): ?>
            <div class="no-products">
                <p>No hay productos disponibles en este momento.</p>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($productos as $producto): ?>
                    <div class="product-card" data-product-id="<?= $producto['id'] ?>">
                        <?php if (!empty($producto['imagen'])): ?>
                            <div class="product-image">
                                <img src="assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>" alt="<?= htmlspecialchars($producto['nombre']) ?>" onerror="this.src='assets/img/inverclinik_3.png';">
                                <button class="btn-preview" data-image-src="assets/img/productos/<?= htmlspecialchars($producto['imagen']) ?>" data-product-name="<?= htmlspecialchars($producto['nombre']) ?>">
                                    👁️ Vista Previa
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="product-image">
                                <img src="assets/img/inverclinik_3.png" alt="Sin imagen">
                                <button class="btn-preview" data-image-src="assets/img/inverclinik_3.png" data-product-name="<?= htmlspecialchars($producto['nombre']) ?>">
                                    👁️ Vista Previa
                                </button>
                            </div>
                        <?php endif; ?>
                        <h3><?= htmlspecialchars($producto['nombre']) ?></h3>
                        <div class="product-info">
                            <strong>Categoría:</strong> 
                            <span><?= htmlspecialchars($producto['categoria'] ?? 'N/A') ?></span>
                        </div>
                        <div class="product-info">
                            <strong>Género:</strong> 
                            <span><?= htmlspecialchars($producto['tipo_genero'] ?? 'N/A') ?></span>
                        </div>
                        <?php if (!empty($producto['descripcion'])): ?>
                            <div class="product-description">
                                <?= htmlspecialchars($producto['descripcion']) ?>
                            </div>
                        <?php endif; ?>
                        <button class="btn-add-cart" data-product-id="<?= $producto['id'] ?>" data-product-name="<?= htmlspecialchars($producto['nombre']) ?>" data-product-categoria="<?= htmlspecialchars($producto['categoria'] ?? 'N/A') ?>" data-product-genero="<?= htmlspecialchars($producto['tipo_genero'] ?? 'N/A') ?>">
                            Agregar al Carrito
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal del Carrito -->
    <div id="cart-modal" class="cart-modal">
        <div class="cart-modal-content">
            <div class="cart-modal-header">
                <h2>Carrito de Compras</h2>
                <button class="cart-close" id="close-cart">&times;</button>
            </div>
            <div class="cart-modal-body" id="cart-items">
                <!-- Los productos del carrito se mostrarán aquí -->
            </div>
            <div class="cart-modal-footer">
                <button class="btn-clear-cart" id="clear-cart">Vaciar Carrito</button>
                <button class="btn-checkout" id="checkout">Finalizar Compra</button>
            </div>
        </div>
    </div>

    <!-- Modal de Vista Previa de Imagen -->
    <div id="preview-modal" class="preview-modal">
        <div class="preview-modal-content">
            <div class="preview-modal-header">
                <h2 id="preview-product-name">Vista Previa</h2>
                <button class="preview-close" id="close-preview">&times;</button>
            </div>
            <div class="preview-modal-body">
                <img id="preview-image" src="" alt="Vista previa del producto">
            </div>
        </div>
    </div>

    <script>
        // Funcionalidad del Carrito
        let cart = JSON.parse(localStorage.getItem('cart')) || [];

        // Función para actualizar el contador del carrito
        function updateCartCount() {
            const count = cart.length;
            document.getElementById('cart-count').textContent = count;
        }

        // Función para agregar producto al carrito
        function addToCart(productId, productName, productCategoria, productGenero) {
            // Verificar si el producto ya está en el carrito
            const existingProduct = cart.find(item => item.id === productId);
            
            if (existingProduct) {
                // Si ya existe, no hacer nada, solo mostrar mensaje
                showNotification('El producto ya está en el carrito. Usa el botón + para aumentar la cantidad.');
                return;
            }
            
            // Si no existe, agregarlo con cantidad 1
            cart.push({
                id: productId,
                name: productName,
                categoria: productCategoria,
                genero: productGenero,
                quantity: 1
            });
            
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();
            showNotification('Producto agregado al carrito');
        }

        // Función para eliminar producto del carrito
        function removeFromCart(productId) {
            // Convertir productId a string para comparación consistente
            productId = String(productId);
            cart = cart.filter(item => String(item.id) !== productId);
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();
            renderCart();
        }

        // Función para actualizar cantidad
        function updateQuantity(productId, change) {
            // Convertir productId a string para comparación consistente
            productId = String(productId);
            const product = cart.find(item => String(item.id) === productId);
            if (product) {
                product.quantity += change;
                if (product.quantity <= 0) {
                    removeFromCart(productId);
                    return;
                }
                localStorage.setItem('cart', JSON.stringify(cart));
                updateCartCount();
                renderCart();
            }
        }

        // Función para renderizar el carrito
        function renderCart() {
            const cartItems = document.getElementById('cart-items');
            
            if (cart.length === 0) {
                cartItems.innerHTML = '<p class="cart-empty">Tu carrito está vacío</p>';
                return;
            }
            
            cartItems.innerHTML = cart.map(item => `
                <div class="cart-item" data-product-id="${item.id}">
                    <div class="cart-item-info">
                        <h4>${item.name}</h4>
                        <p>Categoría: ${item.categoria} | Género: ${item.genero}</p>
                    </div>
                    <div class="cart-item-controls">
                        <button class="btn-quantity btn-decrease" data-product-id="${item.id}">-</button>
                        <span class="quantity">${item.quantity}</span>
                        <button class="btn-quantity btn-increase" data-product-id="${item.id}">+</button>
                        <button class="btn-remove" data-product-id="${item.id}">Eliminar</button>
                    </div>
                </div>
            `).join('');
            
            // Agregar event listeners a los botones recién creados
            attachCartEventListeners();
        }
        
        // Función para agregar event listeners a los botones del carrito
        function attachCartEventListeners() {
            // Botones de aumentar cantidad
            document.querySelectorAll('.btn-increase').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    updateQuantity(productId, 1);
                });
            });
            
            // Botones de disminuir cantidad
            document.querySelectorAll('.btn-decrease').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    updateQuantity(productId, -1);
                });
            });
            
            // Botones de eliminar
            document.querySelectorAll('.btn-remove').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    removeFromCart(productId);
                });
            });
        }

        // Función para mostrar notificación
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 2000);
        }

        // Event Listeners
        document.addEventListener('DOMContentLoaded', function() {
            updateCartCount();
            
            // Agregar al carrito
            document.querySelectorAll('.btn-add-cart').forEach(btn => {
                btn.addEventListener('click', function() {
                    const productId = this.getAttribute('data-product-id');
                    const productName = this.getAttribute('data-product-name');
                    const productCategoria = this.getAttribute('data-product-categoria');
                    const productGenero = this.getAttribute('data-product-genero');
                    addToCart(productId, productName, productCategoria, productGenero);
                });
            });
            
            // Abrir modal del carrito
            document.getElementById('btn-cart').addEventListener('click', function() {
                renderCart();
                document.getElementById('cart-modal').style.display = 'flex';
            });
            
            // Cerrar modal del carrito
            document.getElementById('close-cart').addEventListener('click', function() {
                document.getElementById('cart-modal').style.display = 'none';
            });
            
            // Cerrar al hacer click fuera del modal
            document.getElementById('cart-modal').addEventListener('click', function(e) {
                if (e.target.id === 'cart-modal') {
                    this.style.display = 'none';
                }
            });
            
            // Vaciar carrito
            document.getElementById('clear-cart').addEventListener('click', function() {
                if (confirm('¿Estás seguro de vaciar el carrito?')) {
                    cart = [];
                    localStorage.setItem('cart', JSON.stringify(cart));
                    updateCartCount();
                    renderCart();
                }
            });
            
            // Finalizar compra
            document.getElementById('checkout').addEventListener('click', function() {
                if (cart.length === 0) {
                    alert('Tu carrito está vacío');
                    return;
                }
                alert('Funcionalidad de compra en desarrollo. Productos en el carrito: ' + cart.length);
            });

            // Vista previa de imagen
            document.querySelectorAll('.btn-preview').forEach(btn => {
                btn.addEventListener('click', function() {
                    const imageSrc = this.getAttribute('data-image-src');
                    const productName = this.getAttribute('data-product-name');
                    
                    document.getElementById('preview-image').src = imageSrc;
                    document.getElementById('preview-product-name').textContent = productName;
                    document.getElementById('preview-modal').style.display = 'flex';
                });
            });

            // Cerrar modal de vista previa
            document.getElementById('close-preview').addEventListener('click', function() {
                document.getElementById('preview-modal').style.display = 'none';
            });

            // Cerrar modal de vista previa al hacer click fuera
            document.getElementById('preview-modal').addEventListener('click', function(e) {
                if (e.target.id === 'preview-modal') {
                    this.style.display = 'none';
                }
            });

            // Cerrar modal de vista previa con tecla ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    document.getElementById('preview-modal').style.display = 'none';
                }
            });
        });
    </script>

    <?php
    $conn->close();
    ?>
</body>
</html>

