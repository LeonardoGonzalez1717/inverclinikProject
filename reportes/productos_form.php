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

$lista_productos = [];
$r = $conn->query('SELECT id, nombre FROM productos ORDER BY nombre ASC');
if ($r) {
    while ($p = $r->fetch_assoc()) {
        $lista_productos[] = $p;
    }
}
$categorias = [];
$chkCat = $conn->query("SHOW TABLES LIKE 'categorias'");
if ($chkCat && $chkCat->num_rows > 0) {
    $r = $conn->query('SELECT id, nombre FROM categorias ORDER BY nombre ASC');
    if ($r) {
        while ($c = $r->fetch_assoc()) {
            $categorias[] = $c;
        }
    }
}
if (empty($categorias)) {
    $r = $conn->query('SELECT DISTINCT categoria AS nombre FROM productos WHERE categoria IS NOT NULL AND TRIM(categoria) != \'\' ORDER BY categoria ASC');
    if ($r) {
        while ($c = $r->fetch_assoc()) {
            $categorias[] = ['id' => 0, 'nombre' => $c['nombre']];
        }
    }
}
?>

<div class="main-content">
<div class="container-wrapper">
<div class="container-inner">

<h1 class="main-title">Reporte de Productos</h1>

<form method="GET" action="productos_view.php" target="_blank">

<div class="mb-3">
<label class="form-label">Producto</label>
<select name="producto_id" class="form-control">
<option value="">Todos los productos</option>
<?php foreach ($lista_productos as $pr): ?>
<option value="<?php echo (int) $pr['id']; ?>"><?php echo htmlspecialchars($pr['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
</div>

<div class="mb-3">
<label class="form-label">Categoría</label>
<select name="categoria" class="form-control">
<option value="">Todas las categorías</option>
<?php foreach ($categorias as $cat): ?>
<option value="<?php echo htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($cat['nombre'], ENT_QUOTES, 'UTF-8'); ?></option>
<?php endforeach; ?>
</select>
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