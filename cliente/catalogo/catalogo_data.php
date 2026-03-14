<?php
include '../../connection/connection.php';

if ($_POST['action'] === 'guardar_tarjeta') {
    $id_base = $_POST['id_producto_base'];

    $query = "UPDATE productos SET 
                activo = 1 
              WHERE id = '$id_base'";

    if (mysqli_query($conn, $query)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => mysqli_error($conn)]);
    }
}

if ($_POST['action'] === 'eliminar') {
    $id = $_POST['id'];
    // No borramos, solo ocultamos
    mysqli_query($conn, "UPDATE productos SET activo = 0 WHERE id = '$id'");
    echo json_encode(['success' => true]);
}
?>