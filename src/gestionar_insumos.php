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

$sqlUnidades = "SELECT id, codigo, nombre FROM unidad_medida ORDER BY nombre ASC";
$resultUnidades = $conn->query($sqlUnidades);
$unidades_medida = [];
if ($resultUnidades) {
    while ($row = $resultUnidades->fetch_assoc()) {
        $unidades_medida[] = $row;
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
                
                <!-- <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Insumo</button>
                    </div>
                </div> -->

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div aria-label="Acciones de insumos">
                                    <button class="btn btn-success" id="btn-ir-crear" style="margin-bottom: 0px !important;" title="Registrar Nuevo Insumo" data-toggle="tooltip">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-info" id="btn-toggle-filtros" title="Filtros" data-toggle="tooltip">
                                        <i class="fas fa-filter"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                            <div class="row" style="margin-bottom: 10px;">
                                <div class="col-sm-4">
                                    <label for="filtro-nombre">Nombre del Insumo</label>
                                    <input type="text" id="filtro-nombre" class="form-control clase-filtro-insumo" placeholder="Buscar por nombre...">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-proveedor">Proveedor</label>
                                    <input type="text" id="filtro-proveedor" class="form-control clase-filtro-insumo" placeholder="Buscar por proveedor...">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-almacen">Almacén</label>
                                    <input type="text" id="filtro-almacen" class="form-control clase-filtro-insumo" placeholder="Buscar por almacén...">
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-8"></div>
                                <div class="col-sm-4" style="text-align: right;">
                                    <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros-insumo">
                                        <i class="fas fa-eraser"></i> Limpiar Filtros
                                    </button>
                                </div>
                            </div>
                        </div>
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
                        <div id="paginacion-insumos"></div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <button class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>
                        <!-- <h5 class="subtitle">Crear/Editar Insumo</h5> -->
                        <form id="form-crear">
                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Nombre del Insumo <span style="color: red;">*</span></label>
                                    <input type="text" name="nombre" id="nombre" class="form-control" required 
                                        maxlength="255">
                                </div>
                                <div class="col-sm-3">
                                    <label class="form-label">Unidad de Medida <span style="color: red;">*</span></label>
                                    <select name="unidad_medida_id" id="unidad_medida_id" class="form-control" required>
                                        <option value="">-- Seleccione una unidad --</option>
                                        <?php foreach ($unidades_medida as $um): ?>
                                            <option value="<?php echo (int) $um['id']; ?>"><?php echo htmlspecialchars($um['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-sm-3" style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                                    <input type="checkbox" id="adicional" name="adicional" value="1" style="width: 20px; height: 20px;">
                                    <label for="adicional" style="cursor: pointer; font-weight: bold;">
                                        Insumo Adicional
                                    </label>
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Costo Unitario ($) <span style="color: red;">*</span></label>
                                    <input type="number" step="0.01" min="0" name="costo_unitario" id="costo_unitario" 
                                        class="form-control" required>
                                </div>
                                <div class="col-sm-6" id="contenedor-equivalente-bs-insumo" style="display: none;">
                                    <label class="form-label">Equivalente en Bs.</label>
                                    <input type="text" id="equivalente_bs_insumo" class="form-control" readonly style="background-color: #e9ecef;">
                                    <small class="text-muted" id="texto-tasa-informativa-insumo"></small>
                                </div>
                            </div>
                            
                           

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Stock mínimo</label>
                                    <input type="number" step="0.01" min="0" name="stock_minimo" id="stock_minimo" class="form-control" placeholder="Ej: 10">
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label">Stock máximo</label>
                                    <input type="number" step="0.01" min="0" name="stock_maximo" id="stock_maximo" class="form-control" placeholder="Ej: 100">
                                </div>
                            </div>

                            <div class="row form-group">
                                <div class="col-sm-6">
                                    <label class="form-label">Almacén</label>
                                    <select name="almacen_id" id="almacen_id" class="form-control">
                                        <option value="">Seleccione un almacén</option>
                                        <?php foreach ($almacenes as $a): ?>
                                            <option value="<?php echo htmlspecialchars($a['id']); ?>">
                                                <?php echo htmlspecialchars($a['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <small class="form-text text-muted">Almacén donde se registra el inventario de este insumo</small>
                                </div>
                                <div class="col-sm-6">
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

$('#btn-ir-crear').on('click', function() {
    $('#vista-listado').fadeOut(200, function() {
        $('#vista-crear').removeClass('hidden').fadeIn();
        limpiarFormulario();
    });
});

$('#btn-volver-listado').on('click', function() {
    Swal.fire({
        icon: 'question',
        text: '¿Desea salir? Se perderán los cambios no guardados.',
        showCancelButton: true,
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;
        $('#vista-crear').fadeOut(200, function() {
            $('#vista-listado').fadeIn();
        });
    });
});

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
    $('#vista-listado, #vista-crear').addClass('hidden').hide();
    $('#vista-' + vista).removeClass('hidden').fadeIn(250);
}

function cargarListado(page) {
    // CAPTURAMOS LOS FILTROS ESPECÍFICOS DE INSUMOS
    var params = { 
        action: 'listar_html',
        buscar_nombre: $('#filtro-nombre').val(),
        buscar_proveedor: $('#filtro-proveedor').val(),
        buscar_almacen: $('#filtro-almacen').val()
    };

    crudPostListadoPaginado(
        'gestionar_insumos_data.php',
        params,
        '#vista-listado tbody',
        '#paginacion-insumos',
        page || 1
    );
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#nombre').val('');
    $('#unidad_medida_id').val('');
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
    $('#unidad_medida_id').val(data.unidad_medida_id ? String(data.unidad_medida_id) : '');
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
    cargarListado(1);
    bindCrudPagination('#paginacion-insumos', cargarListado);
    
    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    var temporizadorBusqueda;

    $('.clase-filtro-insumo').on('change keyup', function(e) {
        if (e.type === 'change') {
            cargarListado(1);
            return;
        }

        clearTimeout(temporizadorBusqueda);
        temporizadorBusqueda = setTimeout(function() {
            cargarListado(1);
        }, 350); // 350 milisegundos de espera
    });

    $('#btn-limpiar-filtros-insumo').on('click', function() {
        $('#filtro-nombre').val('');
        $('#filtro-proveedor').val('');
        $('#filtro-almacen').val('');
        cargarListado(1);
    });
});

$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    var idInsumo = $("#editar-insumo-id").val();

    var datos = {
        action: idInsumo ? "editar" : "crear",
        id: idInsumo || null,
        nombre: $("#nombre").val(),
        unidad_medida_id: $("#unidad_medida_id").val(),
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

    if (!datos.unidad_medida_id || datos.unidad_medida_id.trim() === '') {
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
