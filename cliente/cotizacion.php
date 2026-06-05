<?php require_once('../template/header.php'); ?>
<?php require_once '../connection/connection.php'; ?>
<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Cotizaciones</h2>
                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row form-group">
                            <div class="col-sm-12">
                                <div  aria-label="Acciones de cotización">
                                    <button class="btn btn-success" id="btn-ir-crear" title="Crear Nueva Cotización" data-toggle="tooltip">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                    <button class="btn btn-info" id="btn-toggle-filtros" title="Filtros" data-toggle="tooltip">
                                        <i class="fas fa-filter"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div id="panel-filtros" style="display: none; margin-bottom: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1);padding: 15px; border-radius: 5px; border: 1px solid #ddd;">
                            <div class="row">
                                <div class="col-sm-3">
                                    <label for="filtro-codigo">Código Cotización</label>
                                    <input type="text" id="filtro-codigo" class="form-control clase-filtro" placeholder="Buscar Numero de Cotización...">
                                </div>
                                <div class="col-sm-4">
                                    <label for="filtro-cliente">Cliente</label>
                                    <input type="text" id="filtro-cliente" class="form-control clase-filtro" placeholder="Buscar Cliente...">
                                </div>
                                <div class="col-sm-3">
                                    <label for="filtro-estado">Estado</label>
                                    <select id="filtro-estado" class="form-control clase-filtro">
                                        <option value="">Todos los estados</option>
                                        <option value="Enviada">Enviada</option>
                                        <option value="Aprobada">Aprobado</option>
                                        <option value="Rechazada">Rechazado</option>
                                    </select>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-sm-3">
                                    <label for="filtro-desde">Desde</label>
                                    <input type="date" id="filtro-desde" class="form-control clase-filtro">
                                </div>
                                <div class="col-sm-3">
                                    <label for="filtro-hasta">Hasta</label>
                                    <input type="date" id="filtro-hasta" class="form-control clase-filtro">
                                </div>
                                <div class="col-sm-2" style="margin-top: 25px;">
                                    <button type="button" class="btn btn-secondary btn-block" id="btn-limpiar-filtros">Limpiar</button>
                                </div>
                            </div>
                        </div>
                        <div class="table-container">
                        <table class="recipe-table">
                            <thead>
                                <tr>
                                    <th style="width: 1%;">#</th>
                                    <th style="width: 15%;">Código</th>
                                    <th style="width: 35%;">Cliente</th>
                                    <th style="width: 20%;">Fecha</th>
                                    <th style="width: 20%;">Total</th>
                                    <th style="width: 15%;">Estado</th>
                                    <th style="width: 15%;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
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
                                    <button type="button" class="btn btn-success" id="btn-generar-cot" style="margin-left: 8px; margin-top: 8px;">
                                        <i class="fas fa-save"></i> Confirmar y guardar cotización
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
        
        // 2. Setear Cliente
        $('#select-cliente').val(data.id_cliente).trigger('change');
        
        // 3. Pequeño delay para dejar que el select de presupuestos se cargue por el evento 'change' del cliente
        setTimeout(() => {
            if(data.codigo_presupuesto_origen !== 'VENTA DIRECTA'){
                $('#select-presupuesto').val(data.codigo_presupuesto_origen).trigger('change');
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

    $(document).ready(function() {
        // 1. Inicialización de complementos
        $('#select-cliente, #select-presupuesto, #manual-producto').select2();

        // --- LÓGICA DE NAVEGACIÓN ---

        $('#btn-ir-crear').on('click', function() {
            $('#vista-listado').fadeOut(200, function() {
                $('#vista-crear').removeClass('hidden').fadeIn();
                limpiarFormulario();
                cargarProductosManuales(); 
            });
        });

        $('#btn-toggle-filtros').on('click', function() {
            $('#panel-filtros').slideToggle(300);
        });

        $('.clase-filtro').on('keyup change', function() {
            filtrarTabla();
        });

        function filtrarTabla() {
            var codigo = $('#filtro-codigo').val().toLowerCase().trim();
            var cliente = $('#filtro-cliente').val().toLowerCase().trim();
            var estado = $('#filtro-estado').val().toLowerCase().trim();
            var fechaDesde = $('#filtro-desde').val(); // YYYY-MM-DD
            var fechaHasta = $('#filtro-hasta').val(); // YYYY-MM-DD

            $('#vista-listado table tbody tr').each(function() {
                var fila = $(this);
                
                var textoCodigo = (fila.data('codigo') || '').toString().toLowerCase();
                var textoCliente = (fila.data('cliente') || '').toString().toLowerCase();
                var textoEstado = (fila.data('estado') || '').toString().toLowerCase();
                var fechaFila = fila.data('fecha'); 


                // Condición lógica: la fila debe coincidir con los 3 filtros a la vez
                var coincideCodigo = (codigo === "" || textoCodigo.includes(codigo));
                var coincideCliente = (cliente === "" || textoCliente.includes(cliente));
                var coincideEstado = (estado === "" || textoEstado.includes(estado));
                var coincideFecha = true;
                if (fechaFila) {
                    if (fechaDesde !== "" && fechaFila < fechaDesde) {
                        coincideFecha = false;
                    }
                    if (fechaHasta !== "" && fechaFila > fechaHasta) {
                        coincideFecha = false;
                    }
                }

                if (coincideCodigo && coincideCliente && coincideEstado && coincideFecha) {
                    fila.show();
                } else {
                    fila.hide(); 
                }
            });
        }

        $('#btn-limpiar-filtros').on('click', function() {
            $('.clase-filtro').val(''); 
            filtrarTabla(); 
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
            $('#select-cliente').val('').trigger('change');
            $('#select-presupuesto').val('').trigger('change').prop('disabled', true);
            $('#tabla-cotizador-body').empty();
            $('#contenedor-items').hide();
            $('#gran-total-display').text('$0.00');
        }

        $('#btn-generar-cot').on('click', function() {
            let itemsCotizacion = [];
            let totalFinal = parseFloat($('#gran-total-display').text().replace('$', ''));
            let idCliente = $('#select-cliente').val();

            if(!idCliente) { Swal.fire({ icon: 'warning', text: "Por favor, seleccione un cliente." }); return; }

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

            $.ajax({
                url: 'cotizacion_data.php',
                type: 'POST',
                data: {
                    action: 'guardar_cotizacion',
                    id_cliente: idCliente,
                    codigo_presupuesto: $('#select-presupuesto').val(),
                    items: JSON.stringify(itemsCotizacion), // Enviamos como string JSON para mayor seguridad
                    total_cotizacion: totalFinal
                },
                dataType: 'json',
                success: function(resp) {
                    if (resp.success) {
                        Swal.fire({ icon: 'success', text: "Cotización guardada exitosamente." });
                        location.reload(); 
                    } else {
                        Swal.fire({ icon: 'error', text: "Error: " + resp.mensaje });
                    }
                }
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