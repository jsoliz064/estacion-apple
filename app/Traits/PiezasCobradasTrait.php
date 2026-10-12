<?php

namespace App\Traits;

use App\Models\ProductoReparacionRepuesto;
use App\Services\RepuestosDeReparacionService;
use Illuminate\Validation\ValidationException;

/**
 * El aviso antes de quitar o cambiar una pieza de reparacion que ya se COBRO en
 * una venta. La FK de cobro (ventas_detalles.producto_reparacion_repuesto_id) va
 * en RESTRICT, asi que borrarla fallaria con 1451; esto lo dice antes y con la
 * venta a la vista.
 *
 * Antes la FK iba en SET NULL: borrar la pieza convertia el cobro en una venta
 * normal que empezaba a mover stock que nunca debio mover.
 *
 * El componente declara $repuestos (lineas con 'id' y 'cantidad') y
 * $repuestosEliminados (ids).
 */
trait PiezasCobradasTrait
{
    protected function exigirPiezasNoCobradas(): void
    {
        $cobros = app(RepuestosDeReparacionService::class);

        foreach ($this->repuestosEliminados as $id) {
            if ($ventaId = $cobros->ventaDelCobro((int) $id)) {
                throw ValidationException::withMessages([
                    'repuestos' => "Esa pieza ya se cobró en la venta #{$ventaId}: anula el cobro desde la venta antes de quitarla.",
                ]);
            }
        }

        $ids = collect($this->repuestos)->pluck('id')->filter()->all();
        $originales = ProductoReparacionRepuesto::whereIn('id', $ids)->get()->keyBy('id');

        foreach ($this->repuestos as $linea) {
            $original = !empty($linea['id']) ? $originales->get($linea['id']) : null;

            if ($original && (int) $original->cantidad !== (int) $linea['cantidad'] && ($ventaId = $cobros->ventaDelCobro($original->id))) {
                throw ValidationException::withMessages([
                    'repuestos' => "Esa pieza ya se cobró en la venta #{$ventaId}: su cantidad no se puede cambiar.",
                ]);
            }
        }
    }
}
