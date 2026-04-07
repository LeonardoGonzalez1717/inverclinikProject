<?php require_once('../template/header.php'); ?>
<?php include '../connection/connection.php'; ?>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotizaciones | Inverclinik</title>
    <style>
        .btn-volver { background: #6c757d; color: white; border: none; padding: 8px 15px; border-radius: 5px; cursor: pointer; margin-bottom: 15px; }
    </style>
</head>

<body>
    <div class="main-content">
        <div class="container-wrapper">
            <div class="container-inner">
                <h2 class="main-title">Módulo de Cotizaciones</h2>
                <div id="contenedor-vistas">
                    <div id="vista-listado">
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <button class="btn btn-success" id="btn-ir-crear">
                                    <i class="fas fa-plus"></i> Crear Nueva Cotización
                                </button>
                            </div>
                        </div>
                        <h5 class="subtitle">Historial de Cotizaciones</h5>
                        <div class="table-container">
                            <table class="recipe-table">
                                <thead>
                                    <tr>
                                        <th>Código</th>
                                        <th>Cliente</th>
                                        <th>Origen</th>
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
                        <button class="btn-volver" id="btn-volver-listado">
                            <i class="fas fa-arrow-left"></i> Volver al Listado
                        </button>
                        
                        <div id="form-cotizacion">
                            <div id="busqueda-manual" class="row mb-3 p-3" style="background: #f8f9fa; border-radius: 8px; border: 1px solid #ddd; margin: 0 1px;">
                                <div class="col-md-12"><h6 class="text-muted"><i class="fas fa-search"></i> Agregar producto manualmente (Venta Presencial)</h6></div>
                                <div class="col-md-4">
                                    <label class="form-label small">Producto</label>
                                    <select id="manual-producto" class="form-control" style="width:100%">
                                        <option value="">Buscar producto...</option>
                                       
                                    </select>
                                </div>
                                
                                <div class="col-md-2">
                                    <label class="form-label small">Cant.</label>
                                    <input type="number" id="manual-cantidad" class="form-control" value="1" min="1">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label d-block">&nbsp;</label>
                                    <button type="button" class="btn btn-primary btn-block" onclick="agregarProductoManual()">
                                        <i class="fas fa-plus"></i> Añadir a Tabla
                                    </button>
                                </div>
                            </div>
                            <div class="row mb-4" style="display: flex; gap: 20px;">
                                <div style="flex: 1;">
                                    <label class="form-label">Seleccionar Cliente</label>
                                    <select id="select-cliente" class="form-control" style="width: 100%;">
                                        <option value=""></option>
                                        <?php
                                        $query = mysqli_query($conn, "SELECT id, nombre FROM clientes ORDER BY nombre ASC");
                                        while($c = mysqli_fetch_assoc($query)){
                                            echo "<option value='".$c['id']."'>".$c['nombre']."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div style="flex: 1;">
                                    <label class="form-label">Presupuesto Base</label>
                                    <select id="select-presupuesto" class="form-control" style="width: 100%;" disabled>
                                        <option value="">Seleccione un cliente primero...</option>
                                    </select>
                                </div>
                            </div>

                            <div id="contenedor-items" style="display: none; margin-top: 20px;">
                                <h3 style="font-size: 1.1rem; margin-bottom: 15px; color: #555;">Detalles del Presupuesto Seleccionado</h3>
                                <div class="table-responsive">
                                    <table class="table" style="width: 100%; border-collapse: collapse;">
                                        <thead>
                                            <tr style="border-bottom: 2px solid #eee; text-align: left;">
                                                <th>Producto</th>
                                                <th>Cant.</th>
                                                <th width="100">Talla</th>
                                                <th width="120">Precio Base</th>
                                                <th width="180">Personalización</th>
                                                <th>Notas</th>
                                                <th width="130">Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tabla-cotizador-body"></tbody>
                                    </table>
                                </div>

                                <div class="mt-4" style="text-align: right; border-top: 2px solid #eee; padding-top: 15px;">
                                    Total Cotizacion: <span id="gran-total-display" style="color: #005bbe;">$0.00</span> &nbsp;
                                    <button type="button" class="btn-editar" id="btn-generar-cot" style="background-color: #28a745; color: white; padding: 12px 25px; border: none; border-radius: 5px; cursor: pointer; font-weight: bold;">
                                        <i class="fas fa-save"></i> Confirmar y Guardar Cotización
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="resultadoCot" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>

<script src="../assets/js/jquery-3.7.1.min.js"></script>
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

    function verDetalles(data) {
        console.log(data);
        
        $('#select-cliente').val(data.id_cliente).trigger('change');
        $('#select-presupuesto').val(data.id_presupuesto).trigger('change');
        
        mostrarVista('crear');
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

        $('#btn-volver-listado').on('click', function() {
            if(confirm("¿Desea salir? Se perderán los cambios no guardados.")) {
                $('#vista-crear').fadeOut(200, function() {
                    $('#vista-listado').fadeIn();
                });
            }
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
            
            if (!idProd) return alert("Seleccione un producto.");

            let nombreLimpio = select.data('nombre'); 
            let precioDetal  = parseFloat(select.data('precio')) || 0;
            let precioMayor  = parseFloat(select.data('precio-mayor')) || precioDetal; // Si no hay precio mayor, usa el detal
            let tallaId      = select.data('talla-id');
            let tallaNombre  = select.data('talla-nom');
            let cantidadNueva = parseInt($('#manual-cantidad').val());

            if (cantidadNueva < 1) return alert("Cantidad inválida.");

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
        // --- HELPER: GENERADOR DE FILAS ---

        function generarFilaTabla(id, nombre, tallaId, tallaNombre, cant, precio, origen) {
            let subtotal = (precio * cant).toFixed(2);
            let badge = origen === 'manual' ? '<small style="color:#28a745; display:block;">(Venta Directa)</small>' : '';
            
            return `
                <tr style="border-bottom: 1px solid #eee;" 
                    data-id-receta="${id}" 
                    data-id-talla="${tallaId}"
                    data-precio-original="${precio}"
                    data-origen="${origen}">
                    <td><strong>${nombre}</strong></td>
                    <td class="col-cant" style="font-weight:bold; text-align:center;">${cant}</td>
                    <td style="color:#555;">${tallaNombre}</td>
                    <td>$${precio}</td>
                    <td>
                        <select class="form-control perso-sel">
                            <option value="" data-precio="0">Ninguna</option>
                            <?php
                                $resExtras = mysqli_query($conn, "SELECT id, nombre, costo_unitario FROM insumos ORDER BY nombre ASC");
                                while($e = mysqli_fetch_assoc($resExtras)){
                                    echo "<option value='".$e['id']."' data-precio='".$e['costo_unitario']."'>".$e['nombre']." (+$".$e['costo_unitario'].")</option>";
                                }
                            ?>
                        </select>
                    </td>
                    <td><input type="text" class="form-control nota-input" placeholder="Bordado, color, etc..."></td>
                    <td class="row-subtotal" style="font-weight: bold;">$${subtotal}</td>
                    <td><button type="button" class="btn-eliminar-fila" style="color:#dc3545; border:none; background:none; cursor:pointer;"><i class="fas fa-trash"></i></button></td>
                </tr>`;
        }

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

        function actualizarTotalGeneral() {
            let total = 0;
            $('.row-subtotal').each(function() {
                total += parseFloat($(this).text().replace('$', '')) || 0;
            });
            $('#gran-total-display').text('$' + total.toFixed(2));
        }

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

            if(!idCliente) return alert("Por favor, seleccione un cliente.");

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

            if(itemsCotizacion.length === 0) return alert("Agregue al menos un producto.");

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
                        alert("Cotización guardada exitosamente.");
                        location.reload(); 
                    } else {
                        alert("Error: " + resp.mensaje);
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
                    $('.recipe-table tbody').html(respuestaHTML);
                }
            });
        }
    });
</script>
</body>