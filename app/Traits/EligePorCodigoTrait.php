<?php

namespace App\Traits;

/**
 * El Enter de la pistola USB (y de la camara, que lo imita) en los buscadores
 * de los modales: si el codigo coincide EXACTO con una sola fila, se elige.
 *
 * Es el mismo criterio que BuscadorArticulosService::porCodigo(), sobre las
 * filas que cada componente ya filtro (estado, sucursal, vigentes): asi el
 * Enter no se salta ninguna regla de la pantalla. Cero o varias coincidencias
 * (el UPC de una caja es del modelo, no de la unidad) devuelven null y la
 * pantalla deja la lista para elegir a mano.
 */
trait EligePorCodigoTrait
{
    /**
     * @param  iterable  $filas   modelos o arreglos
     * @param  string[]  $campos  los codigos que valen como exactos
     */
    protected function unicoPorCodigo(iterable $filas, ?string $codigo, array $campos = ['imei', 'sku', 'upc']): mixed
    {
        $codigo = trim((string) $codigo);

        if ($codigo === '') {
            return null;
        }

        $exactas = collect($filas)->filter(function ($fila) use ($codigo, $campos) {
            foreach ($campos as $campo) {
                if ((string) data_get($fila, $campo) === $codigo) {
                    return true;
                }
            }

            return false;
        });

        return $exactas->count() === 1 ? $exactas->first() : null;
    }
}
