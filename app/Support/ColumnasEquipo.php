<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\Views\Column;

/**
 * Las columnas de identidad de un equipo que comparten las tablas (Productos,
 * el detalle de la compra y el tablero): una copia, no tres que divergen.
 *
 * La tabla que las usa necesita 'productos.upc' en setAdditionalSelects: la
 * columna lee el serial dentro de su format().
 */
class ColumnasEquipo
{
    /**
     * «IMEI / Serial»: el IMEI y, debajo, el serial; sin IMEI (un iPad Wi-Fi,
     * un Apple Watch), «Serial ...». Antes era la columna «Imei», vacia en esos
     * equipos.
     *
     * Busca los dos por una parte (los ultimos digitos del IMEI o del serial):
     * la busqueda de una tabla solo filtra la lista, no elige sola, asi que un
     * LIKE sobre el serial no mezcla nada. La regla del Enter exacto es otra
     * (EligePorCodigoTrait).
     */
    public static function imeiSerial(): Column
    {
        return Column::make('IMEI / Serial', 'imei')
            ->sortable()
            ->searchable(function (Builder $q, $term) {
                $patron = '%' . addcslashes(trim((string) $term), '%_\\') . '%';
                $q->orWhere('productos.imei', 'like', $patron)
                    ->orWhere('productos.upc', 'like', $patron);
            })
            ->format(function ($imei, $row) {
                $serial = $row->upc;

                if (!$imei) {
                    return $serial ? '<span class="font-mono break-all">Serial ' . e($serial) . '</span>' : '<span class="text-gray-400">—</span>';
                }

                return '<span class="font-mono">' . e($imei) . '</span>'
                    . ($serial ? '<span class="block text-xs text-gray-500 font-mono break-all">Serial ' . e($serial) . '</span>' : '');
            })
            ->html();
    }

    /** «Capacidad»: opcional (un Apple Watch no tiene), «—» en vez de un hueco. */
    public static function capacidad(): Column
    {
        return Column::make('Capacidad', 'almacenamiento')
            ->sortable()
            ->format(fn($v) => $v ?: '—');
    }
}
