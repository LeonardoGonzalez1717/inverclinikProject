<?php
require_once "../connection/connection.php";
require_once __DIR__ . '/../lib/Auditoria.php';
require_once __DIR__ . '/../lib/Pagination.php';

error_reporting(E_ERROR | E_PARSE);

function ensureSchemaTallas(mysqli $conn): void
{
    $conn->query(
        "CREATE TABLE IF NOT EXISTS `tallas` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `rango_tallas_id` int(11) NOT NULL,
            `nombre` varchar(20) NOT NULL,
            `orden` int(11) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_rango_talla` (`rango_tallas_id`,`nombre`),
            KEY `rango_tallas_id` (`rango_tallas_id`),
            CONSTRAINT `fk_tallas_rango` FOREIGN KEY (`rango_tallas_id`) REFERENCES `rangos_tallas` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
    );

    $check = $conn->query("SHOW COLUMNS FROM rangos_tallas LIKE 'tallas_desde'");
    if (!$check || $check->num_rows === 0) {
        return;
    }

    $res = $conn->query('SELECT id, tallas_desde, tallas_hasta FROM rangos_tallas');
    if ($res) {
        $stmtCnt = $conn->prepare('SELECT COUNT(*) AS c FROM tallas WHERE rango_tallas_id = ?');
        $stmtIns = $conn->prepare(
            'INSERT IGNORE INTO tallas (rango_tallas_id, nombre, orden) VALUES (?, ?, ?)'
        );

        while ($row = $res->fetch_assoc()) {
            $rangoId = (int) $row['id'];
            $stmtCnt->bind_param('i', $rangoId);
            $stmtCnt->execute();
            $cnt = (int) ($stmtCnt->get_result()->fetch_assoc()['c'] ?? 0);
            if ($cnt > 0) {
                continue;
            }

            $desde = (int) $row['tallas_desde'];
            $hasta = (int) $row['tallas_hasta'];
            $orden = 0;
            for ($i = $desde; $i <= $hasta; $i++) {
                $nombre = (string) $i;
                $stmtIns->bind_param('isi', $rangoId, $nombre, $orden);
                $stmtIns->execute();
                $orden++;
            }
        }
        $stmtCnt->close();
        $stmtIns->close();
    }

    $conn->query('ALTER TABLE rangos_tallas DROP COLUMN tallas_desde');
    $conn->query('ALTER TABLE rangos_tallas DROP COLUMN tallas_hasta');
}

function obtenerIdRangoPorNombre(mysqli $conn, string $nombre): ?int
{
    $stmt = $conn->prepare('SELECT id FROM rangos_tallas WHERE nombre_rango = ? ORDER BY id ASC LIMIT 1');
    $stmt->bind_param('s', $nombre);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ? (int) $row['id'] : null;
}

function asegurarRangoCanonico(mysqli $conn, string $nombre, string $descripcion): int
{
    $id = obtenerIdRangoPorNombre($conn, $nombre);
    if ($id !== null) {
        return $id;
    }
    $stmt = $conn->prepare('INSERT INTO rangos_tallas (nombre_rango, descripcion) VALUES (?, ?)');
    $stmt->bind_param('ss', $nombre, $descripcion);
    $stmt->execute();
    $id = (int) $conn->insert_id;
    $stmt->close();
    return $id;
}

function fusionarRangosDuplicados(mysqli $conn, string $nombreCanonico): void
{
    $stmt = $conn->prepare(
        'SELECT id FROM rangos_tallas WHERE nombre_rango = ? ORDER BY id ASC'
    );
    $stmt->bind_param('s', $nombreCanonico);
    $stmt->execute();
    $res = $stmt->get_result();
    $ids = [];
    while ($row = $res->fetch_assoc()) {
        $ids[] = (int) $row['id'];
    }
    $stmt->close();

    if (count($ids) <= 1) {
        return;
    }

    $principalId = $ids[0];
    $duplicados = array_slice($ids, 1);
    $tablas = ['productos', 'recetas', 'recetas_productos', 'inventario_productos'];

    foreach ($duplicados as $dupId) {
        foreach ($tablas as $tabla) {
            $chk = $conn->query("SHOW TABLES LIKE '$tabla'");
            if (!$chk || $chk->num_rows === 0) {
                continue;
            }
            $col = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE 'rango_tallas_id'");
            if (!$col || $col->num_rows === 0) {
                continue;
            }
            $stmtU = $conn->prepare("UPDATE `$tabla` SET rango_tallas_id = ? WHERE rango_tallas_id = ?");
            $stmtU->bind_param('ii', $principalId, $dupId);
            $stmtU->execute();
            $stmtU->close();
        }

        $chkDet = $conn->query("SHOW TABLES LIKE 'inventario_detalle'");
        if ($chkDet && $chkDet->num_rows > 0) {
            $colDet = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'rango_tallas_id'");
            if ($colDet && $colDet->num_rows > 0) {
                $stmtU = $conn->prepare(
                    'UPDATE inventario_detalle SET rango_tallas_id = ? WHERE rango_tallas_id = ?'
                );
                $stmtU->bind_param('ii', $principalId, $dupId);
                $stmtU->execute();
                $stmtU->close();
            }
        }

        $stmtD = $conn->prepare('DELETE FROM rangos_tallas WHERE id = ?');
        $stmtD->bind_param('i', $dupId);
        $stmtD->execute();
        $stmtD->close();
    }
}

function consolidarRangosAdultosNinos(mysqli $conn): void
{
    $obsoletos = ['Talla Única', 'XS', 'S', 'M', 'L', 'XL', 'XXL'];
    $placeholders = implode(',', array_fill(0, count($obsoletos), '?'));
    $types = str_repeat('s', count($obsoletos));

    $stmtCheck = $conn->prepare(
        "SELECT COUNT(*) AS c FROM rangos_tallas WHERE nombre_rango IN ($placeholders)"
    );
    $stmtCheck->bind_param($types, ...$obsoletos);
    $stmtCheck->execute();
    $hayObsoletos = (int) ($stmtCheck->get_result()->fetch_assoc()['c'] ?? 0) > 0;
    $stmtCheck->close();

    asegurarRangoCanonico($conn, 'Niños', 'Tallas infantiles');
    asegurarRangoCanonico($conn, 'Adultos', 'Tallas para adultos');

    if (!$hayObsoletos) {
        fusionarRangosDuplicados($conn, 'Niños');
        fusionarRangosDuplicados($conn, 'Adultos');
        return;
    }

    $tallasDefecto = [
        'Niños' => ['2', '4', '6', '8', '10', '12', '14'],
        'Adultos' => ['XS', 'S', 'M', 'L', 'XL', 'XXL', 'Única'],
    ];

    $stmtIns = $conn->prepare(
        'INSERT IGNORE INTO tallas (rango_tallas_id, nombre, orden) VALUES (?, ?, ?)'
    );
    foreach ($tallasDefecto as $nombreRango => $tallas) {
        $stmtR = $conn->prepare('SELECT id FROM rangos_tallas WHERE nombre_rango = ? LIMIT 1');
        $stmtR->bind_param('s', $nombreRango);
        $stmtR->execute();
        $rangoRow = $stmtR->get_result()->fetch_assoc();
        $stmtR->close();
        if (!$rangoRow) {
            continue;
        }
        $rangoId = (int) $rangoRow['id'];
        foreach ($tallas as $orden => $nombreTalla) {
            $stmtIns->bind_param('isi', $rangoId, $nombreTalla, $orden);
            $stmtIns->execute();
        }
    }
    $stmtIns->close();

    $stmtAdultos = $conn->prepare("SELECT id FROM rangos_tallas WHERE nombre_rango = 'Adultos' LIMIT 1");
    $stmtAdultos->execute();
    $adultos = $stmtAdultos->get_result()->fetch_assoc();
    $stmtAdultos->close();
    if (!$adultos) {
        return;
    }
    $adultosId = (int) $adultos['id'];

    $tablas = ['productos', 'recetas', 'recetas_productos', 'inventario_productos'];
    foreach ($tablas as $tabla) {
        $chk = $conn->query("SHOW TABLES LIKE '$tabla'");
        if (!$chk || $chk->num_rows === 0) {
            continue;
        }
        $col = $conn->query("SHOW COLUMNS FROM `$tabla` LIKE 'rango_tallas_id'");
        if (!$col || $col->num_rows === 0) {
            continue;
        }
        $sql = "UPDATE `$tabla` t
                INNER JOIN rangos_tallas rt_old ON rt_old.id = t.rango_tallas_id
                SET t.rango_tallas_id = ?
                WHERE rt_old.nombre_rango IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i' . $types, $adultosId, ...$obsoletos);
        $stmt->execute();
        $stmt->close();
    }

    $chkDet = $conn->query("SHOW TABLES LIKE 'inventario_detalle'");
    if ($chkDet && $chkDet->num_rows > 0) {
        $colDet = $conn->query("SHOW COLUMNS FROM inventario_detalle LIKE 'rango_tallas_id'");
        if ($colDet && $colDet->num_rows > 0) {
            $sql = "UPDATE inventario_detalle t
                    INNER JOIN rangos_tallas rt_old ON rt_old.id = t.rango_tallas_id
                    SET t.rango_tallas_id = ?
                    WHERE rt_old.nombre_rango IN ($placeholders)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param('i' . $types, $adultosId, ...$obsoletos);
            $stmt->execute();
            $stmt->close();
        }
    }

    $stmtDel = $conn->prepare("DELETE FROM rangos_tallas WHERE nombre_rango IN ($placeholders)");
    $stmtDel->bind_param($types, ...$obsoletos);
    $stmtDel->execute();
    $stmtDel->close();

    fusionarRangosDuplicados($conn, 'Niños');
    fusionarRangosDuplicados($conn, 'Adultos');
}

function validarTallasLista($tallasRaw): array
{
    if (!is_array($tallasRaw) || count($tallasRaw) === 0) {
        throw new Exception('Debe agregar al menos una talla al rango');
    }

    $tallas = [];
    $vistas = [];
    foreach ($tallasRaw as $t) {
        $nombre = trim(is_array($t) ? ($t['nombre'] ?? '') : (string) $t);
        if ($nombre === '') {
            continue;
        }
        if (mb_strlen($nombre) > 20) {
            throw new Exception('Cada talla no puede superar 20 caracteres');
        }
        $key = mb_strtolower($nombre);
        if (isset($vistas[$key])) {
            throw new Exception('Hay tallas duplicadas en el listado: ' . $nombre);
        }
        $vistas[$key] = true;
        $tallas[] = $nombre;
    }

    if (count($tallas) === 0) {
        throw new Exception('Debe agregar al menos una talla al rango');
    }

    return $tallas;
}

function validarRangoTallas(array $data, mysqli $conn, ?int $excluirId = null): array
{
    $nombre = trim($data['nombre_rango'] ?? '');
    $descripcion = trim($data['descripcion'] ?? '');
    $tallas = validarTallasLista($data['tallas'] ?? []);

    if ($nombre === '') {
        throw new Exception('El nombre del rango es obligatorio');
    }
    if (mb_strlen($nombre) > 50) {
        throw new Exception('El nombre del rango no puede superar 50 caracteres');
    }
    if (mb_strlen($descripcion) > 100) {
        throw new Exception('La descripción no puede superar 100 caracteres');
    }

    $sqlDup = 'SELECT id FROM rangos_tallas WHERE nombre_rango = ?';
    if ($excluirId !== null) {
        $sqlDup .= ' AND id != ?';
    }
    $stmtDup = $conn->prepare($sqlDup);
    if ($excluirId !== null) {
        $stmtDup->bind_param('si', $nombre, $excluirId);
    } else {
        $stmtDup->bind_param('s', $nombre);
    }
    $stmtDup->execute();
    $dup = $stmtDup->get_result()->fetch_assoc();
    $stmtDup->close();
    if ($dup) {
        throw new Exception('Ya existe un rango de tallas con ese nombre');
    }

    return [
        'nombre_rango' => $nombre,
        'descripcion' => $descripcion !== '' ? $descripcion : null,
        'tallas' => $tallas,
    ];
}

function guardarTallasRango(mysqli $conn, int $rangoId, array $tallas): void
{
    $stmtDel = $conn->prepare('DELETE FROM tallas WHERE rango_tallas_id = ?');
    $stmtDel->bind_param('i', $rangoId);
    $stmtDel->execute();
    $stmtDel->close();

    $stmtIns = $conn->prepare(
        'INSERT INTO tallas (rango_tallas_id, nombre, orden) VALUES (?, ?, ?)'
    );
    foreach ($tallas as $orden => $nombre) {
        $stmtIns->bind_param('isi', $rangoId, $nombre, $orden);
        $stmtIns->execute();
    }
    $stmtIns->close();
}

function contarUsoRango(mysqli $conn, int $id): int
{
    $stmt = $conn->prepare(
        'SELECT (
            (SELECT COUNT(*) FROM recetas WHERE rango_tallas_id = ?)
            + (SELECT COUNT(*) FROM recetas_productos WHERE rango_tallas_id = ?)
            + (SELECT COUNT(*) FROM productos WHERE rango_tallas_id = ?)
        ) AS total'
    );
    $stmt->bind_param('iii', $id, $id, $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return (int) ($row['total'] ?? 0);
}

ensureSchemaTallas($conn);
consolidarRangosAdultosNinos($conn);

$action = $_POST['action'] ?? '';
$data = [];

if (empty($action)) {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true) ?: [];
    $action = $data['action'] ?? '';
}

try {
    if ($action === 'listar_html') {
        $total = (int) ($conn->query('SELECT COUNT(*) AS c FROM rangos_tallas')->fetch_assoc()['c'] ?? 0);
        $pg = Pagination::fromInput($total, $_POST);
        $sql = 'SELECT rt.id, rt.nombre_rango, rt.descripcion,
                       GROUP_CONCAT(t.nombre ORDER BY t.orden SEPARATOR \', \') AS tallas_lista,
                       COUNT(t.id) AS total_tallas
                FROM rangos_tallas rt
                LEFT JOIN tallas t ON t.rango_tallas_id = rt.id
                GROUP BY rt.id, rt.nombre_rango, rt.descripcion
                ORDER BY rt.nombre_rango ASC' . $pg->limitClause();
        $result = $conn->query($sql);

        ob_start();
        $i = $pg->rowNumberStart() - 1;
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $i++;
                $desc = $row['descripcion'] !== null && $row['descripcion'] !== '' ? htmlspecialchars($row['descripcion']) : '—';
                $tallasLista = $row['tallas_lista'] !== null && $row['tallas_lista'] !== ''
                    ? htmlspecialchars($row['tallas_lista'])
                    : '—';
                echo '<tr>';
                echo '<td>' . $i . '</td>';
                echo '<td>' . htmlspecialchars($row['nombre_rango']) . '</td>';
                echo '<td>' . $tallasLista . '</td>';
                echo '<td>' . (int) $row['total_tallas'] . '</td>';
                echo '<td>' . $desc . '</td>';
                echo '<td>'
                    . '<button type="button" class="btn btn-sm btn-primary" onclick="editarRangoTallas(' . (int) $row['id'] . ')">Editar</button> '
                    . '<button type="button" class="btn btn-sm btn-danger" onclick="eliminarRangoTallas(' . (int) $row['id'] . ')">Eliminar</button>'
                    . '</td>';
                echo '</tr>';
            }
        } else {
            echo '<tr><td colspan="6" class="text-center">No hay rangos de tallas registrados</td></tr>';
        }
        $rowsHtml = ob_get_clean();
        Pagination::sendJsonList($rowsHtml, $pg);
        $conn->close();
        exit;
    }

    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'obtener') {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('ID inválido');
        }

        $stmt = $conn->prepare('SELECT id, nombre_rango, descripcion FROM rangos_tallas WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $rango = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$rango) {
            throw new Exception('Rango de tallas no encontrado');
        }

        $stmtT = $conn->prepare(
            'SELECT id, nombre, orden FROM tallas WHERE rango_tallas_id = ? ORDER BY orden ASC, id ASC'
        );
        $stmtT->bind_param('i', $id);
        $stmtT->execute();
        $resT = $stmtT->get_result();
        $tallas = [];
        while ($t = $resT->fetch_assoc()) {
            $tallas[] = [
                'id' => (int) $t['id'],
                'nombre' => $t['nombre'],
                'orden' => (int) $t['orden'],
            ];
        }
        $stmtT->close();

        echo json_encode([
            'success' => true,
            'rango' => [
                'id' => (int) $rango['id'],
                'nombre_rango' => $rango['nombre_rango'],
                'descripcion' => $rango['descripcion'],
                'tallas' => $tallas,
            ],
        ]);
        $conn->close();
        exit;
    }

    if ($action === 'crear') {
        $campos = validarRangoTallas($data, $conn);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'INSERT INTO rangos_tallas (nombre_rango, descripcion) VALUES (?, ?)'
            );
            $stmt->bind_param('ss', $campos['nombre_rango'], $campos['descripcion']);
            $stmt->execute();
            $newId = (int) $conn->insert_id;
            $stmt->close();

            guardarTallasRango($conn, $newId, $campos['tallas']);
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

        Auditoria::registrar(
            $conn,
            'Rango de tallas creado: id ' . $newId . ' — ' . $campos['nombre_rango']
                . ' (' . count($campos['tallas']) . ' talla(s))',
            'Rangos de tallas'
        );

        echo json_encode(['success' => true, 'message' => 'Rango de tallas registrado exitosamente']);
        $conn->close();
        exit;
    }

    if ($action === 'editar') {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('ID inválido para actualizar');
        }

        $campos = validarRangoTallas($data, $conn, $id);

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                'UPDATE rangos_tallas SET nombre_rango = ?, descripcion = ? WHERE id = ?'
            );
            $stmt->bind_param('ssi', $campos['nombre_rango'], $campos['descripcion'], $id);
            $stmt->execute();
            if ($stmt->affected_rows === 0) {
                $check = $conn->prepare('SELECT id FROM rangos_tallas WHERE id = ?');
                $check->bind_param('i', $id);
                $check->execute();
                $exists = $check->get_result()->fetch_assoc();
                $check->close();
                if (!$exists) {
                    $stmt->close();
                    throw new Exception('Rango de tallas no encontrado');
                }
            }
            $stmt->close();

            guardarTallasRango($conn, $id, $campos['tallas']);
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            throw $e;
        }

        Auditoria::registrar(
            $conn,
            'Rango de tallas actualizado: id ' . $id . ' — ' . $campos['nombre_rango']
                . ' (' . count($campos['tallas']) . ' talla(s))',
            'Rangos de tallas'
        );

        echo json_encode(['success' => true, 'message' => 'Rango de tallas actualizado exitosamente']);
        $conn->close();
        exit;
    }

    if ($action === 'eliminar') {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new Exception('ID inválido para eliminar');
        }

        $uso = contarUsoRango($conn, $id);
        if ($uso > 0) {
            throw new Exception(
                'No se puede eliminar este rango porque está asociado a ' . $uso . ' producto(s), guía(s) de corte o receta(s).'
            );
        }

        $stmt = $conn->prepare('DELETE FROM rangos_tallas WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        if ($stmt->affected_rows === 0) {
            $stmt->close();
            throw new Exception('Rango de tallas no encontrado');
        }
        $stmt->close();

        Auditoria::registrar($conn, 'Rango de tallas eliminado: id ' . $id, 'Rangos de tallas');

        echo json_encode(['success' => true, 'message' => 'Rango de tallas eliminado']);
        $conn->close();
        exit;
    }

    throw new Exception('Acción no válida');

} catch (Exception $e) {
    if ($action !== 'listar_html') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
