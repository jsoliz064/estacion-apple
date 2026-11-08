<?php

namespace App\Traits;

use App\Enums\Moneda;
use App\Support\Dinero;

/**
 * Costo y precio cargados en bolivianos, con UN tipo de cambio para los dos.
 * Lo usan Producto, Repuesto y Accesorio.
 *
 * Todo se guarda en USD. Un monto en Bs se convierte AQUI, una vez, al guardar
 * (Bs / TC), y no en cada pantalla: las columnas en USD son lo que leen la
 * compra, la venta y los reportes, y no pueden contradecir a los Bs de los que
 * salen. Asi el alta, la edicion y el reemplazo de un reclamo dan el mismo
 * numero. Actualizar el TC del dia no los toca: se convirtieron con el suyo.
 *
 * Columnas: `moneda`, `tipo_cambio` y, por cada monto, su gemela en Bs.
 */
trait MontosEnMonedaTrait
{
    /** [columna en USD => columna con los Bs cargados]. */
    abstract public static function columnasEnMoneda(): array;

    protected static function bootMontosEnMonedaTrait(): void
    {
        static::saving(function (self $modelo) {
            $moneda = $modelo->getAttribute('moneda') ?? Moneda::base()->value;

            if ($moneda === Moneda::alterna()->value) {
                $tc = (float) $modelo->getAttribute('tipo_cambio');

                foreach (static::columnasEnMoneda() as $usd => $bs) {
                    // Sin TC no se divide: el CHECK de la tabla lo rechaza con
                    // su nombre, en vez de un division by zero.
                    if ($tc > 0) {
                        $modelo->setAttribute($usd, round((float) $modelo->getAttribute($bs) / $tc, 2));
                    }
                }
            } else {
                $modelo->setAttribute('tipo_cambio', null);

                foreach (static::columnasEnMoneda() as $bs) {
                    $modelo->setAttribute($bs, null);
                }
            }
        });
    }

    public function cargadoEnBs(): bool
    {
        return $this->getAttribute('moneda') === Moneda::alterna()->value;
    }

    /** Como se cargo, si fue en Bs: «Bs 4,524.00 / 5,428.80 × 6.96». */
    public function montosEnMoneda(): ?string
    {
        if (!$this->cargadoEnBs()) {
            return null;
        }

        $montos = array_map(fn($bs) => number_format((float) $this->getAttribute($bs), 2), array_values(static::columnasEnMoneda()));

        return 'Bs ' . implode(' / ', $montos) . ' × ' . Dinero::tc($this->getAttribute('tipo_cambio'));
    }
}
