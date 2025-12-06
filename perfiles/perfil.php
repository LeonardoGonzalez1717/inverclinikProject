<?php require_once('../template/header.php'); ?>
<?php
$iduser = $_SESSION['iduser'];

$sql = "SELECT id, username, correo, login, rol, createdAt FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil</title>
</head>
<body>
  <div class="main-content">
    <div class="container-wrapper">
      <div class="container-inner">
        <h2 class="main-title">Mi Perfil</h2>

        <form id="form-perfil">
          <div class="mb-3">
            <label class="form-label">Nombre de Usuario</label>
            <input type="text" name="username" class="form-control" required
                  value="<?= htmlspecialchars($row['username']) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Login</label>
            <input type="text" name="login" class="form-control" required
                  value="<?= htmlspecialchars($row['login']) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Correo Electrónico</label>
            <input type="email" name="correo" class="form-control" required
                  value="<?= htmlspecialchars($row['correo']) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Rol</label>
            <input type="text" name="rol" class="form-control" readonly
                  value="<?= htmlspecialchars($row['rol']) ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control"
                  placeholder="Dejar vacío si no deseas cambiarla">
          </div>

          <button type="submit" class="btn-editar">Guardar Cambios</button>
          <button type="reset" class="btn-eliminar">Cancelar</button>
        </form>

        <div id="resultadoPerfil" class="mt-3"></div>
      </div>
    </div>
  </div>
</body>
</html>
<script>
  $('#form-perfil').on('submit', function(e) {
    e.preventDefault();
    $.ajax({
      url: 'perfil_data.php',
      type: 'POST',
      data: $(this).serialize(),
      dataType: 'json',
      success: function(resp) {
        if (resp.success) {
          $('#resultadoPerfil').html('<div class="alert alert-success">'+resp.message+'</div>');
        } else {
          $('#resultadoPerfil').html('<div class="alert alert-danger">'+resp.message+'</div>');
        }
      },
      error: function(xhr) {
        console.log("Respuesta cruda:", xhr.responseText);
        $('#resultadoPerfil').html('<div class="alert alert-danger">Error de conexión</div>');
      }
    });
  });
</script>
