<?php require_once('../template/header.php'); ?>
<?php require_once '../connection/connection.php'; ?>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Cotizaciones</h2>
                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-success" id="btn-ir-crear">
                                    <i class="fas fa-plus"></i> Crear Nueva Cotización
                                </button>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="recipe-table" id="tabla-listado-cotizaciones">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Cliente</th>
                                        <th>Modalidad pago</th>
                                        <th>Total</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody-listado-cotizaciones">
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div id="vista-crear" class="hidden">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <button type="button" class="btn btn-secondary" id="btn-volver-listado">
                                    <i class="fas fa-arrow-left"></i> Volver al Listado
                                </button>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 col-sm-12" style="margin-bottom: 15px;">
                                <label class="form-label">Seleccionar cliente <span class="text-danger">*</span></label>
                                <select id="select-cliente" class="form-control" style="width: 100%;">
                                    <option value=""></option>
                                    <?php
                                    $query = mysqli_query($conn, "SELECT id, nombre FROM clientes ORDER BY nombre ASC");
                                    while ($c = mysqli_fetch_assoc($query)) {
                                        echo "<option value='" . (int) $c['id'] . "'>" . htmlspecialchars($c['nombre'], ENT_QUOTES, 'UTF-8') . "</option>";
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 col-sm-12">
                                <label class="form-label">Presupuesto base</label>
                                <select id="select-presupuesto" class="form-control" style="width: 100%;" disabled>
                                    <option value="">Seleccione un cliente primero...</option>
                                </select>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-md-6 col-sm-12">
                                <label class="form-label">Modalidad de pago <span class="text-danger">*</span></label>
                                <select id="select-modalidad-pago" class="form-control" required>
                                    <option value="contado">Contado</option>
                                    <option value="financiada">Financiada</option>
                                </select>
                            </div>
                            <div class="col-md-6 col-sm-12" id="grupo-porcentaje-minimo" style="display:none;">
                                <label class="form-label">% mínimo del pago inicial <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="number" id="porcentaje-pago-minimo" class="form-control" value="60" min="1" max="100" step="0.01">
                                   
                                </div>
                            </div>
                        </div>

                        <div class="row mb-3">
                            <div class="col-xs-12">
                                <div class="well well-sm" style="margin-bottom: 0; background-color: #f8f9fa; border-color: #dee2e6;">
                                    <h5 style="color: #0056b3; margin-bottom: 15px;">
                                        <i class="fas fa-search"></i> Agregar producto manualmente (venta presencial)
                                    </h5>
                                    <div class="row">
                                        <div class="col-md-5 col-sm-12" style="margin-bottom: 10px;">
                                            <label class="form-label">Producto</label>
                                            <select id="manual-producto" class="form-control" style="width:100%">
                                                <option value="">Buscar producto...</option>
                                            </select>
                                        </div>
                                        <div class="col-md-2 col-sm-12" style="margin-bottom: 10px;">
                                            <label class="form-label">Cantidad</label>
                                            <input type="number" id="manual-cantidad" class="form-control" value="1" min="1">
                                        </div>
                                        <div class="col-md-5 col-sm-12" style="padding-top: 24px;">
                                            <button type="button" class="btn btn-primary btn-block" onclick="agregarProductoManual()">
                                                <i class="fas fa-plus"></i> Añadir a la tabla
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="contenedor-items" style="display: none; margin-top: 20px;">
                            <hr style="margin: 20px 0; border-color: #dee2e6;">

                            <h5 style="color: #0056b3; margin-bottom: 15px;">Detalle del presupuesto seleccionado</h5>

                            <div class="table-container">
                                <table class="recipe-table">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cant.</th>
                                            <th>Talla</th>
                                            <th>Precio base</th>
                                            <th>Personalización</th>
                                            <th>Notas</th>
                                            <th>Subtotal</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tabla-cotizador-body"></tbody>
                                </table>
                            </div>

                            <div class="row" style="margin-top: 15px;">
                                <div class="col-xs-12 text-right">
                                    <span style="font-weight: bold;">Total cotización:</span>
                                    <span id="gran-total-display" style="color: #0056b3; font-size: 1.1em; font-weight: bold;">$0.00</span>
                                    <span class="visible-xs-block" style="height: 10px; display: inline-block;"></span>
                                    <input type="hidden" id="editar-cotizacion-id" value="">
                                    <button type="button" class="btn btn-success" id="btn-generar-cot" style="margin-left: 8px; margin-top: 8px;">
                                        <i class="fas fa-save"></i> <span id="btn-generar-cot-texto">Confirmar y guardar cotización</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="resultadoCot" style="margin-top: 15px;"></div>
                </div>
            </div>
        </div>
    </div>

<script src="../assets/js/select2.min.js"></script>
<script>

    function mostrarVista(vista) {
        if (vista === 'crear') {
            $('#vista-listado').fadeOut(200, function() {
                $('#vista-crear').removeClass('hidden').fadeIn();
            });
        } else {
            $('#vista-crear').fadeOut(200, function() {
                $('#vista-listado').fadeIn();
            });
        }
    }

    function actualizarTotalGeneral() {
        let total = 0;
        $('.row-subtotal').each(function() {
            total += parseFloat($(this).text().replace('$', '')) || 0;
        });
        $('#gran-total-display').text('$' + total.toFixed(2));
    }


    function generarFilaTabla(id, nombre, tallaId, tallaNombre, cant, precio, origen) {
        let subtotal = (precio * cant).toFixed(2);
        let badge = origen === 'manual' ? '<small style="color:#28a745; display:block;">(Venta Directa)</small>' : '';
        
        return `
            <tr
                data-id-receta="${id}"
                data-id-talla="${tallaId}"
                data-precio-original="${precio}"
                data-origen="${origen}">
                <td><strong>${nombre}</strong></td>
                <td class="col-cant text-center" style="font-weight: bold;">${cant}</td>
                <td class="text-muted">${tallaNombre}</td>
                <td>$${precio}</td>
                <td>
                    <select class="form-control perso-sel">
                        <option value="" data-precio="0">Ninguna</option>
                        <?php
                            $resExtras = mysqli_query($conn, "SELECT id, nombre, costo_unitario FROM insumos WHERE adicional = 1 ORDER BY nombre ASC");
                            while($e = mysqli_fetch_assoc($resExtras)){
                                echo "<option value='".$e['id']."' data-precio='".$e['costo_unitario']."'>".$e['nombre']." (+$".$e['costo_unitario'].")</option>";
                            }
                        ?>
                    </select>
                </td>
                <td><input type="text" class="form-control nota-input" placeholder="Bordado, color, etc."></td>
                <td class="row-subtotal" style="font-weight: bold;">$${subtotal}</td>
                <td>
                    <button type="button" class="btn btn-danger btn-sm btn-eliminar-fila" title="Quitar línea"><i class="fas fa-trash"></i></button>
                </td>
            </tr>`;
    }

    function verDetalles(data) {
        console.log("Editando Cotización:", data);
        
        // 1. Limpiar tabla antes de cargar
        $('#tabla-cotizador-body').empty();
        
        // 2. Setear modo edición, cliente y modalidad de pago
        $('#editar-cotizacion-id').val(data.id_cotizacion || '');
        $('#btn-generar-cot-texto').text('Guardar cambios');
        $('#select-cliente').val(data.id_cliente).trigger('change');
        $('#select-modalidad-pago').val(data.modalidad_pago || 'contado');
        $('#porcentaje-pago-minimo').val(
            data.porcentaje_pago_minimo != null && data.porcentaje_pago_minimo !== ''
                ? data.porcentaje_pago_minimo
                : '60'
        );
        actualizarCampoPorcentajeMinimo();
        
        // 3. Tras cargar presupuestos del cliente, seleccionar el origen sin recargar la tabla (evita pisar el detalle al editar)
        var presupuestoOrigen = data.codigo_presupuesto_origen || '';
        setTimeout(function() {
            if (presupuestoOrigen && presupuestoOrigen !== 'VENTA DIRECTA') {
                $('#select-presupuesto').val(presupuestoOrigen);
            }
        }, 500);

        // 4. Cargar los productos REALES de esta cotización
        $.ajax({
            url: 'cotizacion_data.php',
            type: 'GET',
            data: { action: 'obtener_detalle_cotizacion', id_cotizacion: data.id_cotizacion },
            dataType: 'json',
            success: function(items) {
                let html = "";
                items.forEach(item => {
                    html += generarFilaTabla(
                        item.id_receta, 
                        item.nombre_producto, 
                        item.id_talla, 
                        item.talla_nombre, 
                        item.cantidad, 
                        item.precio_unitario, 
                        item.origen 
                    );
                });
                $('#tabla-cotizador-body').html(html);
                
                // 5. Asignar las personalizaciones y notas guardadas
                items.forEach((item, index) => {
                    let fila = $('#tabla-cotizador-body tr').eq(index);
                    fila.find('.perso-sel').val(item.id_personalizacion);
                    fila.find('.nota-input').val(item.notas);
                });

                $('#contenedor-items').fadeIn();
                actualizarTotalGeneral();
                mostrarVista('crear');
            }
        });
    }

    function actualizarCampoPorcentajeMinimo() {
        var esFinanciada = $('#select-modalidad-pago').val() === 'financiada';
        if (esFinanciada) {
            $('#grupo-porcentaje-minimo').show();
            $('#porcentaje-pago-minimo').prop('required', true);
        } else {
            $('#grupo-porcentaje-minimo').hide();
            $('#porcentaje-pago-minimo').prop('required', false);
        }
    }

    $(document).ready(function() {
        // 1. Inicialización de complementos
        $('#select-cliente, #select-presupuesto, #manual-producto').select2();
        actualizarCampoPorcentajeMinimo();
        $('#select-modalidad-pago').on('change', actualizarCampoPorcentajeMinimo);

        // --- LÓGICA DE NAVEGACIÓN ---

        $('#btn-ir-crear').on('click', function() {
            $('#vista-listado').fadeOut(200, function() {
                $('#vista-crear').removeClass('hidden').fadeIn();
                limpiarFormulario();
                cargarProductosManuales(); 
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

        // --- LÓGICA DE VENTA POR PRESUPUESTO ---

        $('#select-cliente').on('change', function() {
            let idCliente = $(this).val();
            if (!idCliente) return;
            
            $('#select-presupuesto').prop('disabled', false).empty().append('<option>Cargando...</option>');

            $.ajax({
                url: 'cotizacion_data.php',
                type: 'GET',
                data: { action: 'buscar_presupuestos_cliente', id_cliente: idCliente },
                dataType: 'json',
                success: function(resp) {
                    let options = '<option value="">Seleccione presupuesto...</option>';
                    resp.forEach(p => { options += `<option value="${p.id}">${p.text}</option>`; });
                    $('#select-presupuesto').html(options).trigger('change');
                }
            });
        });

        $('#select-presupuesto').on('change', function() {
            let codigo = $(this).val();
            if (!codigo) return;

            $.ajax({
                url: 'cotizacion_data.php',
                type: 'GET',
                data: { action: 'obtener_detalle_presupuesto', codigo: codigo },
                dataType: 'json',
                success: function(resp) {
                    let html = "";
                    resp.forEach(item => {
                        // Usamos los datos que vienen del presupuesto
                        html += generarFilaTabla(item.id_receta, item.nombre, item.id_talla, item.talla_nombre, item.cantidad, item.precio_unitario, 'presupuesto');
                    });
                    $('#tabla-cotizador-body').html(html);
                    $('#contenedor-items').fadeIn();
                    actualizarTotalGeneral();
                }
            });
        });

        // --- LÓGICA DE VENTA MANUAL (La talla ya viene en la receta/producto) ---

        function cargarProductosManuales() {
            $.ajax({
                url: 'cotizacion_data.php',
                type: 'GET',
                data: { action: 'listar_productos_manual' },
                dataType: 'json',
                success: function(resp) {
                    let options = '<option value="">Buscar producto...</option>';
                    
                    // Recorremos lo que trae el PHP
                    resp.forEach(p => {
                        // Guardamos los datos importantes en atributos "data-"
                        options += `<option value="${p.id}" 
                                            data-precio="${p.precio}"
                                            data-precio-mayor="${p.mayor}" 
                                            data-talla-id="${p.id_talla}" 
                                            data-talla-nom="${p.talla_nombre}"
                                            data-nombre="${p.nombre}">
                                            ${p.nombre} - [Talla: ${p.talla_nombre}] ($${p.precio})
                                    </option>`;
                    });
                    
                    // Inyectamos las opciones en el select y refrescamos Select2
                    $('#manual-producto').html(options).trigger('change');
                },
                error: function() {
                    console.error("No se pudieron cargar los productos para venta manual.");
                }
            });
        }

        // Llamar a la función cuando se abre la vista de crear
        $('#btn-ir-crear').on('click', function() {
            cargarProductosManuales();
        });

        window.agregarProductoManual = function() {
            let select = $("#manual-producto option:selected");
            let idProd = $('#manual-producto').val();
            
            if (!idProd) { Swal.fire({ icon: 'warning', text: "Seleccione un producto." }); return; }

            let nombreLimpio = select.data('nombre'); 
            let precioDetal  = parseFloat(select.data('precio')) || 0;
            let precioMayor  = parseFloat(select.data('precio-mayor')) || precioDetal; // Si no hay precio mayor, usa el detal
            let tallaId      = select.data('talla-id');
            let tallaNombre  = select.data('talla-nom');
            let cantidadNueva = parseInt($('#manual-cantidad').val());

            if (cantidadNueva < 1) { Swal.fire({ icon: 'warning', text: "Cantidad inválida." }); return; }

            // --- VALIDACIÓN DE EXISTENCIA ---
            let existe = false;
            $('#tabla-cotizador-body tr').each(function() {
                let fila = $(this);
                let idFila = fila.data('id-receta');
                let tallaFila = fila.data('id-talla');

                if (idFila == idProd && tallaFila == tallaId) {
                    existe = true;
                    
                    // 1. Sumar cantidades
                    let cantActual = parseInt(fila.find('.col-cant').text());
                    let cantFinal = cantActual + cantidadNueva;
                    
                    // 2. Determinar precio (Si >= 12, precio al mayor)
                    let precioAplicar = (cantFinal >= 12) ? precioMayor : precioDetal;
                    
                    // 3. Actualizar la fila existente
                    fila.find('.col-cant').text(cantFinal);
                    fila.data('precio-original', precioAplicar); // Actualizamos el data para cálculos de personalización
                    fila.find('td:nth-child(4)').text('$' + precioAplicar.toFixed(2)); // Columna de precio base
                    
                    // 4. Recalcular subtotal de la fila
                    // Si hay personalización seleccionada, debemos sumarla
                    let costoExtra = parseFloat(fila.find('.perso-sel option:selected').data('precio')) || 0;
                    let nuevoSubtotal = (precioAplicar + costoExtra) * cantFinal;
                    
                    fila.find('.row-subtotal').text('$' + nuevoSubtotal.toFixed(2));
                    
                    return false; // Romper el loop .each()
                }
            });

            // --- SI NO EXISTE, AGREGAR FILA NUEVA ---
            if (!existe) {
                let precioAplicar = (cantidadNueva >= 12) ? precioMayor : precioDetal;
                let filaHtml = generarFilaTabla(idProd, nombreLimpio, tallaId, tallaNombre, cantidadNueva, precioAplicar, 'manual');
                $('#tabla-cotizador-body').append(filaHtml);
            }

            $('#contenedor-items').fadeIn();
            actualizarTotalGeneral();

            // Reset de campos
            $('#manual-producto').val('').trigger('change');
            $('#manual-cantidad').val(1);
        };

        // --- EVENTOS DINÁMICOS ---

        $(document).on('change', '.perso-sel', function() {
            let fila = $(this).closest('tr');
            let precioBase = parseFloat(fila.data('precio-original'));
            let costoExtra = parseFloat($(this).find(':selected').data('precio')) || 0;
            let cantidad = parseInt(fila.find('.col-cant').text());

            let subtotal = (precioBase + costoExtra) * cantidad;
            fila.find('.row-subtotal').text('$' + subtotal.toFixed(2));
            actualizarTotalGeneral();
        });

        $(document).on('click', '.btn-eliminar-fila', function() {
            $(this).closest('tr').remove();
            actualizarTotalGeneral();
            if($('#tabla-cotizador-body tr').length === 0) $('#contenedor-items').hide();
        });

        // --- CÁLCULOS Y GUARDADO FINAL ---

        
        function limpiarFormulario() {
            $('#editar-cotizacion-id').val('');
            $('#btn-generar-cot-texto').text('Confirmar y guardar cotización');
            $('#select-cliente').val('').trigger('change');
            $('#select-presupuesto').val('').trigger('change').prop('disabled', true);
            $('#select-modalidad-pago').val('contado');
            $('#porcentaje-pago-minimo').val('60');
            actualizarCampoPorcentajeMinimo();
            $('#tabla-cotizador-body').empty();
            $('#contenedor-items').hide();
            $('#gran-total-display').text('$0.00');
        }

        $('#btn-generar-cot').on('click', function() {
            let itemsCotizacion = [];
            let totalFinal = parseFloat($('#gran-total-display').text().replace('$', ''));
            let idCliente = $('#select-cliente').val();

            if(!idCliente) { Swal.fire({ icon: 'warning', text: "Por favor, seleccione un cliente." }); return; }

            let modalidadPago = $('#select-modalidad-pago').val();
            if (!modalidadPago) {
                Swal.fire({ icon: 'warning', text: 'Seleccione si la cotización será de contado o financiada.' });
                return;
            }

            let porcentajeMinimo = null;
            if (modalidadPago === 'financiada') {
                porcentajeMinimo = parseFloat($('#porcentaje-pago-minimo').val());
                if (isNaN(porcentajeMinimo) || porcentajeMinimo <= 0 || porcentajeMinimo > 100) {
                    Swal.fire({ icon: 'warning', text: 'Indique un porcentaje mínimo de pago entre 1 y 100.' });
                    return;
                }
            }

            $('#tabla-cotizador-body tr').each(function() {
                let fila = $(this);
                let cant = parseInt(fila.find('.col-cant').text());
                let subt = parseFloat(fila.find('.row-subtotal').text().replace('$', ''));
                
                itemsCotizacion.push({
                    id_receta: fila.data('id-receta'), 
                    id_talla: fila.data('id-talla'),
                    cantidad: cant,
                    id_personalizacion: fila.find('.perso-sel').val(),
                    notas: fila.find('.nota-input').val(),
                    precio_unitario: (subt / cant).toFixed(2),
                    subtotal: subt,
                    origen: fila.data('origen')
                });
            });

            if(itemsCotizacion.length === 0) { Swal.fire({ icon: 'warning', text: "Agregue al menos un producto." }); return; }

            var idCotizacionEdit = $('#editar-cotizacion-id').val();
            var payload = {
                action: 'guardar_cotizacion',
                id_cliente: idCliente,
                codigo_presupuesto: $('#select-presupuesto').val(),
                modalidad_pago: modalidadPago,
                porcentaje_pago_minimo: porcentajeMinimo,
                items: JSON.stringify(itemsCotizacion),
                total_cotizacion: totalFinal
            };
            if (idCotizacionEdit) {
                payload.id_cotizacion = idCotizacionEdit;
            }

            $.ajax({
                url: 'cotizacion_data.php',
                type: 'POST',
                data: payload,
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) {
                        Swal.fire({ icon: 'success', text: resp.mensaje || "Cotización guardada exitosamente." });
                        location.reload(); 
                    } else {
                        Swal.fire({ icon: 'error', text: "Error: " + resp.mensaje });
                    }
                }
            });
        });

        $(document).on('click', '.btn-eliminar-cot', function() {
            var btn = $(this);
            var idCot = btn.data('id');
            var codigo = btn.data('codigo') || ('#' + idCot);

            Swal.fire({
                icon: 'warning',
                title: '¿Eliminar cotización?',
                text: 'Se eliminará ' + codigo + ' y todos sus detalles. Esta acción no se puede deshacer.',
                showCancelButton: true,
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar',
                confirmButtonColor: '#d33'
            }).then(function(r) {
                if (!r.isConfirmed) return;

                $.ajax({
                    url: 'cotizacion_data.php',
                    type: 'POST',
                    data: { action: 'eliminar_cotizacion', id_cotizacion: idCot },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire({ icon: 'success', text: resp.mensaje || 'Cotización eliminada.' });
                            cargarListaCotizaciones();
                        } else {
                            Swal.fire({ icon: 'error', text: resp.mensaje || 'No se pudo eliminar.' });
                        }
                    },
                    error: function() {
                        Swal.fire({ icon: 'error', text: 'Error de comunicación con el servidor.' });
                    }
                });
            });
        });

        // Cargar historial al cargar la página
        cargarListaCotizaciones();

        function cargarListaCotizaciones() {
            $.ajax({
                url: 'cotizacion_data.php',
                type: 'GET',
                data: { action: 'listar_cotizaciones' },
                success: function(respuestaHTML) {
                    $('#tbody-listado-cotizaciones').html(respuestaHTML);
                }
            });
        }
    });
</script>
<?php require_once '../template/footer.php'; ?>