<?php
/**
 * Normalización de cantidades según la unidad de medida del insumo.
 * permite_movimiento_decimal = 0 → solo cantidades enteras en inventario.
 */

declare(strict_types=1);

/**
 * @throws InvalidArgumentException
 */
function inv_normalizar_cantidad_movimiento_manual(float $cantidad, bool $permite_movimiento_decimal): float
{
    if ($cantidad <= 0) {
        throw new InvalidArgumentException('La cantidad debe ser mayor a 0.');
    }
    if ($permite_movimiento_decimal) {
        return round($cantidad, 4);
    }
    $entero = (int) round($cantidad);
    if (abs($cantidad - $entero) > 1e-6) {
        throw new InvalidArgumentException(
            'Esta unidad de medida solo admite cantidades enteras (sin decimales).'
        );
    }
    if ($entero < 1) {
        throw new InvalidArgumentException('La cantidad mínima es 1.');
    }
    return (float) $entero;
}

/**
 * Productos terminados: inventario, ventas y órdenes solo mueven unidades enteras.
 *
 * @throws InvalidArgumentException
 */
function inv_normalizar_cantidad_producto_terminado(float $cantidad): float
{
    return inv_normalizar_cantidad_movimiento_manual($cantidad, false);
}

/** Consumo calculado (receta × orden): sin decimales se usa techo para no sub-registrar el consumo. */
function inv_normalizar_cantidad_consumo_automatico(float $cantidad, bool $permite_movimiento_decimal): float
{
    if ($cantidad <= 0) {
        return 0.0;
    }
    if ($permite_movimiento_decimal) {
        return round($cantidad, 4);
    }
    return (float) max(1, (int) ceil($cantidad - 1e-9));
}
