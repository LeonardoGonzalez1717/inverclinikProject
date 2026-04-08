<?php
session_start();
require_once "../connection/connection.php"; 
header('Content-Type: application/json');

if (!isset($_SESSION['id_cliente'])) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no iniciada']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_cliente = $_SESSION['id_cliente'];
    $carrito = $_POST['carrito'];
    $total = $_POST['total'];

    $conn->begin_transaction();

    try {
        $query_ref = mysqli_query($conn, "SELECT MAX(id_presupuesto) as ultimo FROM presupuestos");
        $row = mysqli_fetch_assoc($query_ref);
        $nuevo_id = ($row['ultimo'] ?? 0) + 1;
        $correlativo = "PRE-" . str_pad($nuevo_id, 4, "0", STR_PAD_LEFT);

        $stmt = $conn->prepare("INSERT INTO presupuestos (id_cliente, codigo_presupuesto, total, fecha_creacion) VALUES (?, ?, ?, NOW())");
        $stmt->bind_param("isd", $id_cliente, $correlativo, $total);
        $stmt->execute();
        $id_presupuesto = $stmt->insert_id;

        // Añadimos 'tipo_precio' si quieres saber si se vendió a detal o mayor
        $stmt_det = $conn->prepare("INSERT INTO presupuesto_detalles (id_presupuesto, id_producto, cantidad, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($carrito as $item) {
            $id_prod = $item['recetaId']; 
            $cant    = $item['cantidad'];
            $precio  = $item['precioUnit'];
            $sub     = $item['subtotal'];

            $stmt_det->bind_param("iiidd", $id_presupuesto, $id_prod, $cant, $precio, $sub);
            $stmt_det->execute();
        }

        $conn->commit();
        echo json_encode(['status' => 'success', 'correlativo' => $correlativo]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Error al procesar: ' . $e->getMessage()]);
    }
}
?>