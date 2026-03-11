<html lang="es">
<head>
  <meta charset="UTF-8" />
  <title>INVERCLINIK - Inicio de sesión</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <script src="assets/js/jquery-3.7.1.min.js"></script>
  <link rel="stylesheet" href="css/login.css" />
</head>
<?php include 'cliente/modales_cliente.php'; ?>
<body>
    <div class="login-container">
        <div class="login-logo">
            <img src="assets/img/inverclinik_3.png" alt="INVERCLINIK" />
        </div>

        <h2>Bienvenido a INVERCLINIK</h2>

        <form id="formi">
            <input type="text" name="usuario" placeholder="Usuario o Correo" required />
            <input type="password" name="clave" placeholder="Contraseña" required />
            <div id="resultado"></div>
            <button type="submit" id="confirm">Iniciar sesión</button>
            <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
        </form>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee; display: flex; gap: 10px;">
            <a href="cliente/catalogo/catalogo.php" style="flex: 1; text-decoration: none;">
                <button type="button" style="background-color: #005bbe; color: #fff; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">
                    Catálogo
                </button>
            </a>
            
            <button type="button" id="btn-abrir-registro" style="background-color: #005bbe; color: #fff; border: none; padding: 12px 20px; font-weight: bold; border-radius: 6px; cursor: pointer; flex: 1;">
                Quiero ser Cliente
            </button>
        </div>
    </div>

    
</body>
</html>
<script>
    $("#btn-abrir-registro").click(function() {
        $("#modal-cliente").css("display", "flex");
    });

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
                        window.location.href = "dashboard/dashboard.php?iduser=" + resp.id;
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
</script>