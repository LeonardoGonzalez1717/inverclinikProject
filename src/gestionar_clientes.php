<?php
require_once "../template/header.php";
require_once "../connection/connection.php";

?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Clientes</title>
</head>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Gestión de Clientes</h2>
                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div aria-label="Acciones de Clientes">
                                    <button class="btn btn-success" id="btn-ir-crear" style="margin-bottom: 0px !important;" title="Registrar Cliente" data-toggle="tooltip">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-info" id="btn-toggle-filtros" title="Filtrar Lista" data-toggle="tooltip">
                                        <i class="fas fa-filter"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); padding: 15px; border-radius: 5px; border: 1px solid #ddd; background-color: #fbfbfb;">
                            <div class="row">
                                <div class="col-sm-6">
                                    <label for="filtro-buscar">Buscar Cliente</label>
                                    <input type="text" id="filtro-buscar" class="form-control clase-filtro-cliente" placeholder="Buscar por nombre, cédula, RIF o teléfono...">
                                </div>
                                <div class="col-sm-3">
                                    <label for="filtro-tipo-doc">Tipo Documento</label>
                                    <select id="filtro-tipo-doc" class="form-control clase-filtro-cliente">
                                        <option value="">Todos</option>
                                        <option value="V">Venezolano (V)</option>
                                        <option value="J">Jurídico (J)</option>
                                        <option value="E">Extranjero (E)</option>
                                    </select>
                                </div>
                                <div class="col-sm-3" style="margin-top: 25px;">
                                    <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros-cli">
                                        <i class="fas fa-eraser"></i> Limpiar
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Doc. Identidad</th>
                                        <th>Nombre</th>
                                        <th>Teléfono</th>
                                        <th>Email</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                        <div id="paginacion-clientes"></div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <button class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>
                        <form id="form-crear">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Cédula / RIF <span style="color: red;">*</span></label>
                                    <div style="display: flex; gap: 5px; ">
                                        <select name="tipo_doc" id="tipo_doc" class="form-control" style="width: 30%;">
                                            <option value=""></option>
                                            <option value="V">V</option>
                                            <option value="J">J</option>
                                            <option value="E">E</option>
                                        </select>
                                        <input type="text" name="nro_doc" maxlength="9" id="nro_doc" placeholder="Documento" class="form-control"/>
                                    </div>
                                    <small class="text-muted">Cédula: 8 dígitos. RIF: 9.</small>
                                </div>

                                <div class="col-md-8 mb-3">
                                    <label class="form-label">Nombre del Cliente <span style="color: red;">*</span></label>
                                    <input type="text" name="nombre" id="nombre" class="form-control" required 
                                           maxlength="100" placeholder="">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Teléfono <span style="color: red;">*</span></label>
                                    <div style="display: flex; gap: 5px; ">
                                        <select name="prefijo_tel" id="prefijo_tel" class="form-control" style="width: 30%;">
                                            <option value=""></option>
                                            <option value="0412">0412</option>
                                            <option value="0422">0422</option>
                                            <option value="0414">0414</option>
                                            <option value="0424">0424</option>
                                            <option value="0416">0416</option>
                                            <option value="0426">0426</option>
                                        </select>
                                        <input type="text" name="nro_tel" maxlength="7" id="nro_tel" placeholder="Telefono" class="form-control"/>
                                    </div>


                                    <small class="text-muted">Código + 7 dígitos.</small>
                                </div>

                                <div class="col-md-7 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" id="email" class="form-control" 
                                           maxlength="100" placeholder="">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Dirección</label>
                                <textarea name="direccion" id="direccion" class="form-control" rows="3" 
                                          placeholder="Dirección completa del cliente..."></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Guardar Cliente</button>
                            <button type="button" class="btn btn-secondary" onclick="mostrarVista('listado')">Cancelar</button>
                            <input type="hidden" id="editar-cliente-id" name="id" value="">
                            <input type="hidden" id="action" value="">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
$('#btn-ir-crear').on('click', function() {
    limpiarFormulario(); 
    mostrarVista('crear'); 
});

$('#btn-volver-listado').on('click', function() {
    Swal.fire({
        icon: 'question',
        text: '¿Desea salir? Se perderán los cambios no guardados.',
        showCancelButton: true,
        confirmButtonText: 'Sí, salir',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (r.isConfirmed) {
            mostrarVista('listado');
        }
    });
});

var temporizador;

function cargarListado(page) {
    var params = { 
        action: 'listar_html',
        buscar: $('#filtro-buscar').val(),
        tipo_doc: $('#filtro-tipo-doc').val()
    };

    crudPostListadoPaginado(
        'gestionar_clientes_data.php',
        params,
        '#vista-listado tbody',
        '#paginacion-clientes',
        page || 1
    );
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#nombre').val('');
    $('#tipo_doc').val('');
    $('#nro_doc').val('');
    $('#telefono').val('');
    $('#email').val('');
    $('#direccion').val('');
    $('#editar-cliente-id').val('');
}

function mostrarVista(vista) {
    $('#vista-listado, #vista-crear').addClass('hidden').hide();
    $('#vista-' + vista).removeClass('hidden').fadeIn(250);
}

function editarCliente(data) {
    var tipo = (data.tipo_documento || '').toString().trim();
    var num = (data.numero_documento || '').toString().trim();

    // Registros antiguos: todo el documento en numero_documento (ej. V12345678)
    if (!tipo && num.length > 1 && /^[VJE]$/i.test(num.charAt(0))) {
        tipo = num.charAt(0).toUpperCase();
        num = num.substring(1).replace(/\D/g, '');
    } else {
        num = num.replace(/\D/g, '');
    }

    $('#tipo_doc').val(tipo || '');
    $('#nro_doc').val(num || '');

    if(data.telefono && data.telefono.length >= 11) {
        $('#prefijo_tel').val(data.telefono.substring(0, 4));
        $('#nro_tel').val(data.telefono.substring(4));
    }
    
    $('#nombre').val(data.nombre || '');
    $('#telefono').val(data.telefono || '');
    $('#email').val(data.email || '');
    $('#direccion').val(data.direccion || '');
    $('#editar-cliente-id').val(data.id);
    mostrarVista('crear');
}

function mensajeErrorAjax(xhr, textoPorDefecto) {
    textoPorDefecto = textoPorDefecto || 'Error de comunicación con el servidor.';
    if (xhr.responseJSON && xhr.responseJSON.message) {
        return xhr.responseJSON.message;
    }
    if (xhr.responseText) {
        try {
            var r = JSON.parse(xhr.responseText);
            if (r && r.message) {
                return r.message;
            }
        } catch (ignore) {}
    }
    if (xhr.status === 0) {
        return 'No hay conexión con el servidor.';
    }
    return textoPorDefecto;
}

function eliminarCliente(id) {
    Swal.fire({
        icon: 'question',
        text: '¿Está seguro de eliminar este cliente?',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then(function(r) {
        if (!r.isConfirmed) return;

        $.ajax({
            url: "gestionar_clientes_data.php",
            type: "POST",
            data: {
                action: 'eliminar',
                id: id
            },
            success: function(resp) {
                if (resp && resp.success) {
                    Swal.fire({ icon: 'success', text: resp.message });
                    mostrarVista('listado');
                    cargarListado();
                } else {
                    Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Respuesta inválida") });
                }
            },
            error: function(xhr) {
                console.error("Error:", xhr.responseText);
                Swal.fire({ icon: 'error', text: mensajeErrorAjax(xhr, 'Error al eliminar el cliente.') });
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    cargarListado(1);
    bindCrudPagination('#paginacion-clientes', cargarListado);
    $('#btn-toggle-filtros').on('click', function() {
        $('#panel-filtros').slideToggle(200);
    });

    $('.clase-filter-cliente, .clase-filtro-cliente').on('change keyup', function(e) {
        if (e.type === 'change') {
            cargarListado(1);
            return;
        }
        clearTimeout(temporizador);
        temporizador = setTimeout(function() {
            cargarListado(1);
        }, 350);
    });

    $('#btn-limpiar-filtros-cli').on('click', function() {
        $('#filtro-buscar').val('');
        $('#filtro-tipo-doc').val('');
        cargarListado(1);
    });
});

$('#nro_doc, #nro_tel').on('keypress', function(e) {
    if (e.which < 48 || e.which > 57) {
        return false;
    }
});

$('#nro_doc, #nro_tel').on('input', function() {
    this.value = this.value.replace(/\D/g, '');
});


$("#form-crear").on("submit", function(e) {
    e.preventDefault();

    // 1. Obtener valores y limpiar caracteres no numéricos
    const tipoDoc = $("#tipo_doc").val();
    const nroDoc  = $("#nro_doc").val().replace(/\D/g, ''); 
    const prefijo = $("#prefijo_tel").val();
    const nroTel  = $("#nro_tel").val().replace(/\D/g, '');
    const nombre  = $("#nombre").val().trim();

    if (nombre === '') {
        Swal.fire({ icon: 'warning', text: 'El nombre del cliente es obligatorio.' }); // Corregido aquí
        return;
    }

    // --- VALIDACIÓN DE DOCUMENTO (Cédula/RIF) ---
    if (tipoDoc === '') {
        Swal.fire({ icon: 'warning', text: 'Debe seleccionar el tipo de documento (V, J, E).' });
        return;
    }

    if (tipoDoc === 'J') {
        // Exactamente 9
        if (nroDoc.length !== 9) {
            Swal.fire({ icon: 'warning', text: 'Para RIF (J), el número debe tener exactamente 9 dígitos.' });
            return;
        }
    } else {
        // 7 u 8
        if (nroDoc.length < 7 || nroDoc.length > 8) {
            Swal.fire({ icon: 'warning', text: 'La cédula debe tener entre 7 y 8 dígitos.' });
            return;
        }
    }

    // --- VALIDACIÓN DE TELÉFONO ---
    if (prefijo === '') {
        Swal.fire({ icon: 'warning', text: 'Debe seleccionar un código de área/operadora.' });
        return;
    }

    if (nroTel.length !== 7) {
        Swal.fire({ icon: 'warning', text: 'El número de teléfono debe tener exactamente 7 dígitos después del código.' });
        return;
    }

    var idCliente = $("#editar-cliente-id").val();
    const datos = {
        action: idCliente ? "editar" : "crear",
        id: idCliente || null,
        tipo_documento: tipoDoc,
        documento: nroDoc, 
        nombre: nombre,
        telefono: prefijo + nroTel,  // 04121234567
        email: $("#email").val() || "",
        direccion: $("#direccion").val() || ""
    };

    $.ajax({
        url: "gestionar_clientes_data.php",
        type: "POST",
        data: datos,
        dataType: 'json',
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
            Swal.fire({ icon: 'error', text: mensajeErrorAjax(xhr) });
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

