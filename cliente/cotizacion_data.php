<?php
session_start();
include '../connection/connection.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'buscar_presupuestos_cliente':
        $id_cliente = $_GET['id_cliente'] ?? 0;
        
        $sql = "SELECT codigo_presupuesto, DATE_FORMAT(fecha_creacion, '%d/%m/%Y') as fecha, total 
                FROM presupuestos 
                WHERE id_cliente = ? 
                ORDER BY id_presupuesto DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_cliente);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $output = [];
        while ($row = $result->fetch_assoc()) {
            $output[] = [
                'id' => $row['codigo_presupuesto'], // Lo que Select2 usará como value
                'text' => $row['codigo_presupuesto'] . " - (" . $row['fecha'] . ") - $" . $row['total'] // Lo que el usuario lee
            ];
        }
        echo json_encode($output);
        break;

    case 'obtener_detalle_presupuesto':
        $codigo = $_GET['codigo'] ?? '';
        
        $sql = "SELECT pd.nombre_producto, pd.cantidad, pd.precio_unitario, pd.subtotal 
                FROM presupuesto_detalles pd
                INNER JOIN presupuestos p ON p.id_presupuesto = pd.id_presupuesto
                WHERE p.codigo_presupuesto = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $codigo);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        echo json_encode($items);
        break;

    default:
        echo json_encode(['error' => 'Acción no válida']);
        break;
}