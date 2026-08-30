<?php
/**
 * Número único de orden de producción: {id}-{MES}-{AA}
 * Ejemplo: 5-AGOST-26
 */

function abreviatura_mes_orden($mes) {
    $meses = [
        1  => 'ENE',
        2  => 'FEB',
        3  => 'MAR',
        4  => 'ABR',
        5  => 'MAY',
        6  => 'JUN',
        7  => 'JUL',
        8  => 'AGOST',
        9  => 'SEPT',
        10 => 'OCT',
        11 => 'NOV',
        12 => 'DIC',
    ];
    $mes = (int)$mes;
    return $meses[$mes] ?? 'ENE';
}

function numero_lote_inventario($id, $fecha = null) {
    $n = numero_orden_produccion($id, $fecha);
    return $n === '' ? '' : 'L-' . $n;
}

function numero_orden_produccion($id, $fecha = null) {
    $id = (int)$id;
    if ($id <= 0) {
        return '';
    }

    $ts = time();
    if (!empty($fecha) && $fecha !== '0000-00-00' && $fecha !== '0000-00-00 00:00:00') {
        $parsed = strtotime($fecha);
        if ($parsed !== false) {
            $ts = $parsed;
        }
    }

    $mes = abreviatura_mes_orden((int)date('n', $ts));
    $anio = date('y', $ts);

    return $id . '-' . $mes . '-' . $anio;
}

function parse_id_orden_produccion($valor) {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return 0;
    }
    if (preg_match('/^(\d+)/', $valor, $m)) {
        return (int)$m[1];
    }
    return 0;
}
