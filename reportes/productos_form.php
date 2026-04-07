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

<h1 class="main-title">Reporte de Productos</h1>

<form method="GET" action="productos_view.php" target="_blank">

<div class="mb-3">
<label class="form-label">Nombre del Producto</label>
<input type="text" name="nombre" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Categoría</label>
<input type="text" name="categoria" class="form-control">
</div>

<div class="mb-3">
<label class="form-label">Género</label>
<select name="genero" class="form-control">

<option value="">-- Seleccione un género --</option>
<option value="Masculino">Masculino</option>
<option value="Femenino">Femenino</option>
<option value="Unisex">Unisex</option>

</select>
</div>

<div class="text-center">
<button type="submit" class="btn btn-primary">
Generar Reporte
</button>
</div>

</form>

</div>
</div>
</div>

<?php require_once('../template/footer.php'); ?>