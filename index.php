<?php require_once "template/header.php" ?>
<body>
    <div class="background">
        <div class="login">
            <div class="login-form">
                <div class="login-input">
                   <form action="" id="formi" method="post">
                    <h1>Iniciar Sesión</h1>
                    <div class="button-radius">
                        <div class="input">
                            <input type="text" name="usuario" placeholder="Usuario" required>
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <div class="input">
                            <input type="password" name="clave" placeholder="Contraseña" required>
                            <i class="fa-solid fa-lock"></i>
                        </div>
                    </div>
                    <div class="button-options">
                        <button id="confirm" class="confirm" type="submit">ENVIAR</button>
                        <button id="cancel" class="cancel" type="button">CANCELAR</button>
                    </div>
                   </form>
                   <div id="resultado"></div>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    $("#confirm").click(function(event){
        event.preventDefault();
        var datos = $("#formi").serialize();
        $.ajax({
            url: "index_data.php",
            type: "POST",              
            data: datos,
            success: function(resp) {
                if(resp.success){
                    window.location.href = "inicio.php?iduser=" + resp.id;
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