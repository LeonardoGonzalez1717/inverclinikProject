<?php
$hay_admin = false;
if (file_exists(__DIR__ . '/connection/connection.php')) {
    include __DIR__ . '/connection/connection.php';
    $chk = $conn->query("SELECT 1 FROM users WHERE role_id = 1 LIMIT 1");
    if ($chk && $chk->num_rows > 0) {
        $hay_admin = true;
    }
    if (isset($conn)) $conn->close();
}
?>
<!DOCTYPE html>
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

        <form id="formi" autocomplete="off">
            <input type="text" name="usuario" placeholder="Usuario o Correo" required autocomplete="off" />
            <input type="password" name="clave" placeholder="Contraseña" required autocomplete="off" />
            <button type="submit" id="confirm">Iniciar sesión</button>
            <button type="button" id="btn-olvidaste" class="btn-forgot">¿Olvidaste tu contraseña?</button>
        </form>
        <div id="resultado"></div>
        
        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
            <button type="button" id="btn-registrarse" style="background-color: #005bbe; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Registrarse</button>
            <?php if (!$hay_admin): ?>
            <div style="margin-top: 12px;">
                <button type="button" id="btn-abrir-crear-admin" style="background-color: #28a745; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Crear usuario administrador</button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de Registro -->
    <div id="modal-registro" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; padding: 20px;">
        <div style="background-color: #fff; padding: 30px; border-radius: 12px; max-width: 500px; width: 90%; position: relative; margin: 20px auto;">
            <button id="cerrar-modal" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            <h2 style="color: #005bbe; margin-bottom: 20px;">Registrarse como Cliente</h2>
            <form id="form-registro" autocomplete="off">
                <input type="text" name="nombre" placeholder="Nombre Completo *" required autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <select name="tipo_documento" autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;">
                    <option value="">Tipo de Documento</option>
                    <option value="V">V - Venezolano</option>
                    <option value="E">E - Extranjero</option>
                    <option value="J">J - Jurídico</option>
                    <option value="G">G - Gobierno</option>
                </select>
                
                <input type="text" name="numero_documento" placeholder="Número de Documento" autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <input type="text" name="telefono" placeholder="Teléfono" autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <input type="email" name="email" placeholder="Correo Electrónico *" required autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <textarea name="direccion" placeholder="Dirección" rows="3" autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; resize: vertical;"></textarea>
                
                <input type="password" name="password" placeholder="Contraseña *" required autocomplete="new-password" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px;" />
                
                <button type="submit" id="btn-confirmar-registro" style="background-color: #005bbe; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%; margin-top: 10px;">Registrarse</button>
            </form>
            <div id="resultadoRegistro" style="margin-top: 15px;"></div>
        </div>
    </div>

    <!-- Modal Crear usuario administrador -->
    <div id="modal-crear-admin" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; padding: 20px;">
        <div style="background-color: #fff; padding: 30px; border-radius: 12px; max-width: 420px; width: 90%; position: relative; margin: 20px auto;">
            <button type="button" id="cerrar-modal-crear-admin" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            <h2 style="color: #005bbe; margin-bottom: 20px;">Crear usuario administrador</h2>
            <form id="form-crear-admin" autocomplete="off">
                <input type="text" name="username" id="admin-username" placeholder="Usuario *" required autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; box-sizing: border-box;" />
                <input type="email" name="correo" id="admin-correo" placeholder="Correo electrónico *" required autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; box-sizing: border-box;" />
                <input type="password" name="password" id="admin-password" placeholder="Contraseña * (mín. 6 caracteres)" required minlength="6" autocomplete="new-password" style="width: 100%; padding: 12px; margin-bottom: 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; box-sizing: border-box;" />
                <button type="submit" id="btn-crear-admin" style="background-color: #28a745; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Crear administrador</button>
            </form>
            <div id="msg-crear-admin" style="margin-top: 10px; font-size: 13px; display: none;"></div>
        </div>
    </div>

    <!-- Modal Recuperar contraseña (contraseña temporal por correo) -->
    <div id="modal-olvidaste" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; padding: 20px;">
        <div style="background-color: #fff; padding: 30px; border-radius: 12px; max-width: 420px; width: 90%; position: relative; margin: 20px auto;">
            <button id="cerrar-modal-olvidaste" type="button" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
            <h2 style="color: #005bbe; margin-bottom: 10px;">Recuperar contraseña</h2>
            <p style="color: #666; font-size: 14px; margin-bottom: 20px;">Ingresa tu correo y te enviaremos una contraseña temporal para que puedas entrar al sistema.</p>
            <form id="form-olvidaste" autocomplete="off">
                <input type="email" name="email" placeholder="Correo electrónico" required autocomplete="off" style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px; font-size: 16px; box-sizing: border-box;" />
                <button type="submit" id="btn-enviar-temp" style="background-color: #005bbe; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Enviar contraseña temporal</button>
            </form>
            <div id="resultadoOlvidaste" style="margin-top: 15px;"></div>
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

    // Modal recuperar contraseña
    $("#btn-olvidaste").click(function() {
        $("#modal-olvidaste").css("display", "flex");
        $("#form-olvidaste")[0].reset();
        $("#resultadoOlvidaste").html("");
    });

    $("#cerrar-modal-olvidaste").click(function() {
        $("#modal-olvidaste").css("display", "none");
        $("#form-olvidaste")[0].reset();
        $("#resultadoOlvidaste").html("");
    });

    $("#modal-olvidaste").click(function(e) {
        if (e.target.id === "modal-olvidaste") {
            $(this).css("display", "none");
            $("#form-olvidaste")[0].reset();
            $("#resultadoOlvidaste").html("");
        }
    });

    $("#form-olvidaste").on("submit", function(e) {
        e.preventDefault();
        var email = $(this).find("[name=email]").val();
        $("#resultadoOlvidaste").html("<p style='color:#666;'>Enviando...</p>");
        $.ajax({
            url: "forgot_password_data.php",
            type: "POST",
            data: { email: email },
            dataType: "json",
            success: function(resp) {
                if (resp.success) {
                    $("#resultadoOlvidaste").html("<p style='color:green;'>" + resp.message + "</p>");
                    setTimeout(function() {
                        $("#modal-olvidaste").css("display", "none");
                        $("#form-olvidaste")[0].reset();
                        $("#resultadoOlvidaste").html("");
                    }, 4000);
                } else {
                    $("#resultadoOlvidaste").html("<p style='color:red;'>" + resp.message + "</p>");
                }
            },
            error: function() {
                $("#resultadoOlvidaste").html("<p style='color:red;'>Error de conexión con el servidor.</p>");
            }
        });
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

    $("#btn-abrir-crear-admin").on("click", function() {
        $("#modal-crear-admin").css("display", "flex");
        $("#form-crear-admin")[0].reset();
        $("#msg-crear-admin").hide();
    });

    $("#cerrar-modal-crear-admin").on("click", function() {
        $("#modal-crear-admin").css("display", "none");
        $("#form-crear-admin")[0].reset();
        $("#msg-crear-admin").hide();
    });

    $("#modal-crear-admin").on("click", function(e) {
        if (e.target.id === "modal-crear-admin") {
            $(this).css("display", "none");
            $("#form-crear-admin")[0].reset();
            $("#msg-crear-admin").hide();
        }
    });

    $("#form-crear-admin").on("submit", function(e) {
        e.preventDefault();
        var $btn = $("#btn-crear-admin");
        $btn.prop("disabled", true).text("Creando...");
        $("#msg-crear-admin").hide();
        $.ajax({
            url: "crear_admin_data.php",
            type: "POST",
            data: {
                action: "crear_admin",
                username: $("#admin-username").val().trim(),
                correo: $("#admin-correo").val().trim(),
                password: $("#admin-password").val()
            },
            dataType: "json",
            success: function(resp) {
                if (resp.success) {
                    $("#msg-crear-admin").css("color", "green").html(resp.message).show();
                    $("#form-crear-admin")[0].reset();
                    setTimeout(function() { location.reload(); }, 2500);
                } else {
                    $("#msg-crear-admin").css("color", "red").html(resp.message).show();
                    $btn.prop("disabled", false).text("Crear administrador");
                }
            },
            error: function() {
                $("#msg-crear-admin").css("color", "red").html("Error de conexión con el servidor.").show();
                $btn.prop("disabled", false).text("Crear administrador");
            }
        });
    });
</script>