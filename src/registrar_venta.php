<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

$sqlClientes = "SELECT id, nombre FROM clientes ORDER BY nombre";
$resultClientes = $conn->query($sqlClientes);
$clientes = [];
if ($resultClientes) {
    while ($row = $resultClientes->fetch_assoc()) {
        $clientes[] = $row;
    }
}

$sqlProductos = "SELECT id, nombre FROM productos ORDER BY nombre";
$resultProductos = $conn->query($sqlProductos);
$productos = [];
if ($resultProductos) {
    while ($row = $resultProductos->fetch_assoc()) {
        $productos[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrar Venta</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Registrar Venta</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Registrar Nueva Venta</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Ventas</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Cliente</th>
                                        <th>Fecha</th>
                                        <th>Número Factura</th>
                                        <th>Cantidad</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Registrar Nueva Venta</h5>
                        <form id="form-venta">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Cliente <span style="color: red;">*</span></label>
                            <select name="cliente_id" id="cliente_id" class="form-control" required>
                                <option value="">-- Seleccione un cliente --</option>
                                <?php foreach ($clientes as $cli): ?>
                                    <option value="<?php echo htmlspecialchars($cli['id']); ?>">
                                        <?php echo htmlspecialchars($cli['nombre']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Fecha <span style="color: red;">*</span></label>
                            <input type="date" name="fecha" id="fecha" class="form-control" required 
                                   value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Número de Factura</label>
                            <input type="text" name="numero_factura" id="numero_factura" class="form-control" 
                                   maxlength="50" placeholder="Ej: FAC-001">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Estado</label>
                            <select name="estado" id="estado" class="form-control">
                                <option value="pendiente">Pendiente</option>
                                <option value="entregado">Entregado</option>
                                <option value="cancelado">Cancelado</option>
                            </select>
                        </div>
                    </div>

                    <hr style="margin: 20px 0; border-color: #dee2e6;">

                    <div class="mb-3">
                        <h5 style="color: #0056b3; margin-bottom: 15px;">Detalle de la Venta</h5>
                        
                        <div class="card" style="padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">
                            <div class="row">
                                <div class="col-md-5 mb-2">
                                    <label class="form-label">Producto</label>
                                    <select id="nuevo-producto-id" class="form-control">
                                        <option value="">-- Seleccione un producto --</option>
                                        <?php foreach ($productos as $prod): ?>
                                            <option value="<?php echo htmlspecialchars($prod['id']); ?>" 
                                                    data-nombre="<?php echo htmlspecialchars($prod['nombre']); ?>">
                                                <?php echo htmlspecialchars($prod['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div id="stock-info-producto" style="margin-top: 8px; padding: 8px; background-color: #e9ecef; border-radius: 4px; display: none;">
                                        <small><strong>Stock Actual:</strong> <span id="stock-actual-producto" style="color: #0056b3; font-weight: bold;">0</span> unidades</small>
                                    </div>
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Cantidad</label>
                                    <input type="number" step="0.01" min="0.01" id="nuevo-cantidad" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Precio Unitario</label>
                                    <input type="number" step="0.01" min="0" id="nuevo-precio-unitario" class="form-control" placeholder="0.00">
                                </div>
                                <div class="col-md-2 mb-2">
                                    <label class="form-label">Subtotal</label>
                                    <input type="text" id="nuevo-subtotal" class="form-control" readonly placeholder="$0.00">
                                </div>
                                <div class="col-md-1 mb-2 d-flex align-items-end" style="position: relative; top: 22px;">
                                    <button type="button" class="btn btn-success" id="btn-agregar-producto" style="width: fit-content; margin-bottom: 0;">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="mensaje-sin-productos" class="alert alert-info" style="display: none;">
                            No hay productos agregados. Por favor, agrega al menos un producto a la venta.
                        </div>

                        <div id="tabla-productos" style="display: none;">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th>Cantidad</th>
                                        <th>Precio Unitario</th>
                                        <th>Subtotal</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-productos">
                                </tbody>
                                <tfoot>
                                    <tr style="background-color: #f8f9fa; font-weight: bold;">
                                        <td colspan="3" style="text-align: right;">Total:</td>
                                        <td id="total-venta" style="color: #0056b3; font-size: 1.1em;">$0.00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                            <div class="row mb-3">
                                <div class="col-md-12">
                                    <button type="submit" class="btn btn-primary" id="btn-guardar-venta">Guardar Venta</button>
                                    <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDetalleVenta" tabindex="-1" role="dialog" aria-labelledby="modalDetalleVentaLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDetalleVentaLabel">Detalle de la Venta</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body" id="modalDetalleVentaBody">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Cargando...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

<script>
var productosAgregados = [];

function mostrarVista(vista) {
    document.querySelectorAll('#contenedor-vistas > div').forEach(el => {
        el.classList.add('hidden');
    });
    const vistaElement = document.getElementById('vista-' + vista);
    if (vistaElement) {
        vistaElement.classList.remove('hidden');
    }
}

function cargarListado() {
    $.post('registrar_venta_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-venta')[0].reset();
    $('#fecha').val('<?php echo date('Y-m-d'); ?>');
    $('#estado').val('pendiente');
    $('#cliente_id').val('');
    productosAgregados = [];
    actualizarTablaProductos();
    limpiarFormularioProducto();
}

function calcularSubtotal(cantidad, precioUnitario) {
    return cantidad * precioUnitario;
}

function agregarProducto() {
    var productoId = $('#nuevo-producto-id').val();
    var cantidad = parseFloat($('#nuevo-cantidad').val()) || 0;
    var precioUnitario = parseFloat($('#nuevo-precio-unitario').val()) || 0;
    
    if (!productoId || cantidad <= 0) {
        alert('Por favor selecciona un producto e ingresa una cantidad válida');
        return;
    }
    
    if (precioUnitario <= 0) {
        alert('Por favor ingresa un precio unitario válido');
        return;
    }
    
    var productoNombre = $('#nuevo-producto-id option:selected').data('nombre');
    var subtotal = calcularSubtotal(cantidad, precioUnitario);
    
    var producto = {
        producto_id: productoId,
        producto_nombre: productoNombre,
        cantidad: cantidad,
        precio_unitario: precioUnitario,
        subtotal: subtotal
    };
    
    productosAgregados.push(producto);
    actualizarTablaProductos();
    limpiarFormularioProducto();
}

function eliminarProducto(index) {
    productosAgregados.splice(index, 1);
    actualizarTablaProductos();
}

function actualizarTablaProductos() {
    var tbody = $('#tbody-productos');
    tbody.empty();
    
    var totalVenta = 0;
    
    productosAgregados.forEach(function(producto, index) {
        totalVenta += producto.subtotal;
        var row = `
            <tr>
                <td>${producto.producto_nombre}</td>
                <td>${producto.cantidad}</td>
                <td>$${producto.precio_unitario.toFixed(2)}</td>
                <td>$${producto.subtotal.toFixed(2)}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarProducto(${index})">
                        <i class="fa fa-trash"></i> Eliminar
                    </button>
                </td>
            </tr>
        `;
        tbody.append(row);
    });
    
    $('#total-venta').text('$' + totalVenta.toFixed(2));
    
    if (productosAgregados.length > 0) {
        $('#tabla-productos').show();
        $('#mensaje-sin-productos').hide();
        $('#btn-guardar-venta').prop('disabled', false);
    } else {
        $('#tabla-productos').hide();
        $('#mensaje-sin-productos').show();
        $('#btn-guardar-venta').prop('disabled', true);
    }
}

function limpiarFormularioProducto() {
    $('#nuevo-producto-id').val('');
    $('#nuevo-cantidad').val('');
    $('#nuevo-precio-unitario').val('');
    $('#nuevo-subtotal').val('');
    $('#stock-info-producto').hide();
}

function verDetalle(ventaId) {
    $('#modalDetalleVentaBody').html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Cargando...</span></div></div>');
    
    var intentos = 0;
    var maxIntentos = 50;
    
    function mostrarModal() {
        if (typeof $.fn.modal !== 'undefined') {
            $('#modalDetalleVenta').modal('show');
        } else {
            intentos++;
            if (intentos < maxIntentos) {
                setTimeout(mostrarModal, 100);
            } else {
                console.error('Bootstrap modal no está disponible después de varios intentos');
                alert('Error: No se pudo cargar el modal. Por favor, recarga la página.');
            }
        }
    }
    
    mostrarModal();
    
    $.post('registrar_venta_data.php', {
        action: 'obtener_detalle',
        venta_id: ventaId
    }, function(resp) {
        if (resp && resp.success) {
            var html = '<div class="row mb-3">';
            html += '<div class="col-md-6"><strong>Cliente:</strong> ' + resp.venta.cliente_nombre + '</div>';
            html += '<div class="col-md-6"><strong>Fecha:</strong> ' + resp.venta.fecha_formateada + '</div>';
            html += '</div>';
            
            html += '<div class="row mb-3">';
            html += '<div class="col-md-6"><strong>Número de Factura:</strong> ' + (resp.venta.numero_factura || '-') + '</div>';
            html += '<div class="col-md-6"><strong>Estado:</strong> ' + resp.venta.estado_badge + '</div>';
            html += '</div>';
            
            html += '<hr>';
            html += '<h6><strong>Detalle de Productos:</strong></h6>';
            html += '<div class="table-responsive">';
            html += '<table class="table table-bordered table-sm">';
            html += '<thead><tr><th>Producto</th><th>Cantidad</th><th>Precio Unitario</th><th>Subtotal</th></tr></thead>';
            html += '<tbody>';
            
            var total = 0;
            resp.detalles.forEach(function(detalle) {
                total += parseFloat(detalle.subtotal);
                html += '<tr>';
                html += '<td>' + detalle.producto_nombre + '</td>';
                html += '<td>' + parseFloat(detalle.cantidad).toFixed(2) + '</td>';
                html += '<td>$' + parseFloat(detalle.precio_unitario).toFixed(2) + '</td>';
                html += '<td>$' + parseFloat(detalle.subtotal).toFixed(2) + '</td>';
                html += '</tr>';
            });
            
            html += '</tbody>';
            html += '<tfoot><tr style="background-color: #f8f9fa; font-weight: bold;"><td colspan="3" style="text-align: right;">Total:</td><td>$' + total.toFixed(2) + '</td></tr></tfoot>';
            html += '</table>';
            html += '</div>';
            
            $('#modalDetalleVentaBody').html(html);
        } else {
            $('#modalDetalleVentaBody').html('<div class="alert alert-danger">Error al cargar los detalles de la venta</div>');
        }
    }, 'json').fail(function() {
        $('#modalDetalleVentaBody').html('<div class="alert alert-danger">Error de conexión al cargar los detalles</div>');
    });
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado();
    
    $('#nuevo-producto-id').on('change', function() {
        var productoId = $(this).val();
        var productoNombre = $(this).find('option:selected').data('nombre');
        
        if (productoId) {
            // Obtener el precio y stock del producto desde las recetas
            $.post('registrar_venta_data.php', {
                action: 'obtener_precio_producto',
                producto_id: productoId
            }, function(resp) {
                if (resp && resp.success) {
                    $('#nuevo-precio-unitario').val(resp.precio_total.toFixed(2));
                    
                    // Mostrar stock actual
                    if (resp.stock_actual !== undefined) {
                        $('#stock-actual-producto').text(parseFloat(resp.stock_actual).toFixed(2));
                        $('#stock-info-producto').show();
                    } else {
                        $('#stock-actual-producto').text('0');
                        $('#stock-info-producto').show();
                    }
                    
                    calcularSubtotalProducto();
                } else {
                    $('#nuevo-precio-unitario').val('');
                    $('#stock-info-producto').hide();
                    if (resp && resp.message) {
                        alert(resp.message);
                    } else {
                        alert('No se pudo obtener el precio del producto');
                    }
                }
            }, 'json').fail(function() {
                $('#nuevo-precio-unitario').val('');
                $('#stock-info-producto').hide();
                alert('Error al obtener el precio del producto');
            });
        } else {
            $('#nuevo-precio-unitario').val('');
            $('#nuevo-subtotal').val('');
            $('#stock-info-producto').hide();
        }
    });
    
    $('#nuevo-cantidad, #nuevo-precio-unitario').on('input', function() {
        calcularSubtotalProducto();
    });
    
    function calcularSubtotalProducto() {
        var cantidad = parseFloat($('#nuevo-cantidad').val()) || 0;
        var precioUnitario = parseFloat($('#nuevo-precio-unitario').val()) || 0;
        var subtotal = calcularSubtotal(cantidad, precioUnitario);
        $('#nuevo-subtotal').val('$' + subtotal.toFixed(2));
        
        // Habilitar botón agregar si hay cantidad y precio válidos
        if (cantidad > 0 && precioUnitario > 0) {
            $('#btn-agregar-producto').prop('disabled', false);
        } else {
            $('#btn-agregar-producto').prop('disabled', true);
        }
    }
    
    $('#btn-agregar-producto').on('click', function() {
        agregarProducto();
    });
    
    $('#nuevo-cantidad, #nuevo-precio-unitario').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            agregarProducto();
        }
    });
    
    $('#btn-guardar-venta').prop('disabled', true);
});

$("#form-venta").on("submit", function(e) {
    e.preventDefault();

    if (productosAgregados.length === 0) {
        alert('Por favor agrega al menos un producto a la venta');
        return;
    }

    var datos = {
        action: 'crear',
        cliente_id: $("#cliente_id").val(),
        fecha: $("#fecha").val(),
        numero_factura: $("#numero_factura").val() || "",
        estado: $("#estado").val(),
        productos: productosAgregados
    };

    if (!datos.cliente_id || !datos.fecha) {
        alert('Por favor completa todos los campos obligatorios');
        return;
    }

    $.ajax({
        url: "registrar_venta_data.php",
        type: "POST",
        data: JSON.stringify(datos),
        contentType: "application/json",
        success: function(resp) {
            if (resp && resp.success) {
                alert(resp.message);
                mostrarVista("listado");
                cargarListado();
            } else {
                alert("Error: " + (resp ? resp.message : "Respuesta inválida"));
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            try {
                var resp = JSON.parse(xhr.responseText);
                alert("Error: " + (resp.message || "Error al guardar la venta"));
            } catch (e) {
                alert("Error de conexión.");
            }
        }
    });
});
</script>

<?php
$conn->close();
require_once "../template/footer.php";
?>
</body>
</html>

