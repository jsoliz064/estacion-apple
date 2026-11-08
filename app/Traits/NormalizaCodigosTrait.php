<?php

namespace App\Traits;

/**
 * Normaliza el UPC (serial o codigo de barras) de un equipo o articulo: sin
 * espacios y NULL cuando viene vacio. Un '' no es un codigo: con el, la
 * busqueda exacta por codigo y los stickers de Codigos de barras verian un
 * valor donde no hay ninguno. (Antes protegia tambien el UNIQUE del SKU, que
 * se quito: la tienda no lo usa.)
 */
trait NormalizaCodigosTrait
{
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
