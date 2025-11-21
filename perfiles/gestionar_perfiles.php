<?php
require_once('../template/header.php');
?>

<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
</head>
<body>
  <div class="main-content">
    <section class="gestion-usuarios">
      <h2>Gestión de usuarios</h2>
      <button id="btnNuevoUsuario" class="btn-agregar">Registrar nuevo usuario</button>

      <table class="tabla-usuarios">
        <thead>
          <tr>
            <th>ID</th>
            <th>Usuario</th>
            <th>Correo</th>
            <th>Rol</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody id="listaUsuarios">
          <!-- Se llena dinámicamente -->
        </tbody>
      </table>

      <div id="resultadoUsuarios"></div>
    </section>
  </div>
</body>
</html>

<script>
$(document).ready(function() {
  $.getJSON('usuarios_data.php', function(data) {
    let html = '';
    data.forEach(user => {
      html += `
        <tr>
          <td>${user.id}</td>
          <td>${user.username}</td>
          <td>${user.email}</td>
          <td>${user.rol}</td>
          <td>
            <button class="btn-editar" onclick="editarUsuario(${user.id})">Modificar</button>
            <button class="btn-eliminar" onclick="eliminarUsuario(${user.id})">Eliminar</button>
          </td>
        </tr>
      `;
    });
    $('#listaUsuarios').html(html);
  });

  $('#btnNuevoUsuario').click(function() {
    window.location.href = 'registrar_usuario.php';
  });
});

function editarUsuario(id) {
  window.location.href = `editar_usuario.php?id=${id}`;
}

function eliminarUsuario(id) {
  if (confirm('¿Eliminar este usuario?')) {
    $.post(`eliminar_usuario.php?id=${id}`, function(resp) {
      $('#resultadoUsuarios').text(resp.message);
      setTimeout(() => location.reload(), 1500);
    }, 'json');
  }
}
</script>