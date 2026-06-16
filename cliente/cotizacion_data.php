<?php
session_start();
include '../connection/connection.php';
require_once __DIR__ . '/../lib/Auditoria.php';

function asegurar_modalidad_pago_cotizaciones(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    $chk = $conn->query("SHOW COLUMNS FROM cotizaciones LIKE 'modalidad_pago'");
    if (!$chk || $chk->num_rows === 0) {
        $conn->query(
            "ALTER TABLE cotizaciones ADD COLUMN modalidad_pago VARCHAR(20) NOT NULL DEFAULT 'contado'
             COMMENT 'contado o financiada' AFTER codigo_presupuesto_origen"
        );
    }

    $chkPct = $conn->query("SHOW COLUMNS FROM cotizaciones LIKE 'porcentaje_pago_minimo'");
    if (!$chkPct || $chkPct->num_rows === 0) {
        $conn->query(
            'ALTER TABLE cotizaciones ADD COLUMN porcentaje_pago_minimo DECIMAL(5,2) DEFAULT NULL
             COMMENT \'Porcentaje mínimo del primer pago si es financiada\' AFTER modalidad_pago'
        );
    }
}

function etiqueta_modalidad_pago(?string $valor): string
{
    return ($valor === 'financiada') ? 'Financiada' : 'Contado';
}

asegurar_modalidad_pago_cotizaciones($conn);

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'listar_cotizaciones':
        // Consulta para traer los datos principales de la cotización
        $sql = "SELECT 
                    c.id_cotizacion, 
                    c.codigo_cotizacion, 
                    c.codigo_presupuesto_origen, 
                    c.modalidad_pago,
                    c.porcentaje_pago_minimo,
                    c.status,
                    c.fecha_registro as fecha,
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
        $i = 1;

        if ($res && mysqli_num_rows($res) > 0) {
            while ($row = mysqli_fetch_assoc($res)) {
                $totalFormateado = number_format($row['total'], 2);
                $st = (int) ($row['status'] ?? 0);

                $fechaFormateada = date('d/m/Y', strtotime($row['fecha']));
                $fechaLimpia = date('Y-m-d', strtotime($row['fecha']));
                
                $estadoStyle = match($st) {
                    2 => 'background-color: #198754; color: #ffffff; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block;', // Aprobada
                    1 => 'background-color: #0d6efd; color: #ffffff; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block;', // Enviada
                    default => 'background-color: #dc3545; color: #ffffff; font-weight: 700; padding: 4px 10px; border-radius: 6px; display: inline-block;' // Rechazada
                };

                $estTxt = match($st) {
                    2 => 'Aprobada',
                    1 => 'Enviada',
                    default => 'Rechazada'
                };

                
                $botonEditar = "";

                $datosJson = htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8');

                if ($st === 1) {
                    $botonEditar = "<button class='btn btn-sm btn-primary' onclick='verDetalles($datosJson)' title='Editar' style='background:#005bbe; border:none; color:white; padding:5px 10px; border-radius:3px; cursor:pointer; margin-left:5px;'>
                                        <i class='fas fa-pencil'></i>
                                    </button>";
                }
                
                $html .= "<tr data-codigo='" . htmlspecialchars($row['codigo_cotizacion']) . "' data-cliente='" . htmlspecialchars($row['cliente_nombre']) . "' data-estado='" . $estTxt . "' data-fecha='" . $fechaLimpia . "'>";;
                
                $html .= "<td>" . $i++ . "</td>";
                $html .= "<td nowrap><strong>" . htmlspecialchars($row['codigo_cotizacion']) . "</strong></td>";
                $html .= "<td>
                            <span style='font-weight: 600; color: #333;'>" . htmlspecialchars($row['cliente_nombre']) . "</span><br>
                            <small class='text-muted'>" . htmlspecialchars($row['email'] ?? 'Sin correo') . "</small>
                        </td>";
                $modTxt = etiqueta_modalidad_pago($row['modalidad_pago'] ?? 'contado');
                if (($row['modalidad_pago'] ?? 'contado') === 'financiada' && !empty($row['porcentaje_pago_minimo'])) {
                    $modTxt .= ' (' . number_format((float) $row['porcentaje_pago_minimo'], 0) . '% mín.)';
                }
                $html .= '<td>' . htmlspecialchars($modTxt) . '</td>';
                $html .= '<td nowrap>' . htmlspecialchars($fechaFormateada) . '</td>';
                $html .= "<td style='font-weight:bold; color:#005bbe;'>$" . $totalFormateado . "</td>";
                $html .= '<td><span style="' . $estadoStyle . '">' . htmlspecialchars($estTxt) . '</span></td>';
                $codigoEsc = htmlspecialchars($row['codigo_cotizacion'], ENT_QUOTES, 'UTF-8');
                $btnEliminar = '';
                if (in_array($st, [1, 3], true)) {
                    $btnEliminar = "<button type='button' class='btn btn-sm btn-danger btn-eliminar-cot'
                        data-id='" . (int) $row['id_cotizacion'] . "'
                        data-codigo='" . $codigoEsc . "'
                        title='Eliminar cotización'
                        style='margin-left:5px;'>
                        <i class='fas fa-trash'></i>
                    </button>";
                }

                $html .= "<td nowrap>
                            <a href='../formatos/ver_cotizacion.php?id=" . $row['id_cotizacion'] . "' target='_blank' class='btn btn-sm btn-info' title='Imprimir'>
                                <i class='fas fa-print'></i>
                            </a>
                            " . $btnEliminar . "
                            " . $botonEditar . "
                        </td>";
                $html .= '</tr>';
            }
        } else {
            $html = "<tr><td colspan='8' style='text-align:center;'>No hay cotizaciones disponibles.</td></tr>";
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

        $id_cotizacion_edit = (int) ($_POST['id_cotizacion'] ?? 0);
        $id_cliente = (int) ($_POST['id_cliente'] ?? 0);
        $codigo_presupuesto = $_POST['codigo_presupuesto'] ?? '';
        $modalidad_pago = $_POST['modalidad_pago'] ?? 'contado';
        $porcentaje_pago_minimo = isset($_POST['porcentaje_pago_minimo']) && $_POST['porcentaje_pago_minimo'] !== ''
            ? (float) str_replace(',', '.', (string) $_POST['porcentaje_pago_minimo'])
            : null;
        $items = json_decode($_POST['items'], true); 
        $total = $_POST['total_cotizacion'];

        if (!in_array($modalidad_pago, ['contado', 'financiada'], true)) {
            echo json_encode(['success' => false, 'mensaje' => 'Modalidad de pago inválida.']);
            break;
        }

        if ($modalidad_pago === 'financiada') {
            if ($porcentaje_pago_minimo === null) {
                $porcentaje_pago_minimo = 60.0;
            }
            if ($porcentaje_pago_minimo <= 0 || $porcentaje_pago_minimo > 100) {
                echo json_encode(['success' => false, 'mensaje' => 'El porcentaje mínimo de pago debe estar entre 1 y 100.']);
                break;
            }
            $porcentaje_pago_minimo = round($porcentaje_pago_minimo, 2);
        } else {
            $porcentaje_pago_minimo = null;
        }
        
        if (!is_array($items) || count($items) === 0) {
            echo json_encode(['success' => false, 'mensaje' => 'Debe incluir al menos un producto en la cotización.']);
            break;
        }

        if ($id_cliente <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'Cliente inválido.']);
            break;
        }

        $orig = !empty($codigo_presupuesto) ? $codigo_presupuesto : 'VENTA DIRECTA';
        $esEdicion = $id_cotizacion_edit > 0;
        $codigo_cotizacion = '';
        $id_cotizacion = 0;

        $conn->begin_transaction();

        try {
            if ($esEdicion) {
                $stmtExist = $conn->prepare(
                    'SELECT id_cotizacion, codigo_cotizacion, status FROM cotizaciones WHERE id_cotizacion = ? LIMIT 1'
                );
                $stmtExist->bind_param('i', $id_cotizacion_edit);
                $stmtExist->execute();
                $exist = $stmtExist->get_result()->fetch_assoc();
                $stmtExist->close();

                if (!$exist) {
                    throw new Exception('La cotización a editar no existe.');
                }
                if ((int) ($exist['status'] ?? 0) !== 1) {
                    throw new Exception('Solo se pueden editar cotizaciones en estado Enviada.');
                }

                $id_cotizacion = (int) $exist['id_cotizacion'];
                $codigo_cotizacion = (string) $exist['codigo_cotizacion'];

                $stmt = $conn->prepare(
                    'UPDATE cotizaciones
                     SET id_cliente = ?, codigo_presupuesto_origen = ?, modalidad_pago = ?,
                         porcentaje_pago_minimo = ?, total = ?
                     WHERE id_cotizacion = ?'
                );
                $stmt->bind_param(
                    'issddi',
                    $id_cliente,
                    $orig,
                    $modalidad_pago,
                    $porcentaje_pago_minimo,
                    $total,
                    $id_cotizacion
                );
                $stmt->execute();
                $stmt->close();

                $stmtDel = $conn->prepare('DELETE FROM cotizacion_detalles WHERE id_cotizacion = ?');
                $stmtDel->bind_param('i', $id_cotizacion);
                $stmtDel->execute();
                $stmtDel->close();
            } else {
                if (!empty($codigo_presupuesto)) {
                    $codigo_cotizacion = str_replace('PRE', 'COT', $codigo_presupuesto);
                } else {
                    $codigo_cotizacion = 'COT-MAN-' . date('His');
                }

                $stmt = $conn->prepare(
                    "INSERT INTO cotizaciones (id_cliente, codigo_cotizacion, codigo_presupuesto_origen, modalidad_pago, porcentaje_pago_minimo, total, status)
                     VALUES (?, ?, ?, ?, ?, ?, 1)"
                );
                $stmt->bind_param('isssdd', $id_cliente, $codigo_cotizacion, $orig, $modalidad_pago, $porcentaje_pago_minimo, $total);
                $stmt->execute();
                $id_cotizacion = (int) $stmt->insert_id;
                $stmt->close();
            }

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

            if (!$esEdicion && !empty($codigo_presupuesto)) {
                $stmt_upd = $conn->prepare('UPDATE presupuestos SET status = 2 WHERE codigo_presupuesto = ?');
                $stmt_upd->bind_param('s', $codigo_presupuesto);
                $stmt_upd->execute();
                $stmt_upd->close();
            }

            $conn->commit();
            Auditoria::registrar(
                $conn,
                ($esEdicion ? 'Cotización actualizada: ' : 'Cotización creada: ')
                    . $codigo_cotizacion . ' (id ' . (int) $id_cotizacion . '). Total: ' . $total . '.',
                'Cotización (cliente)'
            );
            echo json_encode([
                'success' => true,
                'mensaje' => $esEdicion
                    ? 'Cotización actualizada correctamente.'
                    : 'Cotización guardada correctamente.',
            ]);

        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'mensaje' => "Error: " . $e->getMessage()]);
        }
        break;

    case 'eliminar_cotizacion':
        restringirEscritura();

        $id_cotizacion = (int) ($_POST['id_cotizacion'] ?? 0);
        if ($id_cotizacion <= 0) {
            echo json_encode(['success' => false, 'mensaje' => 'ID de cotización inválido.']);
            break;
        }

        $stmt = $conn->prepare(
            'SELECT id_cotizacion, codigo_cotizacion, codigo_presupuesto_origen, status, comprobante_archivo
             FROM cotizaciones WHERE id_cotizacion = ? LIMIT 1'
        );
        $stmt->bind_param('i', $id_cotizacion);
        $stmt->execute();
        $cot = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cot) {
            echo json_encode(['success' => false, 'mensaje' => 'La cotización no existe.']);
            break;
        }

        $st = (int) ($cot['status'] ?? 0);
        if (!in_array($st, [1, 3], true)) {
            echo json_encode([
                'success' => false,
                'mensaje' => 'Solo se pueden eliminar cotizaciones en estado Enviada o Rechazada.',
            ]);
            break;
        }

        $stmtVenta = $conn->prepare('SELECT COUNT(*) AS n FROM ventas WHERE cotizacion_id = ?');
        $stmtVenta->bind_param('i', $id_cotizacion);
        $stmtVenta->execute();
        $nVentas = (int) ($stmtVenta->get_result()->fetch_assoc()['n'] ?? 0);
        $stmtVenta->close();

        if ($nVentas > 0) {
            echo json_encode([
                'success' => false,
                'mensaje' => 'No se puede eliminar: la cotización ya tiene una venta asociada.',
            ]);
            break;
        }

        $conn->begin_transaction();

        try {
            $archivo = trim((string) ($cot['comprobante_archivo'] ?? ''));
            if ($archivo !== '') {
                $rutaArchivo = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads'
                    . DIRECTORY_SEPARATOR . 'comprobantes_cotizaciones' . DIRECTORY_SEPARATOR . $archivo;
                if (is_file($rutaArchivo)) {
                    @unlink($rutaArchivo);
                }
            }

            $stmtDel = $conn->prepare('DELETE FROM cotizaciones WHERE id_cotizacion = ?');
            $stmtDel->bind_param('i', $id_cotizacion);
            $stmtDel->execute();
            $stmtDel->close();

            $origen = trim((string) ($cot['codigo_presupuesto_origen'] ?? ''));
            if ($origen !== '' && $origen !== 'VENTA DIRECTA') {
                $stmtCnt = $conn->prepare(
                    'SELECT COUNT(*) AS n FROM cotizaciones WHERE codigo_presupuesto_origen = ?'
                );
                $stmtCnt->bind_param('s', $origen);
                $stmtCnt->execute();
                $quedan = (int) ($stmtCnt->get_result()->fetch_assoc()['n'] ?? 0);
                $stmtCnt->close();

                if ($quedan === 0) {
                    $stmtRev = $conn->prepare('UPDATE presupuestos SET status = 0 WHERE codigo_presupuesto = ?');
                    $stmtRev->bind_param('s', $origen);
                    $stmtRev->execute();
                    $stmtRev->close();
                }
            }

            $conn->commit();
            Auditoria::registrar(
                $conn,
                'Cotización eliminada: ' . $cot['codigo_cotizacion'] . ' (id ' . $id_cotizacion . ').',
                'Cotización (cliente)'
            );
            echo json_encode(['success' => true, 'mensaje' => 'Cotización eliminada correctamente.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'mensaje' => 'Error: ' . $e->getMessage()]);
        }
        break;
}