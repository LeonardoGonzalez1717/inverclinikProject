<?php
require_once "../connection/connection.php";

$action = $_REQUEST['action'] ?? '';

switch($action) {
    case 'listar_mis_presupuestos':
        $id_cliente = $_GET['id_cliente']; 

        $sql = "SELECT id_presupuesto, codigo_presupuesto, total, status, DATE_FORMAT(fecha_creacion, '%d/%m/%Y') as fecha 
                FROM presupuestos 
                WHERE id_cliente = $id_cliente 
                ORDER BY id_presupuesto DESC";
        
        $res = mysqli_query($conn, $sql);
        $html = "";

        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $statusClass = match($row['status']) {
                    'Aprobado' => 'badge-success',
                    'Pendiente' => 'badge-warning',
                    'Rechazado' => 'badge-danger',
                    default => 'badge-secondary'
                };

                $html .= "<tr>
                            <td>#{$row['codigo_presupuesto']}</td>
                            <td>{$row['fecha']}</td>
                            <td><span class='badge {$statusClass}'>{$row['status']}</span></td>
                            <td><strong>$" . number_format($row['total'], 2) . "</strong></td>
                            <td>
                                <button class='btn btn-sm btn-primary' onclick='verDetallePresupuesto({$row['id_presupuesto']}, \"{$row['codigo_presupuesto']}\")'>
                                    Ver
                                </button>
                            </td>
                          </tr>";
            }
        } else {
            $html = "<tr><td colspan='5' class='text-center'>No tienes presupuestos aún.</td></tr>";
        }
        echo $html;
        break;

    case 'ver_detalle_cliente':
        $id_presupuesto = $_GET['id'];
        $sql = "SELECT d.*, p.nombre as producto, t.nombre_rango as talla 
                FROM presupuesto_detalles d
                JOIN recetas r ON d.id_producto = r.id
                join productos p ON r.producto_id = p.id
                JOIN rangos_tallas t ON r.rango_tallas_id = t.id
                WHERE d.id_presupuesto = $id_presupuesto";
        
        $res = mysqli_query($conn, $sql);
        $items = [];
        while($row = mysqli_fetch_assoc($res)) {
            $items[] = $row;
        }
        echo json_encode($items);
        break;
}