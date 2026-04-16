<?php
require_once "../template/header.php";

$sqlProveedores = "SELECT id, nombre FROM proveedores ORDER BY nombre";
$resultProveedores = $conn->query($sqlProveedores);
$proveedores = [];
if ($resultProveedores) {
    while ($row = $resultProveedores->fetch_assoc()) {
        $proveedores[] = $row;
    }
}

$tasa_actual = null;
$rt = $conn->query("SELECT tasa FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
if ($rt && $row_tasa = $rt->fetch_assoc()) {
    $tasa_actual = (float) $row_tasa['tasa'];
}

$sqlAlmacenes = "SELECT id, nombre FROM almacenes WHERE activo = 1 ORDER BY nombre";
$resultAlmacenes = $conn->query($sqlAlmacenes);
$almacenes = [];
if ($resultAlmacenes) {
    while ($row = $resultAlmacenes->fetch_assoc()) {
        $almacenes[] = $row;
    }
}
if (empty($almacenes)) {
    @$conn->query("CREATE TABLE IF NOT EXISTS almacenes (id int(11) NOT NULL AUTO_INCREMENT, nombre varchar(100) NOT NULL, codigo varchar(20) DEFAULT NULL, activo tinyint(1) NOT NULL DEFAULT 1, PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    @$conn->query("INSERT IGNORE INTO almacenes (nombre, codigo, activo) VALUES ('Principal', 'ALM01', 1)");
    $resultAlmacenes = $conn->query($sqlAlmacenes);
    if ($resultAlmacenes) {
        while ($row = $resultAlmacenes->fetch_assoc()) {
            $almacenes[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Insumos</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Insumos</h2>
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Insumo</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Insumos</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Nombre</th>
                                        <th>Unidad de Medida</th>
                                        <th>Costo Unitario</th>
                                        <th>Equivalente (Bs.)</th>
                                        <th>Stock Mín.</th>
                                        <th>Stock Máx.</th>
                                        <th>Almacén</th>
                                        <th>Proveedor</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Crear/Editar Insumo</h5>
                        <form id="form-crear">
                            <div class="mb-3">
                                <label class="form-label">Nombre del Insumo <span style="color: red;">*</span></label>
                                <input type="text" name="nombre" id="nombre" class="form-control" required 
                                       maxlength="255" placeholder="Ej: Tela jean rígido 14oz">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Unidad de Medida <span style="color: red;">*</span></label>
                                <select name="unidad_medida" id="unidad_medida" class="form-control" required>
                                    <option value="">-- Seleccione una unidad --</option>
                                    <option value="metro">Metro</option>
                                    <option value="unidad">Unidad</option>
                                    <option value="kilogramo">Kilogramo</option>
                                    <option value="litro">Litro</option>
                                    <option value="metro_cuadrado">Metro Cuadrado</option>
                                    <option value="carrete">Carrete</option>
                                    <option value="rollo">Rollo</option>
                                    <option value="pieza">Pieza</option>
                                </select>
                            </div>
                            <div class="mb-3" style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                <input type="checkbox" id="adicional" name="adicional" value="1" style="width: 20px; height: 20px;">
                                <label for="adicional" style="cursor: pointer; font-weight: bold;">
                                    Insumo Adicional
                                </label>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Costo Unitario ($) <span style="color: red;">*</span></label>
                                <input type="number" step="0.01" min="0" name="costo_unitario" id="costo_unitario" 
                                       class="form-control" required placeholder="Ej: 5.00">
                            </div>
                            <div class="mb-3" id="contenedor-equivalente-bs-insumo" style="display: none;">
                                <label class="form-label">Equivalente en Bs.</label>
                                <input type="text" id="equivalente_bs_insumo" class="form-control" readonly style="background-color: #e9ecef;">
                                <small class="text-muted" id="texto-tasa-informativa-insumo"></small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stock mínimo</label>
                                <input type="number" step="0.01" min="0" name="stock_minimo" id="stock_minimo" class="form-control" placeholder="Ej: 10">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Stock máximo</label>
                                <input type="number" step="0.01" min="0" name="stock_maximo" id="stock_maximo" class="form-control" placeholder="Ej: 100">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Almacén</label>
                                <select name="almacen_id" id="almacen_id" class="form-control">
                                    <option value="">-- Seleccione un almacén --</option>
                                    <?php foreach ($almacenes as $a): ?>
                                        <option value="<?php echo htmlspecialchars($a['id']); ?>">
                                            <?php echo htmlspecialchars($a['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-text text-muted">Almacén donde se registra el inventario de este insumo</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Proveedor</label>
                                <select name="proveedor_id" required id="proveedor_id" class="form-control">
                                    <option value="">Seleccione un proveedor</option>
                                    <?php foreach ($proveedores as $prov): ?>
                                        <option value="<?php echo htmlspecialchars($prov['id']); ?>">
                                            <?php echo htmlspecialchars($prov['nombre']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Insumo</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-insumo-id" name="id" value="">
                            <input type="hidden" id="action" value="">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
var tasaCambiariaActual = <?php echo $tasa_actual !== null ? json_encode($tasa_actual) : 'null'; ?>;
var tasaParaEquivalenteInsumo = tasaCambiariaActual;

function actualizarEquivalenteBsInsumo() {
    var costo = parseFloat($('#costo_unitario').val()) || 0;
    var contenedor = $('#contenedor-equivalente-bs-insumo');
    var tasa = tasaParaEquivalenteInsumo;
    if (!tasa || tasa <= 0 || costo <= 0) {
        contenedor.hide();
        return;
    }
    var equivBs = costo * tasa;
    $('#equivalente_bs_insumo').val('Bs. ' + equivBs.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ','));
    $('#texto-tasa-informativa-insumo').text('Tasa informativa: ' + tasa.toFixed(4) + ' Bs/USD');
    contenedor.show();
}

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
    $.post('gestionar_insumos_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#nombre').val('');
    $('#unidad_medida').val('');
    $('#costo_unitario').val('');
    $('#stock_minimo').val('');
    $('#stock_maximo').val('');
    $('#almacen_id').val('');
    $('#proveedor_id').val('');
    $('#editar-insumo-id').val('');
    $('#contenedor-equivalente-bs-insumo').hide();
    tasaParaEquivalenteInsumo = tasaCambiariaActual;
}

function editarInsumo(data) {
    $('#nombre').val(data.nombre || '');
    $('#unidad_medida').val(data.unidad_medida || '');
    $('#costo_unitario').val(data.costo_unitario || '');
    $('#stock_minimo').val(data.stock_minimo !== undefined && data.stock_minimo !== '' ? data.stock_minimo : '');
    $('#stock_maximo').val(data.stock_maximo !== undefined && data.stock_maximo !== '' ? data.stock_maximo : '');
    $('#almacen_id').val(data.almacen_id || '');
    $('#proveedor_id').val(data.proveedor_id || '');
    $('#editar-insumo-id').val(data.id);
    $('#adicional').prop('checked', data.adicional == 1 || data.adicional == '1');
    tasaParaEquivalenteInsumo = (data.tasa_insumo != null && parseFloat(data.tasa_insumo) > 0) ? parseFloat(data.tasa_insumo) : tasaCambiariaActual;
    actualizarEquivalenteBsInsumo();
    mostrarVista('crear');
}

$('#costo_unitario').on('input change', function() {
    actualizarEquivalenteBsInsumo();
});

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado();
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    var idInsumo = $("#editar-insumo-id").val();

    var datos = {
        action: idInsumo ? "editar" : "crear",
        id: idInsumo || null,
        nombre: $("#nombre").val(),
        unidad_medida: $("#unidad_medida").val(),
        costo_unitario: $("#costo_unitario").val(),
        stock_minimo: $("#stock_minimo").val() || null,
        stock_maximo: $("#stock_maximo").val() || null,
        almacen_id: $("#almacen_id").val() || null,
        proveedor_id: $("#proveedor_id").val() || null,
        adicional: $("#adicional").val(),
    };

    if (!datos.nombre || datos.nombre.trim() === '') {
        Swal.fire({ icon: 'warning', text: 'El nombre del insumo es obligatorio' });
        return;
    }

    if (!datos.unidad_medida || datos.unidad_medida.trim() === '') {
        Swal.fire({ icon: 'warning', text: 'La unidad de medida es obligatoria' });
        return;
    }

    if (!datos.costo_unitario || parseFloat(datos.costo_unitario) < 0) {
        Swal.fire({ icon: 'warning', text: 'El costo unitario debe ser mayor o igual a 0' });
        return;
    }

    $.ajax({
        url: "gestionar_insumos_data.php",
        type: "POST",
        data: datos,
        success: function(resp) {
            if (resp && resp.success) {
                Swal.fire({ icon: 'success', text: resp.message });
                mostrarVista("listado");
                cargarListado();
            } else {
                Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Respuesta inválida") });
            }
        },
        error: function(xhr) {
            console.error("Error:", xhr.responseText);
            Swal.fire({ icon: 'error', text: "Error de conexión." });
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
