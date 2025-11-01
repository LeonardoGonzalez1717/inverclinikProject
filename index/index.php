<?php require_once('../template/header.php');?>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <link rel="stylesheet" href="login.css" />
</head>
<body>
  <div class="login-container">
    <div class="login-box">
      <h1>INVERCLINIK</h1>
      <form id="formi">
        <label for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" required />

        <label for="clave">Contraseña</label>
        <input type="password" id="clave" name="clave" required />

        <button type="submit" id="confirm" class="btn-login">Entrar</button>
        <a href="#" class="forgot-link">¿Olvidaste tu contraseña?</a>
      </form>
      <div id="resultado"></div>
    </div>
  </div>
</body>
</html>
<script>
    $("#confirm").click(function(event){
        event.preventDefault();
        var datos = $("#formi").serialize();
        $.ajax({
            url: "index_data.php",
            type: "POST",              
            data: datos,
            dataType: "json",
            success: function(resp) {
                if(resp.success){
                    window.location.href = "../dashboard/dashboard.php?iduser=" + resp.id;
                    $("#resultado").html("<p style='color:green;'>"+resp.message+"</p>");
                } else {
                    $("#resultado").html("<p style='color:red;'>"+resp.message+"</p>");
                }
                setTimeout(() => {
                    $("#resultado").html("");
                }, 2000);

            }
        });
    });
    $("#cancel").click(function(){
        $("#formi")[0].reset();
        $("#resultado").html("");
    });
</script>