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

$sqlRangos = "SELECT id, nombre_rango FROM rangos_tallas ORDER BY nombre_rango";
$resultRangos = $conn->query($sqlRangos);
$rangos = [];
if ($resultRangos) {
    while ($row = $resultRangos->fetch_assoc()) {
        $rangos[] = $row;
    }
}

$sqlNombres = "SELECT DISTINCT nombre FROM productos WHERE activo = 1 ORDER BY nombre ASC";
$resNombres = mysqli_query($conn, $sqlNombres);
$lista_nombres = [];
while ($nom = mysqli_fetch_assoc($resNombres)) {
    $lista_nombres[] = $nom['nombre'];
}

$f_producto = isset($_GET['producto']) ? mysqli_real_escape_string($conn, $_GET['producto']) : '';
$f_genero   = isset($_GET['genero'])   ? mysqli_real_escape_string($conn, $_GET['genero'])   : '';
$f_talla    = isset($_GET['rango_tallas_id']) ? mysqli_real_escape_string($conn, $_GET['rango_tallas_id']) : '';

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

if ($f_producto !== '') {
    $sql .= " AND p.nombre = '$f_producto'";
}

if ($f_genero !== '') {
    $sql .= " AND p.tipo_genero = '$f_genero'";
}

if ($f_talla !== '') {
    $sql .= " AND r.rango_tallas_id = '$f_talla'";
}
$sql .= " ORDER BY p.nombre ASC";

$result = mysqli_query($conn, $sql);

$total_filas = mysqli_num_rows($result);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>INVERCLINIK - Catálogo por Tallas</title>
    <script src="../../assets/js/jquery-3.7.1.min.js"></script>
    <script src="../../assets/js/sweetalert2.all.min.js"></script>
    <script>window.Swal = Swal.mixin({ confirmButtonText: 'Aceptar' });</script>
    <link rel="stylesheet" href="../../assets/css/select2.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="../../css/catalogo_cliente.css">
    <link rel="stylesheet" href="../../css/sweetalert-overrides.css">
    <link rel="stylesheet" href="../../assets/css/all.min.css">
    <link rel="stylesheet" href="../../assets/css/password-toggle.css" />
    <!-- <link rel="stylesheet" href="../../assets/css/bootstrap.css"> -->
</head>
<body>
    <div class="container">
    <header class="header">
        <div class="logo">
           <a href="<?php echo $urlInicio; ?>"> <h1>INVERCLINIK</h1></a>
        </div>
        <h2 class="catalog-title">Catálogo de Productos</h2>
        <div class="user-info">
            Bienvenido, <strong><?php echo $nombreUsuario; ?></strong>
            <?php if($isLoggedIn): ?>
                <a href="../../logout.php" class="btn-logout">Cerrar Sesión</a>
            <?php endif; ?>
        </div>
    </header>
    <form class="dashboard-filtro" id="formFiltroDashboard" action="#" method="get" autocomplete="off">
            <div class="filter-group">
                <label class="form-label">Producto</label>
                <select class="form-control" name="producto" id="producto">
                    <option value=""></option>
                    <?php foreach($lista_nombres as $nombre): ?>
                        <option value="<?php echo $nombre; ?>" <?php echo ($f_producto == $nombre) ? 'selected' : ''; ?>>
                            <?php echo $nombre; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-group">
                <label class="form-label">Género</label>
                <select class="form-control" name="genero"> <option value=""></option>
                    <option value="Masculino" <?php echo ($f_genero == 'Masculino') ? 'selected' : ''; ?>>Masculino</option>
                    <option value="Femenino" <?php echo ($f_genero == 'Femenino') ? 'selected' : ''; ?>>Femenino</option>
                    <option value="Unisex" <?php echo ($f_genero == 'Unisex') ? 'selected' : ''; ?>>Unisex</option>
                </select>
            </div>

            <div class="filter-group">
                <label class="form-label">Talla</label>
                <select name="rango_tallas_id" class="form-control">
                    <option value=""></option>
                    <?php foreach ($rangos as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo ($f_talla == $r['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($r['nombre_rango']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-aplicar">Aplicar</button>
        </form>

    <div class="main-panel">
        <div class="main-layout">
            <div class="products-grid"> <?php
                if($total_filas > 0): 
                    while($p = mysqli_fetch_assoc($result)):
                        $nombreCompleto = $p['nombre'] . " (Talla: " . $p['nombre_talla'] . ")";
                        $rawImg = trim((string)($p['imagen'] ?? ''));
                        if ($rawImg === '') {
                            $imgSrc = '../../assets/img/inverclinik_3.png';
                        } elseif (preg_match('#\Ahttps?://#i', $rawImg)) {
                            $imgSrc = htmlspecialchars($rawImg, ENT_QUOTES, 'UTF-8');
                        } elseif ($rawImg[0] === '/') {
                            $imgSrc = htmlspecialchars($rawImg, ENT_QUOTES, 'UTF-8');
                        } elseif (preg_match('#\Aassets/#i', $rawImg)) {
                            $imgSrc = '../../' . htmlspecialchars($rawImg, ENT_QUOTES, 'UTF-8');
                        } else {
                            $imgSrc = '../../assets/img/productos/' . htmlspecialchars(basename($rawImg), ENT_QUOTES, 'UTF-8');
                        }
                        $nombreCompleto = $p['nombre'] . " (Talla: " . $p['nombre_talla'] . ")"; ?>
                        <div class="product-card" id="card-receta-<?php echo $p['id_receta']; ?>">
                            <div class="product-image">
                                <img src="<?php echo $imgSrc; ?>" alt="Producto">
                            </div>
                            <h3><?php echo $p['nombre']; ?></h3>
                            <p style="color: #005bbe; font-weight: bold; margin: 5px 0;">Talla: <?php echo $p['nombre_talla']; ?></p>
                            <div class="product-info"><strong>$<?php echo number_format($p['precio_detal'], 2); ?></strong></div>
                            <p class="product-description"><?php echo $p['descripcion']; ?></p>

                            <div class="product-controls-row"> 
                                <input type="number" id="cant-<?php echo $p['id_receta']; ?>" class="quantity-input" value="1" min="1">
                                <button class="btn-add-cart" onclick="agregarAlPedido('<?php echo htmlspecialchars($nombreCompleto, ENT_QUOTES, 'UTF-8'); ?>', <?php echo $p['precio_detal']; ?>, <?php echo $p['precio_mayor']; ?>, 'cant-<?php echo $p['id_receta']; ?>', <?php echo $p['id_receta']; ?>)">
                                    <i class="fas fa-cart-plus"></i> Añadir
                                </button>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="grid-column: span 4; text-align: center; padding: 50px;">
                        <p>No se encontraron productos con esos filtros.</p>
                    </div>
                <?php endif; ?>
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
            Swal.fire({ icon: 'info', text: "Por favor, inicie sesión o regístrese para continuar." });
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
        if(pedido.length === 0) { Swal.fire({ icon: 'warning', text: "Carrito vacío" }); return; }
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
                    Swal.fire({ icon: 'success', text: "Presupuesto " + res.correlativo + " generado con éxito." });
                    enviarWhatsApp(res.correlativo);
                    pedido = [];
                    actualizarVista();
                } else {
                    Swal.fire({ icon: 'error', text: "Error: " + res.message });
                }
            }
        });
    }

    function enviarWhatsApp(correlativo) {
        const telefonoAdmin = "584243084640"; 
        const nombreParaMensaje = "<?php echo $nombreUsuario; ?>";
        
        let mensaje = "¡Hola! Soy *" + nombreParaMensaje + "*.\n";
        mensaje += "*PRESUPUESTO:* `" + correlativo + "`\n\n";
        mensaje += "*DETALLE DEL PEDIDO*\n";
        mensaje += "-----------------------------\n";

        pedido.forEach(item => {
            mensaje += `• ${item.nombre}\n`;
            mensaje += `   Cant: ${item.cantidad} x $${item.precioUnit.toFixed(2)}\n`;
            mensaje += `   Subtotal: $${item.subtotal.toFixed(2)}\n\n`;
        });

        const totalText = document.getElementById('total-pedido').innerText;
        mensaje += "-----------------------------\n";
        mensaje += "*TOTAL ESTIMADO: " + totalText + "*\n\n";
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