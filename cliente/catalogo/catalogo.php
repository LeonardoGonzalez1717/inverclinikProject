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
    $urlInicio = '../../index.php'; // Si es invitado, va al login principal
}
include '../modales_cliente.php';
?>
<style>
    /* Contenedor principal del Modal */
.modal {
    display: none; 
    position: fixed; 
    z-index: 2000; /* Por encima de todo */
    left: 0; top: 0;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.6); /* Fondo oscurecido más elegante */
    backdrop-filter: blur(4px); /* Efecto de desenfoque al fondo */
    align-items: center; 
    justify-content: center;
    animation: fadeIn 0.3s ease;
}

/* La tarjeta blanca interna */
.container-inner {
    background: #ffffff;
    width: 100%;
    max-width: 450px;
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
    position: relative;
    border-top: 5px solid #005bbe; /* Línea de color corporativo */
}

.main-title {
    color: #333;
    font-size: 1.5rem;
    margin-bottom: 20px;
    text-align: center;
    font-weight: 700;
}

/* Estilos para las etiquetas e inputs */
.mb-3 { margin-bottom: 1.5rem; }

.mb-3 label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: #555;
    font-size: 0.9rem;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 1rem;
    transition: border-color 0.3s;
}

/* Estilo especial para campos de solo lectura */
.form-control[readonly] {
    background-color: #f8f9fa !important;
    color: #777;
    border: 1px dashed #ccc;
}

/* Botones */
.btn-group {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}

.btn-publish {
    flex: 2;
    background-color: #005bbe;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 8px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.3s;
}

.btn-publish:hover { background-color: #004494; }

.btn-close-modal {
    flex: 1;
    background-color: #6c757d;
    color: white;
    border: none;
    padding: 12px;
    border-radius: 8px;
    cursor: pointer;
}

/* Animación de entrada */
@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-20px); }
    to { opacity: 1; transform: translateY(0); }
}
</style>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>INVERCLINIK - Catálogo</title>
    <script src="../../assets/js/jquery-3.7.1.min.js"></script>
    <link rel="stylesheet" href="../../assets/css/select2.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/catalogo_cliente.css">
    <link rel="stylesheet" href="../../assets/css/all.min.css">
    <script>
        function abrirModalAdmin() {
            console.log("Intentando abrir modal...");
            var modal = document.getElementById('modal-admin-producto');
            if(modal) {
                modal.style.display = 'flex';
                // Si jQuery y Select2 están listos, inicializar
                if (window.jQuery && jQuery.fn.select2) {
                    $('#select-producto-base').select2({
                        dropdownParent: $('#modal-admin-producto')
                    });
                }
            } else {
                alert("Error: El modal no existe en el HTML. Revisa si eres Admin.");
            }
        }
    </script>
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

    <h2 class="catalog-title">Catálogo</h2>

    <div class="main-layout">
        <div class="products-grid">
            <?php if($isAdmin): ?>
            <div class="product-card add-new-card" onclick="abrirModalAdmin()" style="border: 2px dashed #005bbe; display: flex; align-items: center; justify-content: center; cursor: pointer;">
                <div style="text-align: center; color: #005bbe;">
                    <i class="fas fa-plus-circle" style="font-size: 3rem;"></i>
                    <p>Agregar Producto al Catálogo</p>
                </div>
            </div>
            <?php endif; ?>

            <?php
            $result = mysqli_query($conn, "SELECT * FROM productos where activo = 1");
            while($p = mysqli_fetch_assoc($result)):
            ?>
            <div class="product-card" id="card-<?php echo $p['id']; ?>">
                <?php if($isAdmin): ?>
                <div class="admin-actions" style="position: absolute; top: 10px; right: 10px; z-index: 5;">
                    <button class="btn-delete" onclick="eliminarTarjeta(<?php echo $p['id']; ?>)" style="background:#dc3545; border:none; padding:5px 10px; border-radius:4px; color:white; cursor:pointer;"><i class="fas fa-trash"></i></button>
                </div>
                <?php endif; ?>

                <div class="product-image">
                    <img src="<?php echo $p['imagen']; ?>" alt="<?php echo $p['nombre']; ?>">
                    <button class="btn-preview">Vista Previa</button>
                </div>
                <h3><?php echo $p['nombre']; ?></h3>
                <div class="product-info"><strong>$<?php echo number_format($p['precio_unitario'], 2); ?></strong></div>
                <p class="product-description"><?php echo $p['descripcion']; ?></p>
                
                <div class="product-controls">
                    <label>Cant:</label>
                    <input type="number" id="cant-<?php echo $p['id']; ?>" class="quantity-input" value="1" min="1">
                </div>
                
                <button class="btn-add-cart" onclick="agregarAlPedido('<?php echo $p['nombre']; ?>', <?php echo $p['precio_unitario']; ?>, 'cant-<?php echo $p['id']; ?>', <?php echo $p['id']; ?>)">
                    <i class="fas fa-cart-plus"></i> Añadir al carrito
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

<?php if($isAdmin): ?>
<div id="modal-admin-producto" class="modal">
    <div class="container-inner">
        <h2 class="main-title"><i class="fas fa-box-open"></i> Activar Producto</h2>
        
        <form id="form-admin-catalogo">
            <input type="hidden" name="action" value="guardar_tarjeta">
            
            <div class="mb-3">
                <label><i class="fas fa-search"></i> Buscar en Inventario:</label>
                <select id="select-producto-base" name="id_producto_base" class="form-control" required>
                    <option value="">Seleccione un producto...</option>
                    <?php
                    $resBase = mysqli_query($conn, "SELECT id, nombre, precio_unitario FROM productos WHERE activo = 0 ORDER BY nombre ASC");
                    while($b = mysqli_fetch_assoc($resBase)){
                        echo "<option value='".$b['id']."' data-nombre='".$b['nombre']."' data-precio='".$b['precio_unitario']."'>".$b['nombre']."</option>";
                    }
                    ?>
                </select>
            </div>

            <div class="mb-3">
                <label>Nombre (Informativo):</label>
                <input type="text" id="admin-nombre" class="form-control" readonly placeholder="Esperando selección...">
            </div>

            <div class="mb-3">
                <label>Precio Unitario:</label>
                <input type="text" id="admin-precio" class="form-control" readonly placeholder="$ 0.00">
            </div>

            <div class="btn-group">
                <button type="submit" class="btn-publish">
                    <i class="fas fa-check-circle"></i> Publicar en Catálogo
                </button>
                <button type="button" class="btn-close-modal" onclick="document.getElementById('modal-admin-producto').style.display='none'">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function abrirModalAcceso() {
        $("#modal-cliente").css("display", "flex");
    }

    $(document).ready(function() {
        // 1. AUTOCOMPLETAR CAMPOS AL ELEGIR PRODUCTO
        $('#select-producto-base').on('change', function() {
            const seleccionado = $(this).find(':selected');
            const nombre = seleccionado.data('nombre');
            const precio = seleccionado.data('precio'); 

            $('#admin-nombre').val(nombre);
            $('#admin-precio').val(precio);
        });

        // 2. GUARDAR / ACTIVAR PRODUCTO (ESTATUS 1)
        $('#form-admin-catalogo').on('submit', function(e) {
            e.preventDefault(); 
            const datos = $(this).serialize(); 

            $.ajax({
                url: 'catalogo_data.php',
                method: 'POST',
                data: datos,
                dataType: 'json',
                success: function(res) {
                    if(res.success) {
                        alert("¡Producto publicado con éxito!");
                        location.reload();
                    } else {
                        alert("Error: " + res.message);
                    }
                },
                error: function() {
                    alert("Error de comunicación con el servidor.");
                }
            });
        });
    });

    function eliminarTarjeta(id) {
        if(confirm('¿Deseas quitar este producto del catálogo?')) {
            $.post('catalogo_data.php', { action: 'eliminar', id: id }, function(res) {
                if(res.success) {
                    $(`#card-${id}`).fadeOut(500, function() { $(this).remove(); });
                } else {
                    alert("Error al ocultar: " + res.message);
                }
            }, 'json');
        }
    }

    let pedido = [];
    function agregarAlPedido(nombre, precio, inputId, prodId) {
        const input = document.getElementById(inputId);
        const cantidad = parseInt(input.value);
        if(cantidad < 1 || isNaN(cantidad)) return;

        const productoExistente = pedido.find(item => item.prodId === prodId);
        if (productoExistente) {
            productoExistente.cantidad += cantidad;
            productoExistente.subtotal = productoExistente.cantidad * productoExistente.precioUnit;
        } else {
            pedido.push({
                id: Date.now(),
                prodId: prodId,
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
                <div class="cart-item" style="display:flex; justify-content:space-between; margin-bottom:10px; border-bottom:1px solid #eee; padding-bottom:5px;">
                    <div>
                        <h4 style="margin:0; font-size:0.9rem;">${item.nombre}</h4>
                        <small>${item.cantidad} x $${item.precioUnit.toFixed(2)}</small>
                    </div>
                    <strong>$${item.subtotal.toFixed(2)}</strong>
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
            data: { carrito: pedido, total: totalCalculado },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    alert("Presupuesto " + res.correlativo + " guardado.");
                    enviarWhatsApp(res.correlativo);
                    pedido = [];
                    actualizarVista();
                }
            }
        });
    }

    function enviarWhatsApp(correlativo) {
        const telefonoAdmin = "584243084640"; 
        // Usamos la variable de PHP directamente
        const nombreParaMensaje = "<?php echo $nombreUsuario; ?>";
        
        let mensaje = "¡Hola! Soy *" + nombreParaMensaje + "*.\n";
        mensaje += "📝 *PRESUPUESTO:* `" + correlativo + "`\n\n";
        mensaje += "✅ *DETALLE DEL PEDIDO*\n";
        mensaje += "-----------------------------\n";

        // Nota: El array 'pedido' debe tener datos antes de limpiar el carrito
        pedido.forEach(item => {
            mensaje += `• ${item.nombre}\n`;
            mensaje += `   Cant: ${item.cantidad} x $${item.precioUnit.toFixed(2)}\n`;
            mensaje += `   Subtotal: $${item.subtotal.toFixed(2)}\n\n`;
        });

        const totalText = document.getElementById('total-pedido').innerText;
        mensaje += "-----------------------------\n";
        mensaje += "💰 *TOTAL ESTIMADO: " + totalText + "*\n\n";
        mensaje += "Deseo coordinar tallas y personalización. Referencia: " + correlativo;

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