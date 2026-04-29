<?php
session_start();
require_once '../connection/connection.php';

/** Asegura columnas de comprobante de pago en instalaciones antiguas. */
function asegurar_columnas_comprobante_cotizaciones(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;
    $chk = $conn->query("SHOW COLUMNS FROM cotizaciones LIKE 'comprobante_referencia'");
    if ($chk && $chk->num_rows > 0) {
        return;
    }
    $conn->query(
        'ALTER TABLE cotizaciones
        ADD COLUMN comprobante_referencia VARCHAR(120) DEFAULT NULL,
        ADD COLUMN comprobante_archivo VARCHAR(255) DEFAULT NULL,
        ADD COLUMN comprobante_fecha DATETIME DEFAULT NULL'
    );
}

asegurar_columnas_comprobante_cotizaciones($conn);

/** Crea tabla formas_pago y columna cotizaciones.forma_pago_id si faltan. */
function asegurar_formas_y_forma_pago_cotizaciones(mysqli $conn): void
{
    static $hecho = false;
    if ($hecho) {
        return;
    }
    $hecho = true;

    $conn->query(
        'CREATE TABLE IF NOT EXISTS formas_pago (
            id INT AUTO_INCREMENT PRIMARY KEY,
            nombre VARCHAR(60) NOT NULL UNIQUE,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci'
    );
    $base = ['pago movil', 'transferencia bancaria', 'efectivo', 'divisa'];
    foreach ($base as $nombre) {
        $st = $conn->prepare(
            'INSERT INTO formas_pago (nombre, activo)
             SELECT ?, 1
             WHERE NOT EXISTS (
                SELECT 1 FROM formas_pago WHERE LOWER(TRIM(nombre)) = LOWER(TRIM(?))
             )'
        );
        if ($st) {
            $st->bind_param('ss', $nombre, $nombre);
            $st->execute();
            $st->close();
        }
    }

    $chkCol = $conn->query("SHOW COLUMNS FROM cotizaciones LIKE 'forma_pago_id'");
    if ($chkCol && $chkCol->num_rows === 0) {
        @ $conn->query(
            'ALTER TABLE cotizaciones ADD COLUMN forma_pago_id INT NULL DEFAULT NULL
             COMMENT \'Forma de pago declarada al cargar comprobante\' AFTER comprobante_fecha'
        );
    }
    $chkFk = $conn->query(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cotizaciones'
           AND COLUMN_NAME = 'forma_pago_id' AND REFERENCED_TABLE_NAME = 'formas_pago'
         LIMIT 1"
    );
    if ((!$chkFk || $chkFk->num_rows === 0)) {
        @ $conn->query(
            'ALTER TABLE cotizaciones
             ADD CONSTRAINT fk_cotizaciones_forma_pago
             FOREIGN KEY (forma_pago_id) REFERENCES formas_pago(id)
             ON UPDATE CASCADE ON DELETE SET NULL'
        );
    }
}

asegurar_formas_y_forma_pago_cotizaciones($conn);

$action = $_REQUEST['action'] ?? '';
$id_cliente = isset($_SESSION['id_cliente']) ? (int) $_SESSION['id_cliente'] : 0;

$acciones_cliente = [
    'listar_mis_cotizaciones',
    'ver_detalle_cotizacion_cliente',
    'datos_comprobante_cotizacion',
    'guardar_comprobante_cotizacion',
    'listar_formas_pago',
];

if ($id_cliente <= 0 && in_array($action, $acciones_cliente, true)) {
    if (in_array($action, ['ver_detalle_cotizacion_cliente', 'datos_comprobante_cotizacion', 'guardar_comprobante_cotizacion'], true)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Sesión no válida']);
    } else {
        echo '<tr><td colspan="7" class="text-center">Inicia sesión para ver tus cotizaciones.</td></tr>';
    }
    exit;
}

function etiqueta_estado_cotizacion(int $st): array
{
    switch ($st) {
        case 1:
            return ['Enviada', 'badge-warning'];
        case 2:
            return ['Aprobada', 'badge-success'];
        case 3:
            return ['Rechazada', 'badge-danger'];
        default:
            return ['Estado ' . $st, 'badge-secondary'];
    }
}

switch ($action) {
    case 'listar_mis_cotizaciones':
        $sql = "SELECT c.id_cotizacion, c.codigo_cotizacion, c.codigo_presupuesto_origen, c.total, c.status,
                DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') AS fecha,
                c.comprobante_referencia, c.comprobante_archivo, c.comprobante_fecha
                FROM cotizaciones c
                WHERE c.id_cliente = ?
                ORDER BY c.id_cotizacion DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_cliente);
        $stmt->execute();
        $res = $stmt->get_result();
        $html = '';

        if ($res && $res->num_rows > 0) {
            while ($row = $res->fetch_assoc()) {
                $st = (int) $row['status'];
                [$estTxt, $estCls] = etiqueta_estado_cotizacion($st);
                $orig = trim((string) ($row['codigo_presupuesto_origen'] ?? ''));
                if ($orig === '' || strtoupper($orig) === 'VENTA DIRECTA') {
                    $origHtml = '<span class="text-muted">—</span>';
                } else {
                    $origHtml = htmlspecialchars($orig);
                }
                $cod = $row['codigo_cotizacion'] ?? '';
                $codEsc = htmlspecialchars($cod, ENT_QUOTES, 'UTF-8');
                $jsonCod = json_encode($cod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE);
                $idCot = (int) $row['id_cotizacion'];
                $refComp = trim((string) ($row['comprobante_referencia'] ?? ''));
                $archComp = trim((string) ($row['comprobante_archivo'] ?? ''));
                $tieneComp = ($refComp !== '' || $archComp !== '');
                $mostrarBtnCargarComprobante = ($refComp === '' || $archComp === '');
                if ($tieneComp) {
                    $compBits = [];
                    if ($refComp !== '') {
                        $compBits[] = '<span class="text-muted small">Ref.:</span> ' . htmlspecialchars(mb_strlen($refComp) > 24 ? mb_substr($refComp, 0, 22) . '…' : $refComp);
                    }
                    if ($archComp !== '') {
                        $compBits[] = '<span class="badge badge-light border">Archivo</span>';
                    }
                    $compHtml = '<div class="small">' . implode('<br>', $compBits) . '</div>';
                } else {
                    $compHtml = '<span class="text-muted">—</span>';
                }

                $html .= '<tr>'
                    . '<td><strong>' . $codEsc . '</strong></td>'
                    . '<td>' . htmlspecialchars($row['fecha']) . '</td>'
                    . '<td>' . $origHtml . '</td>'
                    . '<td><span class="badge ' . $estCls . '">' . htmlspecialchars($estTxt) . '</span></td>'
                    . '<td><strong>$' . number_format((float) $row['total'], 2, '.', ',') . '</strong></td>'
                    . '<td class="small">' . $compHtml . '</td>'
                    . '<td style="white-space:nowrap">'
                    . '<button type="button" class="btn btn-sm btn-primary" onclick=\'verDetalleCotizacion(' . $idCot . ', ' . $jsonCod . ')\'>Ver</button> '
                    . '<a href="../formatos/ver_cotizacion.php?id=' . $idCot . '" target="_blank" class="btn btn-sm btn-info">Imprimir</a>'
                    . (!$compHtml
                        ? ' <button type="button" class="btn btn-sm btn-outline-secondary" onclick=\'abrirModalComprobante(' . $idCot . ', ' . $jsonCod . ')\'>Cargar comprobante</button>'
                        : '')
                    . '</td>'
                    . '</tr>';
            }
        } else {
            $html = '<tr><td colspan="7" class="text-center">Aún no tienes cotizaciones registradas.</td></tr>';
        }
        $stmt->close();
        echo $html;
        break;

    case 'ver_detalle_cotizacion_cliente':
        header('Content-Type: application/json; charset=utf-8');
        $id_cotizacion = (int) ($_GET['id'] ?? 0);
        if ($id_cotizacion <= 0) {
            echo json_encode(['error' => 'Cotización no válida']);
            exit;
        }

        $stmtCab = $conn->prepare(
            'SELECT codigo_cotizacion, codigo_presupuesto_origen, total, status,
             DATE_FORMAT(fecha_registro, \'%d/%m/%Y\') AS fecha,
             comprobante_referencia, comprobante_archivo,
             DATE_FORMAT(comprobante_fecha, \'%d/%m/%Y %H:%i\') AS comprobante_fecha_fmt
             FROM cotizaciones WHERE id_cotizacion = ? AND id_cliente = ?'
        );
        $stmtCab->bind_param('ii', $id_cotizacion, $id_cliente);
        $stmtCab->execute();
        $rc = $stmtCab->get_result();
        if (!$rc || $rc->num_rows === 0) {
            $stmtCab->close();
            echo json_encode(['error' => 'Cotización no encontrada']);
            exit;
        }
        $cabRow = $rc->fetch_assoc();
        $stmtCab->close();

        $st = (int) ($cabRow['status'] ?? 0);
        [$estTxt, $estCls] = etiqueta_estado_cotizacion($st);
        $orig = trim((string) ($cabRow['codigo_presupuesto_origen'] ?? ''));
        if ($orig === '' || strtoupper($orig) === 'VENTA DIRECTA') {
            $orig = '';
        }

        $cabecera = [
            'codigo_cotizacion' => (string) ($cabRow['codigo_cotizacion'] ?? ''),
            'fecha' => (string) ($cabRow['fecha'] ?? ''),
            'presupuesto_origen' => $orig,
            'total' => (float) ($cabRow['total'] ?? 0),
            'estado_texto' => $estTxt,
            'estado_class' => $estCls,
            'comprobante_referencia' => trim((string) ($cabRow['comprobante_referencia'] ?? '')),
            'comprobante_archivo' => trim((string) ($cabRow['comprobante_archivo'] ?? '')),
            'comprobante_fecha' => (string) ($cabRow['comprobante_fecha_fmt'] ?? ''),
        ];

        $sql = "SELECT cd.cantidad, cd.precio_unitario, cd.subtotal,
                p.nombre AS producto, rt.nombre_rango AS talla
                FROM cotizacion_detalles cd
                INNER JOIN recetas r ON r.id = cd.id_receta
                INNER JOIN productos p ON p.id = r.producto_id
                LEFT JOIN rangos_tallas rt ON rt.id = cd.id_talla
                WHERE cd.id_cotizacion = ?
                ORDER BY cd.id ASC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $id_cotizacion);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
        $stmt->close();
        echo json_encode(['cabecera' => $cabecera, 'items' => $items]);
        break;

    case 'listar_formas_pago':
        header('Content-Type: application/json; charset=utf-8');
        $rows = [];
        $rf = $conn->query('SELECT id, nombre FROM formas_pago WHERE activo = 1 ORDER BY nombre ASC');
        if ($rf) {
            while ($fr = $rf->fetch_assoc()) {
                $nom = (string) ($fr['nombre'] ?? '');
                $rows[] = [
                    'id' => (int) $fr['id'],
                    'nombre' => $nom,
                    'nombre_norm' => strtolower(trim($nom)),
                ];
            }
        }
        echo json_encode(['success' => true, 'formas' => $rows]);
        break;

    case 'datos_comprobante_cotizacion':
        header('Content-Type: application/json; charset=utf-8');
        $id_cot = (int) ($_GET['id'] ?? 0);
        if ($id_cot <= 0) {
            echo json_encode(['error' => 'Cotización no válida']);
            exit;
        }
        $stmt = $conn->prepare(
            'SELECT codigo_cotizacion, comprobante_referencia, comprobante_archivo,
             forma_pago_id,
             DATE_FORMAT(comprobante_fecha, \'%d/%m/%Y %H:%i\') AS comprobante_fecha
             FROM cotizaciones WHERE id_cotizacion = ? AND id_cliente = ?'
        );
        $stmt->bind_param('ii', $id_cot, $id_cliente);
        $stmt->execute();
        $r = $stmt->get_result();
        if (!$r || $r->num_rows === 0) {
            $stmt->close();
            echo json_encode(['error' => 'Cotización no encontrada']);
            exit;
        }
        $row = $r->fetch_assoc();
        $stmt->close();
        $fp = isset($row['forma_pago_id']) ? (int) $row['forma_pago_id'] : 0;
        echo json_encode([
            'id_cotizacion' => $id_cot,
            'codigo_cotizacion' => (string) ($row['codigo_cotizacion'] ?? ''),
            'comprobante_referencia' => (string) ($row['comprobante_referencia'] ?? ''),
            'comprobante_archivo' => (string) ($row['comprobante_archivo'] ?? ''),
            'comprobante_fecha' => (string) ($row['comprobante_fecha'] ?? ''),
            'forma_pago_id' => $fp > 0 ? $fp : null,
            'tiene_archivo' => trim((string) ($row['comprobante_archivo'] ?? '')) !== '',
        ]);
        break;

    case 'guardar_comprobante_cotizacion':
        header('Content-Type: application/json; charset=utf-8');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['error' => 'Método no permitido']);
            exit;
        }
        $id_cot = (int) ($_POST['id_cotizacion'] ?? 0);
        $refNueva = trim((string) ($_POST['comprobante_referencia'] ?? ''));
        $forma_id = (int) ($_POST['forma_pago_id'] ?? 0);

        if ($id_cot <= 0) {
            echo json_encode(['error' => 'Cotización no válida']);
            exit;
        }

        if ($forma_id <= 0) {
            echo json_encode(['error' => 'Seleccione la forma de pago.']);
            exit;
        }
        $vf = $conn->prepare('SELECT id FROM formas_pago WHERE id = ? AND activo = 1 LIMIT 1');
        $vf->bind_param('i', $forma_id);
        $vf->execute();
        $rf = $vf->get_result();
        if (!$rf || $rf->num_rows === 0) {
            $vf->close();
            echo json_encode(['error' => 'Forma de pago no válida.']);
            exit;
        }
        $vf->close();

        $stmt0 = $conn->prepare(
            'SELECT comprobante_archivo FROM cotizaciones WHERE id_cotizacion = ? AND id_cliente = ?'
        );
        $stmt0->bind_param('ii', $id_cot, $id_cliente);
        $stmt0->execute();
        $r0 = $stmt0->get_result();
        if (!$r0 || $r0->num_rows === 0) {
            $stmt0->close();
            echo json_encode(['error' => 'Cotización no encontrada']);
            exit;
        }
        $existente = $r0->fetch_assoc();
        $stmt0->close();
        $archivoAnterior = trim((string) ($existente['comprobante_archivo'] ?? ''));

        $subioArchivo = false;
        $nombreArchivoGuardar = $archivoAnterior;

        $uploadBase = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'comprobantes_cotizaciones' . DIRECTORY_SEPARATOR;
        if (!is_dir($uploadBase)) {
            mkdir($uploadBase, 0755, true);
        }

        $fileField = $_FILES['archivo_comprobante'] ?? null;
        if (is_array($fileField) && (!empty($fileField['name']) || (($fileField['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE))) {
            $err = (int) ($fileField['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) {
                // sin archivo nuevo
            } elseif ($err !== UPLOAD_ERR_OK) {
                echo json_encode(['error' => 'Error al subir el archivo (código ' . $err . ').']);
                exit;
            } else {
                $file = $fileField;
                $maxBytes = 8 * 1024 * 1024;
                if ((int) $file['size'] > $maxBytes) {
                    echo json_encode(['error' => 'El archivo supera el máximo permitido (8 MB).']);
                    exit;
                }
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $permitidas = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];
                if (!in_array($ext, $permitidas, true)) {
                    echo json_encode(['error' => 'Formato no permitido. Use PDF, JPG, PNG o WEBP.']);
                    exit;
                }
                $mimeOk = [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/webp',
                ];
                $detectado = null;
                if (function_exists('mime_content_type')) {
                    $detectado = @mime_content_type($file['tmp_name']);
                }
                if ($detectado !== null && $detectado !== '' && !in_array($detectado, $mimeOk, true)) {
                    if ($detectado !== 'application/octet-stream') {
                        echo json_encode(['error' => 'El tipo de archivo no es válido.']);
                        exit;
                    }
                }
                $nuevoNombre = 'cot_' . $id_cot . '_' . time() . '_' . uniqid('', true) . '.' . $ext;
                $destino = $uploadBase . $nuevoNombre;
                if (!move_uploaded_file($file['tmp_name'], $destino)) {
                    echo json_encode(['error' => 'No se pudo guardar el archivo.']);
                    exit;
                }
                if ($archivoAnterior !== '' && is_file($uploadBase . $archivoAnterior)) {
                    @unlink($uploadBase . $archivoAnterior);
                }
                $nombreArchivoGuardar = $nuevoNombre;
                $subioArchivo = true;
            }
        }

        $refFinal = $refNueva;
        if ($refFinal === '' && !$subioArchivo && $archivoAnterior === '') {
            echo json_encode(['error' => 'Indique un número de referencia o adjunte un comprobante de pago.']);
            exit;
        }

        $refSql = $refFinal;
        $archSql = $nombreArchivoGuardar;

        $stmtUp = $conn->prepare(
            'UPDATE cotizaciones SET
                comprobante_referencia = NULLIF(TRIM(?), \'\'),
                comprobante_archivo = NULLIF(TRIM(?), \'\'),
                comprobante_fecha = NOW(),
                forma_pago_id = ?
             WHERE id_cotizacion = ? AND id_cliente = ?'
        );
        $stmtUp->bind_param('ssiii', $refSql, $archSql, $forma_id, $id_cot, $id_cliente);
        if (!$stmtUp->execute()) {
            $stmtUp->close();
            echo json_encode(['error' => 'No se pudo guardar los datos.']);
            exit;
        }
        $stmtUp->close();
        echo json_encode(['ok' => true, 'mensaje' => 'Comprobante guardado correctamente.']);
        break;
}
