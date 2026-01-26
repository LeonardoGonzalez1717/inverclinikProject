<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>INVERCLINIK - Inicio de sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="css/login.css" />
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="assets/img/inverclinik_3.png" alt="INVERCLINIK" />
        </div>

        <h2>Bienvenido a INVERCLINIK</h2>

        <form id="formi">
            <input type="text" name="usuario" placeholder="Usuario o Correo" required />
            <input type="password" name="clave" placeholder="Contraseña" required />
            <button type="submit" id="confirm">Iniciar sesión</button>
            <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
        </form>
        <div id="resultado"></div>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <button type="button" id="btn-registrarse" style="background-color: #005bbe; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Registrarse</button>
        </div>
    </div>

    <!-- Modal de Registro -->
    <div id="modal-registro" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; padding: 20px;">
        <div style="background-color: #fff; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; position: relative; margin: 20px auto;">
            <button id="cerrar-modal" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            <h2 style="color: #005bbe; margin-bottom: 20px;">Registrarse como Cliente</h2>
            <form id="form-registro">
                <input type="text" name="nombre" placeholder="Nombre Completo *" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <select name="tipo_documento" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;">
                    <option value="">Tipo de Documento</option>
                    <option value="V">V - Venezolano</option>
                    <option value="E">E - Extranjero</option>
                    <option value="J">J - Jurídico</option>
                    <option value="G">G - Gobierno</option>
                </select>
                
                <input type="text" name="numero_documento" placeholder="Número de Documento" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <input type="text" name="telefono" placeholder="Teléfono" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <input type="email" name="email" placeholder="Correo Electrónico *" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <textarea name="direccion" placeholder="Dirección" rows="3" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; resize: vertical;"></textarea>
                
                <input type="password" name="password" placeholder="Contraseña *" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <button type="submit" id="btn-confirmar-registro" style="background-color: #005bbe; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 10px;">Registrarse</button>
            </form>
            <div id="resultadoRegistro" style="margin-top: 15px;"></div>
        </div>
    </div>
</body>
</html>
<script src="assets/js/jquery-3.7.1.min.js"></script>
<script>
    $("#confirm").click(function (event) {
  event.preventDefault(); // evita el envío tradicional

  var datos = $("#formi").serialize();

  $.ajax({
    url: "index_data.php",
    type: "POST",
    data: datos,
    dataType: "json",
    success: function (resp) {
      if (resp.success) {
        $("#resultado").html("<p style='color:green;'>" + resp.message + "</p>");
        setTimeout(() => {
          if (resp.tipo === 'cliente') {
            window.location.href = "index_cliente_data.php?idcliente=" + resp.id;
          } else {
            window.location.href = "dashboard/dashboard.php?iduser=" + resp.id;
          }
        }, 1000);
      } else {
        $("#resultado").html("<p style='color:red;'>" + resp.message + "</p>");
        setTimeout(() => {
          $("#resultado").html("");
        }, 3000);
      }
    },
    error: function () {
      $("#resultado").html("<p style='color:red;'>Error de conexión con el servidor.</p>");
    }
  });
});

    // Funcionalidad del modal de registro
    $("#btn-registrarse").click(function() {
        $("#modal-registro").css("display", "flex");
    });

    $("#cerrar-modal").click(function() {
        $("#modal-registro").css("display", "none");
        $("#form-registro")[0].reset();
        $("#resultadoRegistro").html("");
    });

    // Cerrar modal al hacer clic fuera
    $("#modal-registro").click(function(e) {
        if (e.target.id === "modal-registro") {
            $(this).css("display", "none");
            $("#form-registro")[0].reset();
            $("#resultadoRegistro").html("");
        }
    });

    // Envío del formulario de registro
    $("#form-registro").on("submit", function(e) {
        e.preventDefault();
        var datos = $(this).serialize();

        $.ajax({
            url: "registro_data.php",
            type: "POST",
            data: datos,
            dataType: "json",
            success: function(resp) {
                if (resp.success) {
                    $("#resultadoRegistro").html("<p style='color:green;'>" + resp.message + "</p>");
                    setTimeout(() => {
                        $("#modal-registro").css("display", "none");
                        $("#form-registro")[0].reset();
                        $("#resultadoRegistro").html("");
                    }, 2000);
                } else {
                    $("#resultadoRegistro").html("<p style='color:red;'>" + resp.message + "</p>");
                    setTimeout(() => {
                        $("#resultadoRegistro").html("");
                    }, 3000);
                }
            },
            error: function() {
                $("#resultadoRegistro").html("<p style='color:red;'>Error de conexión con el servidor.</p>");
            }
        });
    });
</script>