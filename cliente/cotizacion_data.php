<?php
session_start();
include '../connection/connection.php';
require_once __DIR__ . '/../lib/Auditoria.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'listar_cotizaciones':
        // Consulta para traer los datos principales de la cotización
        $sql = "SELECT 
                    c.id_cotizacion, 
                    c.codigo_cotizacion, 
                    c.codigo_presupuesto_origen, 
                    c.status,
                    cl.nombre AS cliente_nombre, 
                    cl.telefono, 
                    cl.email, 
                    cl.direccion, 
                    c.total,
                    c.id_cliente
                FROM cotizaciones c
                INNER JOIN clientes cl ON c.id_cliente = cl.id
                ORDER BY c.id_cotizacion DESC";

        $res = mysqli_query($conn, $sql);
        $html = "";

        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $totalFormateado = number_format($row['total'], 2);
                $st = (int) ($row['status'] ?? 0);
                switch ($st) {
                    case 1:
                        $estTxt = 'Enviada';
                        $estCls = 'badge-warning';
                        break;
                    case 2:
                        $estTxt = 'Aprobada';
                        $estCls = 'badge-success';
                        break;
                    case 3:
                        $estTxt = 'Rechazada';
                        $estCls = 'badge-danger';
                        break;
                    default:
                        $estTxt = 'Estado ' . $st;
                        $estCls = 'badge-secondary';
                }

                $datosJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                
                $html .= "<tr>";
                $html .= "<td>" . $row['id_cotizacion'] . "</td>";
                $html .= "<td>
                            <strong>" . htmlspecialchars($row['cliente_nombre']) . "</strong><br>
                            <small class='text-muted'>" . htmlspecialchars($row['codigo_cotizacion']) . "</small>
                        </td>";
                $html .= "<td>" . htmlspecialchars($row['email'] ?? '') . "</td>";
                $html .= "<td style='font-weight:bold; color:#005bbe;'>$" . $totalFormateado . "</td>";
                $html .= '<td><span class="badge ' . $estCls . '">' . htmlspecialchars($estTxt) . '</span></td>';
                $html .= "<td nowrap>
                            <a href='../formatos/ver_cotizacion.php?id=" . $row['id_cotizacion'] . "' target='_blank' class='btn btn-sm btn-info'>
                                <i class='fas fa-print'></i> Imprimir Cotización
                            </a>
                            <button class='btn btn-sm btn-primary' onclick='verDetalles($datosJson)' title='Ver Detalles' style='background:#005bbe; border:none; color:white; padding:5px 10px; border-radius:3px; cursor:pointer; margin-left:5px;'>
                                Editar
                            </button>
                        </td>";
                $html .= '</tr>';
            }
        } else {
            $html = "<tr><td colspan='6' style='text-align:center;'>No hay cotizaciones disponibles.</td></tr>";
        }

        echo $html;
        break;

    case 'buscar_presupuestos_cliente':
        $id_cliente = $_GET['id_cliente'] ?? 0;
        
        $sql = "SELECT codigo_presupuesto, DATE_FORMAT(fecha_creacion, '%d/%m/%Y') as fecha, total 
                FROM presupuestos 
                WHERE id_cliente = ?  and status = 0
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
        
        $sql = "SELECT 
                    pd.id_producto as id_receta, 
                    pd.cantidad, 
                    pd.precio_unitario, 
                    pd.subtotal, 
                    pr.nombre,
                    rt.nombre_rango as talla_nombre,
                    r.rango_tallas_id as id_talla
                FROM presupuesto_detalles pd
                INNER JOIN presupuestos p ON p.id_presupuesto = pd.id_presupuesto
                INNER JOIN recetas r ON pd.id_producto = r.id
                INNER JOIN productos pr ON r.producto_id = pr.id
                INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
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

    case 'listar_productos_manual':
        $sql = "SELECT 
                    r.id, 
                    pr.nombre, 
                    r.precio_detal as precio_venta, 
                    r.precio_mayor, 
                    r.rango_tallas_id as id_talla, 
                    rt.nombre_rango as talla_nombre 
                FROM recetas r
                INNER JOIN productos pr ON r.producto_id = pr.id
                INNER JOIN rangos_tallas rt ON r.rango_tallas_id = rt.id
                ORDER BY pr.nombre ASC";
                
        $res = mysqli_query($conn, $sql);
        $productos = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $productos[] = [
                'id'           => $row['id'],
                'nombre'       => $row['nombre'],
                'precio'       => $row['precio_venta'],
                'mayor'       => $row['precio_mayor'],
                'id_talla'     => $row['id_talla'],
                'talla_nombre' => $row['talla_nombre']
            ];
        }
        echo json_encode($productos);
        break;
    // Agrega esto dentro del switch ($action)

    case 'obtener_detalle_cotizacion':
        $id_cotizacion = $_GET['id_cotizacion'] ?? 0;
        
        $sql = "SELECT 
                    cd.id_receta, 
                    cd.cantidad, 
                    cd.precio_unitario, 
                    cd.subtotal, 
                    cd.id_personalizacion,
                    cd.notas,
                    pr.nombre as nombre_producto,
                    rt.nombre_rango as talla_nombre,
                    cd.id_talla,
                    c.codigo_presupuesto_origen as origen_raw
                FROM cotizacion_detalles cd
                INNER JOIN cotizaciones c ON cd.id_cotizacion = c.id_cotizacion
                INNER JOIN recetas r ON cd.id_receta = r.id
                INNER JOIN productos pr ON r.producto_id = pr.id
                INNER JOIN rangos_tallas rt ON cd.id_talla = rt.id
                WHERE cd.id_cotizacion = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $id_cotizacion);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $items = [];
        while ($row = $result->fetch_assoc()) {
            // Determinamos el origen para el badge visual
            $row['origen'] = ($row['origen_raw'] == 'VENTA DIRECTA') ? 'manual' : 'presupuesto';
            $items[] = $row;
        }
        echo json_encode($items);
        break;

    case 'guardar_cotizacion':
        restringirEscritura();

        $id_cliente = $_POST['id_cliente'];
        $codigo_presupuesto = $_POST['codigo_presupuesto'] ?? '';
        $items = json_decode($_POST['items'], true); 
        $total = $_POST['total_cotizacion'];
        
        if (!empty($codigo_presupuesto)) {
            $codigo_cotizacion = str_replace("PRE", "COT", $codigo_presupuesto);
        } else {
            $codigo_cotizacion = "COT-MAN-" . date('His');
        }

        $conn->begin_transaction();

        try {
            $stmt = $conn->prepare("INSERT INTO cotizaciones (id_cliente, codigo_cotizacion, codigo_presupuesto_origen, total, status) VALUES (?, ?, ?, ?, 1)");
            $orig = !empty($codigo_presupuesto) ? $codigo_presupuesto : 'VENTA DIRECTA';
            $stmt->bind_param("issd", $id_cliente, $codigo_cotizacion, $orig, $total);
            $stmt->execute();
            $id_cotizacion = $stmt->insert_id;
            $stmt->close();

            $stmt_det_cot = $conn->prepare("INSERT INTO cotizacion_detalles 
                (id_cotizacion, id_receta, id_talla, id_personalizacion, cantidad, notas, precio_unitario, subtotal) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            foreach ($items as $item) {
                $id_receta = $item['id_receta'];
                $id_talla  = $item['id_talla'];
                $cantidad  = $item['cantidad'];
                $perso     = !empty($item['id_personalizacion']) ? $item['id_personalizacion'] : null;
                $notas     = $item['notas'] ?? '';
                $precio    = $item['precio_unitario'];
                $subtotal  = $item['subtotal'];

                $stmt_det_cot->bind_param(
                    "iiiidsdd",
                    $id_cotizacion,
                    $id_receta,
                    $id_talla,
                    $perso,
                    $cantidad,
                    $notas,
                    $precio,
                    $subtotal
                );
                $stmt_det_cot->execute();
            }
            $stmt_det_cot->close();

            if (!empty($codigo_presupuesto)) {
                $stmt_upd = $conn->prepare("UPDATE presupuestos SET status = 2 WHERE codigo_presupuesto = ?");
                $stmt_upd->bind_param("s", $codigo_presupuesto);
                $stmt_upd->execute();
                $stmt_upd->close();
            }

            $conn->commit();
            Auditoria::registrar(
                $conn,
                'Cotización creada: ' . $codigo_cotizacion . ' (id ' . (int) $id_cotizacion . '). Total: ' . $total . '.',
                'Cotización (cliente)'
            );
            echo json_encode([
                'success' => true,
                'mensaje' => 'Cotización guardada correctamente.',
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'mensaje' => "Error: " . $e->getMessage()]);
        }
        break;
}