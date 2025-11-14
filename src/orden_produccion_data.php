<?php
require_once "../connection/connection.php";

$action = $_POST['action'] ?? '';

try {
    if ($action === 'listar_html') {

        $sql = "
            SELECT 
                op.id AS orden_id,
                p.nombre AS producto_nombre,
                op.cantidad_a_producir,
                op.fecha_inicio,
                op.fecha_fin,
                op.estado,
                op.observaciones, rp.id
            FROM ordenes_produccion op
            INNER JOIN recetas_productos rp ON op.receta_producto_id = rp.id
            INNER JOIN productos p ON rp.producto_id = p.id
            ORDER BY op.creado_en DESC
        ";

        $result = $conn->query($sql);
        $ordenes = [];
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $ordenes[] = $row;
            }
        }
        $i = 0;
        if (!empty($ordenes)) {
            foreach ($ordenes as $o) {
                $i++;
                $badgeClass = match($o['estado']) {
                    'finalizado' => 'success',
                    'en_proceso' => 'warning',
                    'pendiente' => 'info',
                    default => 'danger'
                };
                echo '<tr>';
                echo '<td>' . htmlspecialchars($i) . '</td>';
                echo '<td>' . htmlspecialchars($o['producto_nombre']) . '</td>';
                echo '<td>' . htmlspecialchars($o['cantidad_a_producir']) . '</td>';
                echo '<td>' . ($o['fecha_inicio'] ? date('d/m/Y', strtotime($o['fecha_inicio'])) : '—') . '</td>';
                echo '<td>' . ($o['fecha_fin'] ? date('d/m/Y', strtotime($o['fecha_fin'])) : '—') . '</td>';
                echo '<td><span class="badge bg-' . $badgeClass . '">' . htmlspecialchars($o['estado']) . '</span></td>';
                echo '<td><button class="btn btn-sm btn-primary" onclick="editarOrden(' . htmlspecialchars(json_encode($o), ENT_QUOTES, 'UTF-8') . ')">Editar</button></td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="7" class="text-center">No hay órdenes de producción</td></tr>';
        }
        $conn->close();
        exit; // Terminamos aquí
    }

    // Para el resto de acciones, usamos JSON
    header('Content-Type: application/json');

    switch ($action) {
        case 'crear':
            // ... tu código de crear (ya corregido) ...
            $receta_id = $_POST['receta_id'] ?? null;
            $cantidad = $_POST['cantidad_a_producir'] ?? 0;
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $observaciones = $_POST['observaciones'] ?? '';

            if (!$receta_id || $cantidad <= 0) {
                throw new Exception("Receta y cantidad válida son obligatorios");
            }

            $stmt = $conn->prepare("
                INSERT INTO ordenes_produccion (receta_producto_id, cantidad_a_producir, fecha_inicio, fecha_fin, observaciones, estado)
                VALUES (?, ?, ?, ?, ?, 'pendiente')
            ");

            if($_POST['orden_id'] == ""){
                $msg = "orden creada";
            }else{
                $msg = "orden editada";
            }
            $stmt->bind_param("idsss", $receta_id, $cantidad, $fecha_inicio, $fecha_fin, $observaciones);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => $msg, 'id' => $conn->insert_id]);
            break;

        case 'editar':
            $id = $_POST['id'] ?? null;
            if (!$id) throw new Exception("ID de orden requerido");

            $receta_id = $_POST['receta_id'] ?? null;
            $cantidad = $_POST['cantidad_a_producir'] ?? 0;
            $fecha_inicio = !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : null;
            $fecha_fin = !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : null;
            $estado = $_POST['estado'] ?? 'pendiente';
            $observaciones = $_POST['observaciones'] ?? '';

            $stmt = $conn->prepare("
                UPDATE ordenes_produccion 
                SET receta_producto_id = ?, 
                    cantidad_a_producir = ?, 
                    fecha_inicio = ?, 
                    fecha_fin = ?, 
                    estado = ?, 
                    observaciones = ?
                WHERE id = ?
            ");
            $stmt->bind_param("idssssi", $receta_id, $cantidad, $fecha_inicio, $fecha_fin, $estado, $observaciones, $id);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Orden actualizada']);
            break;

        default:
            throw new Exception("Acción no válida");
    }

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        echo '<tr><td colspan="7" class="text-center text-danger">Error al cargar órdenes</td></tr>';
    }
}

$conn->close();
?>