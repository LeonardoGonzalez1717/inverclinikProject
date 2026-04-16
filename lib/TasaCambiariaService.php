<?php

require_once __DIR__ . '/../classes/TasaBCV.php';

class TasaCambiariaService
{
    private const TZ_VENEZUELA = 'America/Caracas';

    private static function ahoraVz(): DateTime
    {
        return new DateTime('now', new DateTimeZone(self::TZ_VENEZUELA));
    }

    /**
     * Intenta registrar automáticamente la tasa BCV.
     * Evita consultas frecuentes por intervalo y no duplica por franja horaria del día.
     *
     * @return array{success:bool, updated:bool, message:string, tasa?:float}
     */
    public static function actualizarAutomatica(
        mysqli $conn,
        ?int $usuarioId = null,
        int $intervaloMinutos = 60
    ): array {
        $intervaloMinutos = max(1, $intervaloMinutos);
        $ahoraVz = self::ahoraVz();

        $consultaUltima = $conn->query("SELECT fecha_hora FROM tasas_cambiarias ORDER BY fecha_hora DESC LIMIT 1");
        if ($consultaUltima && $consultaUltima->num_rows > 0) {
            $ultima = $consultaUltima->fetch_assoc();
            $ultimaFecha = strtotime((string) ($ultima['fecha_hora'] ?? ''));
            if ($ultimaFecha && ($ahoraVz->getTimestamp() - $ultimaFecha) < ($intervaloMinutos * 60)) {
                return [
                    'success' => true,
                    'updated' => false,
                    'message' => 'No se actualiza: intervalo minimo no cumplido.'
                ];
            }
        }

        $ahora = $ahoraVz->format('Y-m-d H:i:s');
        $fechaSolo = $ahoraVz->format('Y-m-d');
        $hora = (int) $ahoraVz->format('H');
        $esFranjaManana = ($hora < 13);

        if ($esFranjaManana) {
            $check = $conn->prepare("SELECT id FROM tasas_cambiarias WHERE DATE(fecha_hora) = ? AND HOUR(fecha_hora) < 13 LIMIT 1");
        } else {
            $check = $conn->prepare("SELECT id FROM tasas_cambiarias WHERE DATE(fecha_hora) = ? AND HOUR(fecha_hora) >= 13 LIMIT 1");
        }
        $check->bind_param('s', $fechaSolo);
        $check->execute();
        $existeFranja = $check->get_result();
        $check->close();

        if ($existeFranja && $existeFranja->num_rows > 0) {
            return [
                'success' => true,
                'updated' => false,
                'message' => 'No se actualiza: ya existe tasa para la franja actual.'
            ];
        }

        $bcv = new TasaBCV();
        $tasa = $bcv->obtenerTasa();
        if ($tasa === null || $tasa <= 0) {
            return [
                'success' => false,
                'updated' => false,
                'message' => 'No se pudo obtener la tasa BCV. ' . ($bcv->getLastError() ?: '')
            ];
        }

        if ($usuarioId !== null) {
            $stmt = $conn->prepare("INSERT INTO tasas_cambiarias (tasa, fecha_hora, origen, usuario_id) VALUES (?, ?, 'bcv', ?)");
            $stmt->bind_param('dsi', $tasa, $ahora, $usuarioId);
        } else {
            $stmt = $conn->prepare("INSERT INTO tasas_cambiarias (tasa, fecha_hora, origen) VALUES (?, ?, 'bcv')");
            $stmt->bind_param('ds', $tasa, $ahora);
        }
        $stmt->execute();
        $stmt->close();

        return [
            'success' => true,
            'updated' => true,
            'message' => 'Tasa BCV actualizada automaticamente.',
            'tasa' => $tasa
        ];
    }

    /**
     * Actualización programada para ejecutarse 2 veces al día (08:30 y 13:30, hora Venezuela).
     * El script no inserta si ya existe la franja correspondiente del día.
     *
     * @return array{success:bool, updated:bool, message:string, tasa?:float}
     */
    public static function actualizarProgramadaVz(mysqli $conn, ?int $usuarioId = null): array
    {
        $ahoraVz = self::ahoraVz();
        $hora = (int) $ahoraVz->format('H');
        $minuto = (int) $ahoraVz->format('i');

        // Franja manana: desde 8:30 hasta antes de 13:00 (BCV publica ~8:30).
        $permiteManana = (($hora === 8 && $minuto >= 30) || ($hora > 8 && $hora < 13));
        // Franja tarde: desde 13:30 en adelante (BCV publica ~13:30). 13:00-13:29 queda fuera a proposito.
        $permiteTarde = ($hora === 13 && $minuto >= 30) || ($hora > 13);

        if (!$permiteManana && !$permiteTarde) {
            if ($hora === 13 && $minuto < 30) {
                return [
                    'success' => true,
                    'updated' => false,
                    'message' => 'Fuera de ventana: la actualizacion de la tarde aplica desde las 13:30 (hora Venezuela).'
                ];
            }
            return [
                'success' => true,
                'updated' => false,
                'message' => 'Fuera de ventana programada (08:30-12:59 o desde 13:30, hora Venezuela).'
            ];
        }

        return self::actualizarAutomatica($conn, $usuarioId, 1);
    }
}
