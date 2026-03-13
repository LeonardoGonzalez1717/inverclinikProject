<?php
session_start();
include '../connection/connection.php'; // Ajusta la ruta si es necesario
header('Content-Type: application/json');

// Verificar si el usuario está logueado
if (!isset($_SESSION['id_cliente'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cliente = $_SESSION['id_cliente'];
    $carrito = $_POST['carrito'];
    $total = $_POST['total'];

    // Obtener el correlativo
    $query_ref = mysqli_query($conn, "SELECT MAX(id_presupuesto) as ultimo FROM presupuestos");
    $row = mysqli_fetch_assoc($query_ref);
    $nuevo_id = ($row['ultimo'] ?? 0) + 1;
    $correlativo = "PRE-" . str_pad($nuevo_id, 4, "0", STR_PAD_LEFT);

    // Insertar Cabecera
    $stmt = $conn->prepare("INSERT INTO presupuestos (id_cliente, codigo_presupuesto, total) VALUES (?, ?, ?)");
    $stmt->bind_param("isd", $id_cliente, $correlativo, $total);
    
    if ($stmt->execute()) {
        $id_nuevo_presupuesto = $stmt->insert_id;

        // Preparar el detalle una sola vez
        $stmt_det = $conn->prepare("INSERT INTO presupuesto_detalles (id_presupuesto, nombre_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($carrito as $item) {
            $nombre = $item['nombre'];
            $cant = (int)$item['cantidad'];
            $precio = (double)$item['precioUnit'];
            $sub = (double)$item['subtotal'];

            // "isidd" significa: integer, string, integer, double, double
            $stmt_det->bind_param("isidd", $id_nuevo_presupuesto, $nombre, $cant, $precio, $sub);
            $stmt_det->execute();
        }

        echo json_encode(['status' => 'success', 'correlativo' => $correlativo]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $conn->error]);
    }
}
?>