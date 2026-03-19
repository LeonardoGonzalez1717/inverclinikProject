<?php require_once('../template/header.php'); ?>

<?php
$iduser = $_SESSION['iduser'];

$sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol 
        FROM users u 
        LEFT JOIN roles r ON r.id = u.role_id 
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
?>

<div class="main-content">
  <div class="container-wrapper">
    <div class="container-inner">
      <h1 class="main-title">Reporte de Ventas</h1>

      <form method="GET" action="ventas_view.php" target="_blank">

        <div class="mb-3">
          <label class="form-label">Cliente</label>
          <input type="text" name="cliente" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label">Fecha Inicio</label>
          <input type="date" name="fecha_inicio" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label">Fecha Fin</label>
          <input type="date" name="fecha_fin" class="form-control">
        </div>

        <div class="mb-3">
          <label class="form-label">Estado</label>
          <select name="estado" class="form-control">
            <option value=""></option>
            <option value="pendiente">Pendiente</option>
            <option value="entregado">Entregado</option>
            <option value="cancelado">Cancelado</option>
          </select>
        </div>

        <div class="text-center">
          <button type="submit" class="btn btn-primary">Generar Reporte</button>
        </div>

      </form>
    </div>
  </div>
</div>

<?php require_once('../template/footer.php'); ?>