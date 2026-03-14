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
                'id' => $row['codigo_presupuesto'], 
                'text' => $row['codigo_presupuesto'] . " - (" . $row['fecha'] . ") - $" . $row['total'] // Lo que el usuario lee
            ];
        }
        echo json_encode($output);
        break;

    case 'obtener_detalle_presupuesto':
        $codigo = $_GET['codigo'] ?? '';
        
        $sql = "SELECT pd.id_producto, pd.cantidad, pd.precio_unitario, pd.subtotal 
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

    case 'guardar_cotizacion':
        $id_cliente = $_POST['id_cliente'];
        $codigo_presupuesto = $_POST['codigo_presupuesto'];
        $items = $_POST['items']; 
        $total = $_POST['total_cotizacion'];
        
        $codigo_cotizacion = str_replace("PRE", "COT", $codigo_presupuesto);

        // 1. Insertar Cabecera de Cotización
        $stmt = $conn->prepare("INSERT INTO cotizaciones (id_cliente, codigo_cotizacion, codigo_presupuesto_origen, total, status) VALUES (?, ?, ?, ?, 1)");
        $status_inicial = 1;
        $stmt->bind_param("issd", $id_cliente, $codigo_cotizacion, $codigo_presupuesto, $total);
        
        if ($stmt->execute()) {
            $id_cotizacion = $stmt->insert_id;

            $stmt_det = $conn->prepare("INSERT INTO cotizacion_detalles (id_cotizacion, id_producto, cantidad, talla, personalizacion, notas, precio_unitario, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach ($items as $item) {
                $stmt_det->bind_param("iiisssdd", 
                    $id_cotizacion, 
                    $item['id'], 
                    $item['cantidad'], 
                    $item['talla'], 
                    $item['perso'], 
                    $item['notas'], 
                    $item['precio'], 
                    $item['subtotal']
                );
                $stmt_det->execute();
            }

            $stmt_upd = $conn->prepare("UPDATE presupuestos SET status = 1 WHERE codigo_presupuesto = ?");
            $stmt_upd->bind_param("s", $codigo_presupuesto);
            $stmt_upd->execute();

            echo json_encode(['success' => true, 'mensaje' => 'Cotización '.$codigo_cotizacion.' guardada con éxito']);
        } else {
            echo json_encode(['success' => false, 'mensaje' => $conn->error]);
        }
    break;
}