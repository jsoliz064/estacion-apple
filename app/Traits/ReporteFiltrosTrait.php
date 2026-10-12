<?php

namespace App\Traits;

use App\Models\Sucursal;

/**
 * Lo comun a todos los reportes (docs/09): el filtro de sucursal y las dos
 * expresiones del descuento prorrateado.
 *
 * El descuento de cabecera de una venta se reparte a prorrata entre TODAS sus
 * lineas (equipos, repuestos y accesorios). Vive aqui y solo aqui: el reporte
 * general, el de productos y el de vendedores tienen que cuadrar entre si.
 * Las expresiones esperan los alias `d` (ventas_detalles) y `v` (ventas).
 */
trait ReporteFiltrosTrait
{
    /** Descuento de cabecera repartido a prorrata entre las lineas de la venta. */
    public const DESCUENTO_PRORRATEADO = 'SUM(d.subtotal / NULLIF(v.subtotal, 0) * v.descuento)';

    /** Ingreso neto: bruto menos descuento prorrateado. */
    public const INGRESO_NETO = 'SUM(d.subtotal) - SUM(d.subtotal / NULLIF(v.subtotal, 0) * v.descuento)';

    /** '' = todas las sucursales. */
    public string $sucursalId = '';

    /** Para el filtro: todas, tambien las inactivas (sus ventas viejas cuentan). */
    public function sucursales()
    {
        return Sucursal::orderBy('nombre')->get(['id', 'nombre', 'activa']);
    }

    /** La sucursal elegida, si es valida. */
    protected function sucursalFiltro(): ?int
    {
        return ctype_digit($this->sucursalId) ? (int) $this->sucursalId : null;
    }

    /** Aplica el filtro de sucursal a la columna dada (`v.sucursal_id`, `p.sucursal_id`...). */
    protected function porSucursal($query, string $columna)
    {
        return $query->when($this->sucursalFiltro(), fn($q, $id) => $q->where($columna, $id));
    }

    public function nombreSucursal(): string
    {
        $id = $this->sucursalFiltro();

        return $id ? (Sucursal::whereKey($id)->value('nombre') ?? 'Sucursal') : 'Todas las sucursales';
    }
}
