<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$esCliente = isset($_SESSION['tipo'], $_SESSION['id_cliente'])
    && $_SESSION['tipo'] === 'cliente'
    && (int) $_SESSION['id_cliente'] > 0;
$idStaff = isset($_SESSION['iduser']) ? (int) $_SESSION['iduser'] : 0;

if (!$esCliente && $idStaff <= 0) {
    header('Location: ../index.php');
    exit;
}

require_once('../template/header.php');

$row = null;
if ($esCliente) {
    $idCliente = (int) $_SESSION['id_cliente'];
    $sql = "SELECT c.id, c.nombre, c.tipo_documento, c.numero_documento, c.telefono, c.email, c.direccion, c.role_id, r.nombre AS rol
            FROM clientes c
            LEFT JOIN roles r ON r.id = c.role_id
            WHERE c.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $idCliente);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
} else {
    $iduser = $idStaff;
    $sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $iduser);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
}

if (!$row) {
    echo '<body><div class="main-content"><p class="alert alert-danger">No se encontraron datos del perfil.</p></div>';
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    require_once '../template/footer.php';
    exit;
}
?>
<body>
  <div class="main-content">
    <div class="container-wrapper">
      <div class="container-inner">
        <h2 class="main-title">Mi Perfil</h2>

        <form id="form-perfil">
          <?php if ($esCliente): ?>
          <div class="mb-3">
            <label class="form-label">Nombre completo</label>
            <input type="text" name="nombre" class="form-control" required maxlength="100"
                  value="<?= htmlspecialchars($row['nombre'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Correo electrónico</label>
            <input type="email" name="correo" class="form-control" required maxlength="100"
                  value="<?= htmlspecialchars($row['email'] ?? '') ?>">
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Tipo de documento</label>
              <input type="text" name="tipo_documento" class="form-control" maxlength="20"
                    value="<?= htmlspecialchars($row['tipo_documento'] ?? '') ?>"
                    placeholder="Ej. DNI, RIF">
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Número de documento</label>
              <input type="text" name="numero_documento" class="form-control" maxlength="30"
                    value="<?= htmlspecialchars($row['numero_documento'] ?? '') ?>">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Teléfono</label>
            <input type="text" name="telefono" class="form-control" maxlength="20"
                  value="<?= htmlspecialchars($row['telefono'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Dirección</label>
            <textarea name="direccion" class="form-control" rows="2" maxlength="500"><?= htmlspecialchars($row['direccion'] ?? '') ?></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label">Rol</label>
            <input type="text" name="rol" class="form-control" readonly
                  value="<?= htmlspecialchars($row['rol'] ?? 'Cliente') ?>">
          </div>
          <?php else: ?>
          <div class="mb-3">
            <label class="form-label">Nombre de Usuario</label>
            <input type="text" name="username" class="form-control" required
                  value="<?= htmlspecialchars($row['username'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Correo Electrónico</label>
            <input type="email" name="correo" class="form-control" required
                  value="<?= htmlspecialchars($row['correo'] ?? '') ?>">
          </div>

          <div class="mb-3">
            <label class="form-label">Rol</label>
            <input type="text" name="rol" class="form-control" readonly
                  value="<?= htmlspecialchars($row['rol'] ?? '') ?>">
          </div>
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Contraseña</label>
            <input type="password" name="password" class="form-control"
                  placeholder="Dejar vacío si no deseas cambiarla" autocomplete="new-password">
          </div>

          <button type="submit" class="btn-editar">Guardar Cambios</button>
          <button type="reset" class="btn-eliminar">Cancelar</button>
        </form>

        <div id="resultadoPerfil" class="mt-3"></div>
      </div>
    </div>
  </div>
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
<?php
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
require_once '../template/footer.php';
?>
