<!-- Modal Cliente -->
<div id="modal-cliente" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; overflow-y: auto; padding: 20px;">
    <div style="background-color: #fff; padding: 30px; border-radius: 12px; max-width: 450px; width: 90%; position: relative; margin: auto;">
        <button id="cerrar-modal" style="position: absolute; top: 10px; right: 10px; background: none; border: none; font-size: 24px; cursor: pointer; color: #666;">&times;</button>
        
        <div id="vista-login">
            <h2 style="color: #005bbe; margin-bottom: 20px; text-align: center;">Acceso Clientes</h2>
            <form id="form-login-cliente">
                <input type="email" name="email" placeholder="Correo Electrónico" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px;" />
                <input type="password" name="password" placeholder="Contraseña" required style="width: 100%; padding: 12px; margin-bottom: 15px; border: 1px solid #ccc; border-radius: 6px;" />
                <button type="submit" style="background-color: #005bbe; color: #fff; border: none; padding: 12px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Entrar</button>
            </form>
            <p style="text-align: center; margin-top: 15px; font-size: 14px;">
                ¿Eres nuevo? <a href="javascript:void(0)" id="ir-a-registro" style="color: #005bbe; font-weight: bold; text-decoration: none;">Regístrate aquí</a>
            </p>
        </div>

        <div id="vista-registro" style="display: none;">
            <h2 style="color: #005bbe; margin-bottom: 20px; text-align: center;">Crear Cuenta</h2>
            <form id="form-registro-cliente">
                <input type="text" name="nombre" placeholder="Nombre / Razón Social *" required style="width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px;" />
                <div style="display: flex; gap: 5px; margin-bottom: 10px;">
                    <select name="tipo_doc" style="width: 30%; padding: 12px; border: 1px solid #ccc; border-radius: 6px;">
                        <option value="V">V</option><option value="J">J</option><option value="E">E</option>
                    </select>
                    <input type="text" name="nro_doc" placeholder="Documento" style="width: 70%; padding: 12px; border: 1px solid #ccc; border-radius: 6px;" />
                </div>

                <input type="email" name="email_reg" placeholder="Correo Electrónico *" required style="width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px;" />
                
                <input type="password" id="pass_reg" name="pass_reg" placeholder="Crear Contraseña *" required style="width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px;" />
                <input type="password" id="pass_reg_2" placeholder="Repetir Contraseña *" required style="width: 100%; padding: 12px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 6px;" />                
                <button type="submit" style="background-color: #005bbe; color: #fff; border: none; padding: 12px; font-weight: bold; border-radius: 6px; cursor: pointer; width: 100%;">Confirmar Registro</button>
            </form>
            <p style="text-align: center; margin-top: 15px; font-size: 14px;">
                ¿Ya tienes cuenta? <a href="javascript:void(0)" id="ir-a-login" style="color: #005bbe; font-weight: bold; text-decoration: none;">Volver al inicio</a>
            </p>
        </div>
        <div id="msg-cliente" style="margin-top: 10px; text-align: center;"></div>
    </div>
</div>

<script>
    // Lógica de intercambio de vistas
    document.getElementById('ir-a-registro').onclick = () => {
        document.getElementById('vista-login').style.display = 'none';
        document.getElementById('vista-registro').style.display = 'block';
    };
    document.getElementById('ir-a-login').onclick = () => {
        document.getElementById('vista-registro').style.display = 'none';
        document.getElementById('vista-login').style.display = 'block';
    };
    document.getElementById('cerrar-modal').onclick = () => {
        document.getElementById('modal-cliente').style.display = 'none';
    };

    $(document).ready(function() {
        $("#form-registro-cliente").on("submit", function(e) {
            e.preventDefault(); 
            
            const p1 = $('#pass_reg').val();
            const p2 = $('#pass_reg_2').val();
            const msgDiv = $('#msg-cliente');

            if (p1 !== p2) {
                msgDiv.html("<p style='color:red;'>Las contraseñas no coinciden.</p>");
                $('#pass_reg_2').css("border-color", "red");
                return;
            }

            var datos = $(this).serialize();
            $.ajax({
                url: "cliente/cliente_data.php",
                type: "POST",
                data: datos,
                dataType: "json",
                success: function(resp) {
                    if (resp.success) {
                        $("#msg-cliente").html("<p style='color:green;'>" + resp.message + "</p>");
                        setTimeout(() => {
                            $('#ir-a-login').click();
                            $('#form-registro-cliente')[0].reset();
                            msgDiv.html("");
                        }, 2000);
                    } else {
                        $("#msg-cliente").html("<p style='color:red;'>" + resp.message + "</p>");
                    }
                },
                error: function() {
                    $("#msg-cliente").html("<p style='color:red;'>Error de conexión con el servidor.</p>");
                }
            });
        });
    });


    $(document).on("submit", "#form-login-cliente", function(e) {
        e.preventDefault();
        $.ajax({
            url: "login_cliente_data.php", // <--- Solo para clientes
            type: "POST",
            data: $(this).serialize(),
            dataType: "json",
            success: function(resp) {
                $("#msg-cliente").html("<p style='color:green;'>" + resp.message + "</p>");
                if (resp.success) {
                    window.location.href = "dashboard/dashboard_cliente.php";
                } else {
                    $('#msg-cliente').html("<p style='color:red;'>" + resp.message + "</p>");
                }
            }
        });
    });
</script>