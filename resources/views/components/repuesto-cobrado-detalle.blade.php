{{--
    Una fila de bitacora que habla de UN repuesto cobrado (o descobrado) con la
    venta del equipo (eventos 'cobro' / 'cobro-anulado').

    Existe porque esas filas llevan producto_reparacion_id y venta_id a la vez,
    y el modal pintaba la reparacion entera y la tabla de la venta DEL TELEFONO
    como si fueran del repuesto. Con tres piezas cobradas salian tres modales
    identicos.

    El cobro es una linea mas de la venta del telefono (ventas_detalles con
    producto_reparacion_repuesto_id), no una venta de repuestos aparte.
--}}
@props(['historial'])

@php
    // La linea concreta de esta fila: mismo repuesto y misma reparacion. Un
    // mismo repuesto puede haberse montado en dos reparaciones del equipo.
    // Si el cobro se anulo, la linea ya no existe.
    $linea = $historial->venta?->detalles
        ->first(function ($detalle) use ($historial) {
            return $detalle->producto_reparacion_repuesto_id
                && $detalle->repuesto_id == $historial->repuesto_id
                && $detalle->reparacionRepuesto?->producto_reparacion_id == $historial->producto_reparacion_id;
        });
    $anulado = $historial->evento === 'cobro-anulado';
@endphp

<div class="m-2 rounded-lg border border-green-200 dark:border-green-700 bg-green-50 dark:bg-green-900 p-4">
    <h3 class="text-sm font-semibold text-green-900 dark:text-green-100">
        {{ $anulado ? 'Cobro de repuesto anulado' : 'Repuesto cobrado con la venta' }}
    </h3>

    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div>
            <span class="block text-xs text-green-700 dark:text-green-300">Repuesto</span>
            <span class="font-semibold text-gray-900 dark:text-white">
                {{ $historial->repuesto?->nombre ?? 'Repuesto eliminado' }}
            </span>
        </div>
        <div>
            <span class="block text-xs text-green-700 dark:text-green-300">Cantidad</span>
            <span class="font-semibold text-gray-900 dark:text-white">{{ $linea?->cantidad ?? '—' }}</span>
        </div>
        <div>
            <span class="block text-xs text-green-700 dark:text-green-300">Precio (Bs)</span>
            <span class="font-semibold text-gray-900 dark:text-white">
                {{ $linea ? number_format((float) $linea->precio, 2) : '—' }}
            </span>
        </div>
        <div>
            <span class="block text-xs text-green-700 dark:text-green-300">Subtotal (Bs)</span>
            <span class="font-semibold text-gray-900 dark:text-white">
                {{ $linea ? number_format((float) $linea->subtotal, 2) : '—' }}
            </span>
        </div>
    </div>

    <div class="mt-4 border-t border-green-200 dark:border-green-700 pt-3 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div>
            <span class="block text-xs text-green-700 dark:text-green-300">Se montó en la reparación</span>
            <span class="font-semibold text-gray-900 dark:text-white">
                #{{ $historial->producto_reparacion_id ?? '—' }}
            </span>
        </div>
        <div>
            <span class="block text-xs text-green-700 dark:text-green-300">Venta del equipo</span>
            @if ($historial->venta_id)
                <a href="{{ route('ventas.detalles', $historial->venta_id) }}"
                    class="font-semibold text-brand-600 dark:text-brand-400 hover:underline">
                    Venta #{{ $historial->venta_id }}
                </a>
            @else
                <span class="font-semibold text-gray-500">Venta anulada</span>
            @endif
        </div>
    </div>

    <p class="mt-3 text-xs text-green-700 dark:text-green-300">
        El stock ya se descontó al montar la pieza en la reparación; este cobro no lo volvió a mover, y su costo
        sigue cargado al costo del teléfono.
    </p>
</div>
