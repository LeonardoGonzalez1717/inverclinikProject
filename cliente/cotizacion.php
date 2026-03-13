<?php require_once('../template/header.php'); ?>
<?php include '../connection/connection.php'; ?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Cotización | Inverclinik</title>
</head>
<body>
  <div class="main-content">
    <div class="container-wrapper">
      <div class="container-inner">
        <h2 class="main-title"> Cotización</h2>

        <div id="form-cotizacion">
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
            <h3 style="font-size: 1.1rem; margin-bottom: 15px; color: #555;">Detalles del Presupuesto</h3>
            <div class="table-responsive">
                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #eee; text-align: left;">
                            <th>Producto</th>
                            <th>Cant.</th>
                            <th width="120">Talla</th>
                            <th width="150">Personalización</th>
                            <th>Notas Técnicas</th>
                            <th width="120">Precio Unit.</th>
                        </tr>
                    </thead>
                    <tbody id="tabla-cotizador-body">
                        </tbody>
                </table>
            </div>

            <div class="mt-4" style="text-align: right;">
                <button type="button" class="btn-editar" id="btn-generar-cot">
                    <i class="fas fa-save"></i> Crear Cotización Formal
                </button>
            </div>
          </div>
        </div>

        <div id="resultadoCot" class="mt-3"></div>
      </div>
    </div>
  </div>

  <script src="../assets/js/jquery-3.7.1.min.js"></script>
  <script src="../assets/js/select2.min.js"></script>

  <script>
    $(document).ready(function() {
        $('#select-cliente, #select-presupuesto').select2();

        $('#select-cliente').on('change', function() {
            let idCliente = $(this).val();
            if (!idCliente) return;

            $('#select-presupuesto').prop('disabled', false).empty().append('<option>Cargando...</option>');

            $.ajax({
                url: 'cotizacion_data.php',
                type: 'GET',
                data: { 
                    action: 'buscar_presupuestos_cliente', 
                    id_cliente: idCliente 
                },
                dataType: 'json',
                success: function(resp) {
                    let options = '<option value="">Seleccione presupuesto...</option>';
                    resp.forEach(p => {
                        options += `<option value="${p.id}">${p.text}</option>`;
                    });
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
                data: { 
                    action: 'obtener_detalle_presupuesto', 
                    codigo: codigo 
                },
                dataType: 'json',
                success: function(resp) {
                    let html = "";
                    resp.forEach(item => {
                        html += `
                        <tr>
                            <td>${item.nombre_producto}</td>
                            <td>${item.cantidad}</td>
                            <td>
                                <select class="form-control talla-sel">
                                    <option>S</option><option selected>M</option><option>L</option><option>XL</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-control perso-sel">
                                    <option value="Ninguna">Ninguna</option>
                                    <option value="Bordado">Bordado</option>
                                    <option value="DTF">DTF</option>
                                </select>
                            </td>
                            <td><input type="text" class="form-control nota-input"></td>
                            <td><input type="number" class="form-control precio-input" value="${item.precio_unitario}"></td>
                        </tr>`;
                    });
                    $('#tabla-cotizador-body').html(html);
                    $('#contenedor-items').fadeIn();
                }
            });
        });
    });
  </script>
</body>
</html>