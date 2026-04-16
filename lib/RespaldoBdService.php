<?php

/**
 * Generación de respaldos SQL mediante mysqldump (MySQL/MariaDB).
 * Metadatos en tabla respaldos_bd; archivos en storage/respaldos_bd/
 */
class RespaldoBdService
{
    public static function directorioRespaldos(): string
    {
        if (!defined('ROOT_PATH')) {
            throw new RuntimeException('ROOT_PATH no está definido.');
        }
        $dir = ROOT_PATH . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'respaldos_bd';
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException('No se pudo crear el directorio de respaldos.');
            }
        }
        return $dir;
    }

    /**
     * Busca el ejecutable mysqldump (XAMPP Windows, MYSQL_HOME, PATH).
     */
    public static function encontrarMysqldump(): ?string
    {
        $candidates = [];

        $mysqlHome = getenv('MYSQL_HOME');
        if ($mysqlHome !== false && $mysqlHome !== '') {
            $candidates[] = rtrim($mysqlHome, '\\/') . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR
                . (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' ? 'mysqldump.exe' : 'mysqldump');
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $candidates[] = 'C:\\xampp\\mysql\\bin\\mysqldump.exe';
            $drive = getenv('SystemDrive') ?: 'C:';
            $candidates[] = $drive . '\\xampp\\mysql\\bin\\mysqldump.exe';
        } else {
            $candidates[] = '/usr/bin/mysqldump';
            $candidates[] = '/usr/local/bin/mysqldump';
        }

        foreach ($candidates as $p) {
            if ($p === '' || !is_file($p)) {
                continue;
            }
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN' || is_executable($p)) {
                return $p;
            }
        }

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $out = shell_exec('where mysqldump 2>nul');
        } else {
            $out = shell_exec('command -v mysqldump 2>/dev/null');
        }
        if ($out !== null && $out !== '') {
            $line = trim(preg_split('/\r\n|\n|\r/', $out)[0] ?? '');
            if ($line !== '' && is_file($line)) {
                return $line;
            }
        }

        return null;
    }

    /**
     * ¿Ya hay un registro de respaldo para el día actual (America/Caracas)?
     */
    public static function existeRespaldoHoy(mysqli $conn): bool
    {
        $tz = new DateTimeZone('America/Caracas');
        $inicio = (new DateTimeImmutable('today', $tz))->format('Y-m-d H:i:s');
        $fin = (new DateTimeImmutable('tomorrow', $tz))->format('Y-m-d H:i:s');

        $stmt = $conn->prepare(
            'SELECT 1 FROM respaldos_bd WHERE creado_en >= ? AND creado_en < ? LIMIT 1'
        );
        if ($stmt === false) {
            throw new RuntimeException('No se pudo consultar respaldos_bd: ' . $conn->error);
        }
        $stmt->bind_param('ss', $inicio, $fin);
        $stmt->execute();
        $res = $stmt->get_result();
        $ok = $res && $res->num_rows > 0;
        $stmt->close();

        return $ok;
    }

    /**
     * @param string $origen 'automatico' | 'manual'
     * @return array{success:bool,message:string,skipped?:bool,filename?:string,size?:int,id?:int}
     */
    public static function crearRespaldo(
        mysqli $conn,
        string $host,
        string $user,
        string $pass,
        string $db,
        string $origen = 'automatico',
        ?int $usuarioId = null
    ): array {
        $db = trim($db);
        if ($db === '') {
            return ['success' => false, 'message' => 'Nombre de base de datos no válido.'];
        }

        $origen = ($origen === 'manual') ? 'manual' : 'automatico';

        if (self::existeRespaldoHoy($conn)) {
            return [
                'success' => true,
                'skipped' => true,
                'message' => 'Ya existe un respaldo para el día de hoy; no se generó otro.',
            ];
        }

        $exe = self::encontrarMysqldump();
        if ($exe === null) {
            return [
                'success' => false,
                'message' => 'No se encontró mysqldump. En XAMPP suele estar en C:\\xampp\\mysql\\bin\\mysqldump.exe. Añada esa carpeta al PATH o defina la variable de entorno MYSQL_HOME.',
            ];
        }

        $dir = self::directorioRespaldos();
        $safeDb = preg_replace('/[^a-zA-Z0-9_-]/', '_', $db);
        $fname = $safeDb . '_' . date('Y-m-d_H-i-s') . '.sql';
        $path = $dir . DIRECTORY_SEPARATOR . $fname;

        $cmdLine = escapeshellarg($exe)
            . ' --user=' . escapeshellarg($user)
            . ' --password=' . escapeshellarg($pass)
            . ' --host=' . escapeshellarg($host)
            . ' --default-character-set=utf8mb4 --single-transaction --routines --events '
            . escapeshellarg($db);

        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['file', $path, 'wb'],
            2 => ['pipe', 'w'],
        ];

        $proc = @proc_open($cmdLine, $descriptorspec, $pipes);

        if (!is_resource($proc)) {
            return ['success' => false, 'message' => 'No se pudo iniciar mysqldump.'];
        }

        fclose($pipes[0]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $code = proc_close($proc);

        clearstatcache(true, $path);
        $size = is_file($path) ? (int) filesize($path) : 0;

        if ($code !== 0 || $size === 0) {
            if (is_file($path)) {
                @unlink($path);
            }
            $detail = trim($stderr);
            if ($detail === '') {
                $detail = 'Código de salida: ' . $code;
            }
            return ['success' => false, 'message' => 'Error al generar el respaldo: ' . $detail];
        }

        $tz = new DateTimeZone('America/Caracas');
        $creadoEn = (new DateTimeImmutable('now', $tz))->format('Y-m-d H:i:s');

        if ($usuarioId === null) {
            $stmt = $conn->prepare(
                'INSERT INTO respaldos_bd (nombre_archivo, tamano_bytes, creado_en, origen, usuario_id) VALUES (?, ?, ?, ?, NULL)'
            );
            if ($stmt === false) {
                @unlink($path);
                return ['success' => false, 'message' => 'Error al registrar el respaldo: ' . $conn->error];
            }
            $stmt->bind_param('siss', $fname, $size, $creadoEn, $origen);
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO respaldos_bd (nombre_archivo, tamano_bytes, creado_en, origen, usuario_id) VALUES (?, ?, ?, ?, ?)'
            );
            if ($stmt === false) {
                @unlink($path);
                return ['success' => false, 'message' => 'Error al registrar el respaldo: ' . $conn->error];
            }
            $stmt->bind_param('sissi', $fname, $size, $creadoEn, $origen, $usuarioId);
        }

        if (!$stmt->execute()) {
            $err = $stmt->error;
            $stmt->close();
            @unlink($path);
            return ['success' => false, 'message' => 'Error al guardar el registro en la base de datos: ' . $err];
        }
        $newId = (int) $conn->insert_id;
        $stmt->close();

        return [
            'success' => true,
            'message' => 'Respaldo creado correctamente.',
            'filename' => $fname,
            'size' => $size,
            'id' => $newId,
        ];
    }

    /**
     * Listado desde tabla respaldos_bd (solo respaldos registrados).
     *
     * @return list<array{id:int,basename:string,mtime:int,size:int,origen:string,actor:string}>
     */
    public static function listarRegistros(mysqli $conn): array
    {
        $sql = 'SELECT r.id, r.nombre_archivo, r.tamano_bytes, UNIX_TIMESTAMP(r.creado_en) AS ts, r.origen, u.username
                FROM respaldos_bd r
                LEFT JOIN users u ON u.id = r.usuario_id
                ORDER BY r.creado_en DESC';

        $result = $conn->query($sql);
        if ($result === false) {
            throw new RuntimeException('No se pudo listar respaldos_bd: ' . $conn->error);
        }

        $list = [];
        while ($row = $result->fetch_assoc()) {
            $origen = $row['origen'] === 'manual' ? 'manual' : 'automatico';
            $actor = '—';
            if ($origen === 'automatico') {
                $actor = 'Tarea programada';
            } elseif (!empty($row['username'])) {
                $actor = (string) $row['username'];
            }
            $list[] = [
                'id' => (int) $row['id'],
                'basename' => (string) $row['nombre_archivo'],
                'mtime' => (int) $row['ts'],
                'size' => (int) $row['tamano_bytes'],
                'origen' => $origen,
                'actor' => $actor,
            ];
        }
        $result->free();

        return $list;
    }
}
