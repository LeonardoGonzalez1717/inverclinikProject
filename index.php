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
            <input type="text" name="usuario" placeholder="Usuario" required />
            <input type="password" name="clave" placeholder="Contraseña" required />
            <button type="submit" id="confirm">Iniciar sesión</button>
            <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
        </form>
        <div id="resultado"></div>
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