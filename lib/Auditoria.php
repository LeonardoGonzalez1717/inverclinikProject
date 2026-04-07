<?php

/**
 * Registro centralizado de auditoría: usuario (staff o cliente), módulo, mensaje, fecha e IP.
 * La tabla `auditoria` debe existir en la base (p. ej. db_inverclinik_estructura.sql o sql/migracion_auditoria.sql).
 */
class Auditoria
{
    /**
     * Inserta un evento. Resuelve actor desde la sesión (usuario interno, cliente o sistema).
     *
     * @param mysqli $conn Conexión abierta
     * @param string $mensaje Descripción del movimiento
     * @param string|null $modulo Etiqueta corta (ej. Ventas, Compras)
     * @param array{nombre_actor?:string,id_usuario?:int|null,id_cliente?:int|null}|null $actorManual Si se indica, sustituye la detección por sesión (útil p. ej. intentos de login fallidos).
     */
    public static function registrar(mysqli $conn, string $mensaje, ?string $modulo = null, ?array $actorManual = null): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $idUsuario = null;
        $idCliente = null;
        $nombreActor = 'Sistema';

        if ($actorManual !== null) {
            if (array_key_exists('id_usuario', $actorManual)) {
                $idUsuario = $actorManual['id_usuario'];
                if ($idUsuario !== null) {
                    $idUsuario = (int) $idUsuario;
                }
            }
            if (array_key_exists('id_cliente', $actorManual)) {
                $idCliente = $actorManual['id_cliente'];
                if ($idCliente !== null) {
                    $idCliente = (int) $idCliente;
                }
            }
            $nombreActor = trim((string) ($actorManual['nombre_actor'] ?? 'Sistema'));
            if ($nombreActor === '') {
                $nombreActor = 'Sistema';
            }
        } else {
            $tipo = $_SESSION['tipo'] ?? '';
            if ($tipo === 'usuario' && !empty($_SESSION['iduser'])) {
                $idUsuario = (int) $_SESSION['iduser'];
                $nombreActor = trim((string) ($_SESSION['username'] ?? ''));
                if ($nombreActor === '') {
                    $nombreActor = 'Usuario #' . $idUsuario;
                }
            } elseif ($tipo === 'cliente' && !empty($_SESSION['id_cliente'])) {
                $idCliente = (int) $_SESSION['id_cliente'];
                $nombreActor = trim((string) ($_SESSION['nombre_cliente'] ?? ''));
                if ($nombreActor === '') {
                    $nombreActor = 'Cliente #' . $idCliente;
                }
            }
        }

        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $ip = $ip !== '' ? substr($ip, 0, 45) : null;
        $moduloVal = $modulo !== null && $modulo !== '' ? $modulo : null;

        $sqlIdU = $idUsuario === null ? 'NULL' : (string) (int) $idUsuario;
        $sqlIdC = $idCliente === null ? 'NULL' : (string) (int) $idCliente;
        $sql = "INSERT INTO auditoria (id_usuario, id_cliente, nombre_actor, modulo, accion, ip)
                VALUES ({$sqlIdU}, {$sqlIdC}, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return;
        }

        $stmt->bind_param('ssss', $nombreActor, $moduloVal, $mensaje, $ip);
        $stmt->execute();
        $stmt->close();
    }
}
