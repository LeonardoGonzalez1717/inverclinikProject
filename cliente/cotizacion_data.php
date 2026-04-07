<?php
session_start();
include '../connection/connection.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'listar_cotizaciones':
        // Consulta para traer los datos principales de la cotización
        $sql = "SELECT 
                    c.id_cotizacion, 
                    c.codigo_cotizacion, 
                    c.codigo_presupuesto_origen, 
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

                $datosJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');
                
                $html .= "<tr>";
                $html .= "<td>" . $row['id_cotizacion'] . "</td>";
                $html .= "<td>
                            <strong>" . htmlspecialchars($row['cliente_nombre']) . "</strong><br>
                            <small class='text-muted'>" . $row['codigo_cotizacion'] . "</small>
                        </td>";
                $html .= "<td>" . $row['email'] . "</td>";
                $html .= "<td style='font-weight:bold; color:#005bbe;'>$" . $totalFormateado . "</td>";
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

    case 'guardar_cotizacion':
        $id_cliente = $_POST['id_cliente'];
        $codigo_presupuesto = $_POST['codigo_presupuesto'] ?? '';
        $items = json_decode($_POST['items'], true); 
        $total = $_POST['total_cotizacion'];
        
        if (!empty($codigo_presupuesto)) {
            $codigo_cotizacion = str_replace("PRE", "COT", $codigo_presupuesto);
        } else {
            $codigo_cotizacion = "COT-MAN-" . date('His');
        }

        $codigo_venta = "FAC-" . date('ymd') . "-" . rand(100, 999);

        $conn->begin_transaction(); 

        try {
            $stmt = $conn->prepare("INSERT INTO cotizaciones (id_cliente, codigo_cotizacion, codigo_presupuesto_origen, total, status) VALUES (?, ?, ?, ?, 1)");
            $orig = !empty($codigo_presupuesto) ? $codigo_presupuesto : 'VENTA DIRECTA';
            $stmt->bind_param("issd", $id_cliente, $codigo_cotizacion, $orig, $total);
            $stmt->execute();
            $id_cotizacion = $stmt->insert_id;

            $stmt_vta = $conn->prepare("INSERT INTO ventas (cotizacion_id, cliente_id, fecha, numero_factura, total, estado) VALUES (?, ?, CURDATE(), ?, ?, 'pendiente')");
            $stmt_vta->bind_param("iisd", $id_cotizacion, $id_cliente, $codigo_venta, $total);
            $stmt_vta->execute();
            $id_venta = $stmt_vta->insert_id;

            // 3. Insertar Detalles
            $stmt_det_cot = $conn->prepare("INSERT INTO cotizacion_detalles 
                (id_cotizacion, id_receta, id_talla, id_personalizacion, cantidad, notas, precio_unitario, subtotal) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

            // En detalle_venta NO incluyas el subtotal porque es GENERATED
            $stmt_det_vta = $conn->prepare("INSERT INTO detalle_venta (venta_id, producto_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?)");
            
            foreach ($items as $item) {
                $id_receta = $item['id_receta']; 
                $id_talla  = $item['id_talla'];
                $cantidad  = $item['cantidad'];
                $perso     = !empty($item['id_personalizacion']) ? $item['id_personalizacion'] : NULL; 
                $notas     = $item['notas'];
                $precio    = $item['precio_unitario']; 
                $subtotal  = $item['subtotal'];

                $stmt_det_cot->bind_param("iiiidsdd", 
                    $id_cotizacion, $id_receta, $id_talla, $perso, $cantidad, $notas, $precio, $subtotal
                );
                $stmt_det_cot->execute();

                $stmt_det_vta->bind_param("iidd", 
                    $id_venta, $id_receta, $cantidad, $precio
                );
                $stmt_det_vta->execute();
            }

            if (!empty($codigo_presupuesto)) {
                $stmt_upd = $conn->prepare("UPDATE presupuestos SET status = 2 WHERE codigo_presupuesto = ?");
                $stmt_upd->bind_param("s", $codigo_presupuesto);
                $stmt_upd->execute();
            }

            $conn->commit();
            echo json_encode(['success' => true, 'mensaje' => 'Venta registrada como pendiente correctamente.']);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'mensaje' => "Error: " . $e->getMessage()]);
        }
        break;
}