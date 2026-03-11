<?php require_once('../template/header.php'); ?>
<?php
$iduser = $_SESSION['iduser'];

$sql = "SELECT u.id, u.username, u.correo, u.role_id, u.createdAt, r.nombre AS rol FROM users u LEFT JOIN roles r ON r.id = u.role_id WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $iduser);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
?>

<div class="main-content">
  <div class="container-wrapper">
    <div class="container-inner">
      <h1 class="main-title">Reporte de Insumos</h1>

      <form method="GET" action="insumos_view.php" target="_blank">
        <div class="mb-3">
			<label for="nombre" class="form-label">Insumo</label>
			<input type="text" name="nombre" id="nombre" class="form-control">
        </div>

        <div class="mb-3">
          	<label class="form-label">Unidad de Medida</label>
			<select name="unidad_medida" id="unidad_medida" class="form-control">
				<option value="">-- Seleccione una unidad --</option>
				<option value="metro">Metro</option>
				<option value="unidad">Unidad</option>
				<option value="kilogramo">Kilogramo</option>
				<option value="litro">Litro</option>
				<option value="metro_cuadrado">Metro Cuadrado</option>
				<option value="carrete">Carrete</option>
				<option value="rollo">Rollo</option>
				<option value="pieza">Pieza</option>
			</select>
        </div>

        <div class="mb-3">
			<label for="estado" class="form-label">Estado</label>
			<select name="estado" id="estado" class="form-control">
				<option value=""></option>
				<option value="1">Activo</option>
				<option value="2">Inactivo</option>
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