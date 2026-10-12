<?php

namespace App\Traits;

/**
 * Normaliza el SKU y el UPC de un articulo: sin espacios y NULL cuando vienen
 * vacios.
 *
 * Imprescindible por los UNIQUE de `sku`: en MySQL varios NULL conviven en un
 * indice unico, pero dos '' chocan. Sin esto, el segundo articulo guardado con
 * el campo SKU en blanco reventaria con 1062.
 */
trait NormalizaCodigosTrait
{
    public function setSkuAttribute($value): void
    {
        $this->attributes['sku'] = self::normalizarCodigo($value);
    }

    public function setUpcAttribute($value): void
    {
        $this->attributes['upc'] = self::normalizarCodigo($value);
    }

    public static function normalizarCodigo($value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : (string) $value;
    }
}
