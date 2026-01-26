<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

$createRecetasUnicas = "
CREATE TABLE IF NOT EXISTS recetas (
    id INT PRIMARY KEY AUTO_INCREMENT,
    producto_id INT NOT NULL,
    rango_tallas_id INT NOT NULL,
    tipo_produccion_id INT NOT NULL,
    observaciones TEXT,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_receta (producto_id, rango_tallas_id, tipo_produccion_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
";
$conn->query($createRecetasUnicas);

$checkRecetas = $conn->query("SELECT COUNT(*) as count FROM recetas");
$row = $checkRecetas->fetch_assoc();
if ($row['count'] == 0) {
    $sqlInsertRecetas = "
        INSERT IGNORE INTO recetas (producto_id, rango_tallas_id, tipo_produccion_id, observaciones)
        SELECT DISTINCT producto_id, rango_tallas_id, tipo_produccion_id, NULL
        FROM recetas_productos
    ";
    $conn->query($sqlInsertRecetas);
}

$sqlInsumos = "SELECT i.id, i.nombre, i.unidad_medida, 
                      COALESCE(inv.stock_actual, 0) AS stock_actual
               FROM insumos i
               LEFT JOIN inventario inv ON i.id = inv.insumo_id
               WHERE i.activo = 1
               ORDER BY i.nombre ASC";
$resultInsumos = $conn->query($sqlInsumos);
$insumos = [];
if ($resultInsumos) {
    while ($row = $resultInsumos->fetch_assoc()) {
        $insumos[] = $row;
    }
}

$sqlRecetas = "SELECT 
                    r.id AS receta_id,
                    p.nombre AS producto_nombre,
                    rt.nombre_rango AS rango_tallas_nombre,
                    tp.nombre AS tipo_produccion_nombre,
                    r.producto_id,
                    r.rango_tallas_id,
                    r.tipo_produccion_id
               FROM recetas r
               INNER JOIN productos p ON r.producto_id = p.id
               INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
               INNER JOIN tipos_produccion tp ON r.tipo_produccion_id = tp.id
               ORDER BY p.nombre, rt.nombre_rango";
$resultRecetas = $conn->query($sqlRecetas);
$recetas = [];
if ($resultRecetas) {
    while ($row = $resultRecetas->fetch_assoc()) {
        $recetas[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Inventario</title>
    <style>
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-item {
            margin-bottom: -2px;
        }
        .nav-tabs .nav-link {
            border: 1px solid transparent;
            border-top-left-radius: 0.25rem;
            border-top-right-radius: 0.25rem;
            padding: 0.5rem 1rem;
            color: #495057;
            background-color: transparent;
            cursor: pointer;
        }
        .nav-tabs .nav-link:hover {
            border-color: #e9ecef #e9ecef #dee2e6;
            isolation: isolate;
        }
        .nav-tabs .nav-link.active {
            color: #495057;
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
            font-weight: bold;
        }
        .tab-content {
            margin-top: 0;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .stock-info {
            background-color: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .stock-actual {
            font-size: 18px;
            font-weight: bold;
            color: #0056b3;
        }
        .badge-entrada {
            /* background-color: #28a745; */
            color: #28a745;
            padding: 5px 10px;
            border-radius: 3px;
        }
        .badge-salida {
            background-color: #dc3545;
            color: white;
            padding: 5px 10px;
            border-radius: 3px;
        }
    </style>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Inventario</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12" style="display: flex; gap: 10px;">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Registrar Movimiento</button>
                        <!-- <button class="btn btn-info" onclick="mostrarVista('listado');cargarListado(tabActivo || 'materia_prima');">Ver Inventario</button> -->
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <ul class="nav nav-tabs mb-3" id="inventario-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="materia-prima-tab" type="button" onclick="cambiarTab('materia_prima');">
                                    Materia Prima (Insumos)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="productos-tab" type="button" onclick="cambiarTab('productos');">
                                    Productos Terminados
                                </button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="inventario-tab-content">
                            <div class="tab-pane active" id="materia-prima" role="tabpanel">
                                <h5 class="subtitle">Inventario de Materia Prima</h5>
                        
                                <div class="table-container">
                                    <table class="recipe-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Última Actualización</th>
                                                <th>Insumo</th>
                                                <th>Último Movimiento</th>
                                                <th>Origen del Movimiento</th>
                                                <th>Stock Actual</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-materia-prima">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            
                            <div class="tab-pane" id="productos" role="tabpanel">
                                <h5 class="subtitle">Inventario de Productos Terminados</h5>
                                <div class="table-container">
                                    <table class="recipe-table">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Última Actualización</th>
                                                <th>Producto</th>
                                                <th>Rango Tallas</th>
                                                <th>Tipo Producción</th>
                                                <th>Último Movimiento</th>
                                                <th>Stock Actual</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-productos">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vista de Crear Movimiento -->
                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Registrar Nuevo Movimiento</h5>
                        
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Tipo de Inventario <span style="color: red;">*</span></label>
                                <select name="tipo_inventario" id="tipo_inventario" class="form-control" required onchange="cambiarTipoInventario();">
                                    <option value=""></option>
                                    <option value="materia_prima">Materia Prima (Insumos)</option>
                                    <option value="productos">Productos Terminados</option>
                                </select>
                            </div>
                            
                            <div id="campo-materia-prima" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Insumo <span style="color: red;">*</span></label>
                                    <select name="insumo_id" id="insumo_id" class="form-control" onchange="actualizarStockInfo();">
                                        <option value=""></option>
                                        <?php foreach ($insumos as $insumo): ?>
                                            <option value="<?php echo htmlspecialchars($insumo['id']); ?>" 
                                                    data-stock="<?php echo htmlspecialchars($insumo['stock_actual']); ?>"
                                                    data-unidad="<?php echo htmlspecialchars($insumo['unidad_medida']); ?>">
                                                <?php echo htmlspecialchars($insumo['nombre'] . ' (' . $insumo['unidad_medida'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div id="campo-productos" style="display: none;">
                                <div class="mb-3">
                                    <label class="form-label">Receta/Producto <span style="color: red;">*</span></label>
                                    <select name="receta_id" id="receta_id" class="form-control" onchange="actualizarStockInfoProducto();">
                                        <option value=""></option>
                                        <?php foreach ($recetas as $receta): ?>
                                            <option value="<?php echo htmlspecialchars($receta['receta_id']); ?>" 
                                                    data-producto-id="<?php echo htmlspecialchars($receta['producto_id']); ?>"
                                                    data-rango-tallas-id="<?php echo htmlspecialchars($receta['rango_tallas_id']); ?>"
                                                    data-tipo-produccion-id="<?php echo htmlspecialchars($receta['tipo_produccion_id']); ?>">
                                                <?php echo htmlspecialchars($receta['producto_nombre'] . ' - ' . $receta['rango_tallas_nombre'] . ' - ' . $receta['tipo_produccion_nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="stock-info" id="stock-info" style="display: none;">
                                <strong>Stock Actual:</strong> 
                                <span class="stock-actual" id="stock-actual">0</span> 
                                <span id="unidad-medida"></span>
                            </div>

                            <div class="stock-info" id="stock-info" style="display: none;">
                                <strong>Stock Actual:</strong> 
                                <span class="stock-actual" id="stock-actual">0</span> 
                                <span id="unidad-medida"></span>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Tipo de Movimiento <span style="color: red;">*</span></label>
                                <select name="tipo" id="tipo" class="form-control" required>
                                    <option value=""></option>
                                    <option value="entrada">Entrada</option>
                                    <option value="salida">Salida</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Origen del Movimiento <span style="color: red;">*</span></label>
                                <select name="tipo_movimiento" id="tipo_movimiento" class="form-control" required>
                                    <option value="manual">Manual</option>
                                    <option value="compra">Compra</option>
                                    <option value="orden_produccion">Orden de Producción</option>
                                    <option value="ajuste">Ajuste de Inventario</option>
                                </select>
                                <small class="form-text text-muted">Selecciona el origen de este movimiento de inventario</small>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Cantidad <span style="color: red;">*</span></label>
                                <input type="number" step="0.01" min="0.01" name="cantidad" id="cantidad" 
                                       class="form-control" required placeholder="Ej: 10.50">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Observaciones</label>
                                <textarea name="observaciones" id="observaciones" class="form-control" rows="3" 
                                          placeholder="Notas adicionales sobre el movimiento..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary">Registrar Movimiento</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado');cargarListado(tabActivo || 'materia_prima');">Cancelar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
function mostrarVista(vista) {
    document.querySelectorAll('#contenedor-vistas > div').forEach(el => {
        el.classList.add('hidden');
    });
    const vistaElement = document.getElementById('vista-' + vista);
    if (vistaElement) {
        vistaElement.classList.remove('hidden');
    }
}

function actualizarStockInfo() {
    const select = document.getElementById('insumo_id');
    const option = select.options[select.selectedIndex];
    const stockInfo = document.getElementById('stock-info');
    const stockActual = document.getElementById('stock-actual');
    const unidadMedida = document.getElementById('unidad-medida');
    
    if (option.value) {
        const stock = option.getAttribute('data-stock') || '0';
        const unidad = option.getAttribute('data-unidad') || '';
        stockActual.textContent = parseFloat(stock).toFixed(2);
        unidadMedida.textContent = unidad;
        stockInfo.style.display = 'block';
    } else {
        stockInfo.style.display = 'none';
    }
}

var tabActivo = 'materia_prima';

function cambiarTab(tipo) {
    tabActivo = tipo;
    $('.nav-link').removeClass('active');
    $('.tab-pane').removeClass('active');
    
    if (tipo === 'materia_prima') {
        $('#materia-prima-tab').addClass('active');
        $('#materia-prima').addClass('active');
    } else {
        $('#productos-tab').addClass('active');
        $('#productos').addClass('active');
    }
    
    cargarListado(tipo);
}

function cargarListado(tipo = 'materia_prima') {
    $.post('movimientos_inventario_data.php', { 
        action: 'listar_html',
        tipo_inventario: tipo
    }, function(resp) {
        if (tipo === 'materia_prima') {
            $('#tbody-materia-prima').html(resp);
        } else {
            $('#tbody-productos').html(resp);
        }
    }).fail(function(xhr, status, error) {
        if (tipo === 'materia_prima') {
            $('#tbody-materia-prima').html('<tr><td colspan="5" class="text-center text-danger">Error al cargar inventario</td></tr>');
        } else {
            $('#tbody-productos').html('<tr><td colspan="7" class="text-center text-danger">Error al cargar inventario</td></tr>');
        }
    });
}

function cambiarTipoInventario() {
    const tipo = $('#tipo_inventario').val();
    $('#campo-materia-prima').hide();
    $('#campo-productos').hide();
    $('#insumo_id').removeAttr('required');
    $('#receta_id').removeAttr('required');
    $('#stock-info').hide();
    
    if (tipo === 'materia_prima') {
        $('#campo-materia-prima').show();
        $('#insumo_id').attr('required', 'required');
    } else if (tipo === 'productos') {
        $('#campo-productos').show();
        $('#receta_id').attr('required', 'required');
    }
}

function actualizarStockInfoProducto() {
    const recetaId = $('#receta_id').val();
    if (!recetaId) {
        $('#stock-info').hide();
        return;
    }
    
    const option = $('#receta_id option:selected');
    const productoId = option.attr('data-producto-id');
    const rangoTallasId = option.attr('data-rango-tallas-id');
    const tipoProduccionId = option.attr('data-tipo-produccion-id');
    
    $.post('movimientos_inventario_data.php', {
        action: 'obtener_stock_producto',
        producto_id: productoId,
        rango_tallas_id: rangoTallasId,
        tipo_produccion_id: tipoProduccionId
    }, function(resp) {
        if (resp && resp.success) {
            $('#stock-actual').text(parseFloat(resp.stock_actual || 0).toFixed(2));
            $('#unidad-medida').text('unidades');
            $('#stock-info').show();
        }
    }, 'json');
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#tipo_inventario').val('');
    $('#insumo_id').val('');
    $('#receta_id').val('');
    $('#tipo').val('');
    $('#tipo_movimiento').val('manual');
    $('#cantidad').val('');
    $('#observaciones').val('');
    $('#campo-materia-prima').hide();
    $('#campo-productos').hide();
    $('#stock-info').hide();
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado('materia_prima');
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    const tipoInventario = $("#tipo_inventario").val();
    const insumoId = $("#insumo_id").val();
    const recetaId = $("#receta_id").val();
    const tipo = $("#tipo").val();
    const cantidad = parseFloat($("#cantidad").val());
    const observaciones = $("#observaciones").val();

    if (!tipoInventario) {
        alert('Por favor selecciona un tipo de inventario');
        return;
    }

    if (tipoInventario === 'materia_prima' && !insumoId) {
        alert('Por favor selecciona un insumo');
        return;
    }

    if (tipoInventario === 'productos' && !recetaId) {
        alert('Por favor selecciona un producto');
        return;
    }

    if (!tipo) {
        alert('Por favor selecciona un tipo de movimiento');
        return;
    }

    if (!cantidad || cantidad <= 0) {
        alert('La cantidad debe ser mayor a 0');
        return;
    }

    if (tipo === 'salida') {
        let stockActual = 0;
        if (tipoInventario === 'materia_prima') {
            stockActual = parseFloat($('#insumo_id option:selected').attr('data-stock')) || 0;
        } else {
            stockActual = parseFloat($('#stock-actual').text()) || 0;
        }
        
        if (cantidad > stockActual) {
            if (!confirm('La cantidad a salir es mayor al stock actual. ¿Deseas continuar de todas formas?')) {
                return;
            }
        }
    }

    const tipoMovimiento = $("#tipo_movimiento").val();
    
    const datos = {
        action: 'crear',
        tipo_inventario: tipoInventario,
        insumo_id: tipoInventario === 'materia_prima' ? insumoId : null,
        receta_id: tipoInventario === 'productos' ? recetaId : null,
        tipo: tipo,
        tipo_movimiento: tipoMovimiento,
        cantidad: cantidad,
        observaciones: observaciones
    };

    $.ajax({
        url: "movimientos_inventario_data.php",
        type: "POST",
        data: datos,
        success: function(resp) {
            if (resp && resp.success) {
                alert(resp.message);
                limpiarFormulario();
                mostrarVista("listado");
                const tipoInventario = $("#tipo_inventario").val() || tabActivo || 'materia_prima';
                if (tipoInventario === 'productos') {
                    cambiarTab('productos');
                } else {
                    cambiarTab('materia_prima');
                }
            } else {
                alert("Error: " + (resp ? resp.message : "Respuesta inválida"));
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            alert("Error de conexión.");
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

