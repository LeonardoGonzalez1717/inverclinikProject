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
                
                <div class="row mb-3" id="vista-botones">
                    <div class="col-md-12">
                        <button class="btn btn-success" onclick="mostrarVista('crear');limpiarFormulario();">Crear Nuevo Cliente</button>
                    </div>
                </div>

                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <h5 class="subtitle">Lista de Clientes</h5>
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
                    </div>

                    <div id="vista-crear" class="hidden">
                        <h5 class="subtitle">Crear/Editar Cliente</h5>
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
    $.post('gestionar_clientes_data.php', { action: 'listar_html' }, function(html) {
        $('#vista-listado tbody').html(html);
    });
    limpiarFormulario();
}

function limpiarFormulario() {
    $('#form-crear')[0].reset();
    $('#nombre').val('');
    $('#tipo_documento').val('');
    $('#numero_documento').val('');
    $('#telefono').val('');
    $('#email').val('');
    $('#direccion').val('');
    $('#editar-cliente-id').val('');
}

function editarCliente(data) {
    $('#nombre').val(data.nombre || '');
    $('#tipo_documento').val(data.tipo_documento || '');
    $('#numero_documento').val(data.numero_documento || '');
    $('#telefono').val(data.telefono || '');
    $('#email').val(data.email || '');
    $('#direccion').val(data.direccion || '');
    $('#editar-cliente-id').val(data.id);
    mostrarVista('crear');
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
                    cargarListado();
                } else {
                    Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Respuesta inválida") });
                }
            },
            error: function(xhr) {
                console.error("Error:", xhr.responseText);
                var resp = JSON.parse(xhr.responseText);
                Swal.fire({ icon: 'error', text: "Error: " + (resp ? resp.message : "Error al eliminar cliente") });
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', function() {
    mostrarVista('listado');
    cargarListado();
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
        Swal.fire({ icon: 'warning', text: 'El nombre del proveedor es obligatorio.' });
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
        error: function() {
            Swal.fire({ icon: 'error', text: "Error de comunicación con el servidor." });
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

