{{-- Costo y precio en USD (por defecto) o en Bs con un tipo de cambio para los
     dos (MontosEnMonedaFormTrait). Lo comparten el alta y la edicion del equipo
     y el formulario de repuestos y accesorios.
     Parametros:
       $c: los wire:model de cada campo (moneda, costo, precio, costo_bs, precio_bs, tc)
       $enBs: si esta en Bs
       $costo, $precio: los valores en USD (en Bs, los calculados)
       $etiquetaCosto: el rotulo del costo («Costo», «Costo (código)»)
       $costoBloqueado: no se edita el costo (una permuta, cuyo costo es el
         pago, o sin el permiso editar-costo)
       $precioBloqueado: no se edita el precio (sin el permiso editar-precio)
       $aviso: una nota bajo los montos (por que estan bloqueados)
     Con cualquiera de los dos bloqueado, la moneda y el TC tambien: son de los
     dos montos, y cambiarlos moveria el bloqueado.
     Los montos y el TC van con wire:model a secas (viajan al guardar o con el
     siguiente cambio) y el equivalente lo calcula el Alpine montosMoneda: con
     .live, la respuesta de cada tecla pisaba lo tecleado y el campo se trababa.
     El wire:key de cada campo es para el cambio de moneda: sin el, el morph
     reusaba el input de USD como el de Bs y quedaba enlazado al monto anterior. --}}
@php
    $costoBloqueado = $costoBloqueado ?? false;
    $precioBloqueado = $precioBloqueado ?? false;
    $monedaBloqueada = $costoBloqueado || $precioBloqueado;
    $etiquetaCosto = $etiquetaCosto ?? 'Costo';
    $aviso = $aviso ?? null;
@endphp
<div class="space-y-2" x-data="montosMoneda(@js(['campos' => $c, 'tcDia' => (float) \App\Models\TipoCambio::actual()]))">
    <div class="flex items-center justify-between gap-2">
        <x-label value="Costo y precio *" />
        <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs font-semibold dark:border-gray-600">
            <label @class(['cursor-pointer px-3 py-1', 'bg-brand-600 text-white' => !$enBs, 'text-gray-600 dark:text-gray-300' => $enBs])>
                <input type="radio" class="sr-only" value="USD" wire:model.live="{{ $c['moneda'] }}" @disabled($monedaBloqueada)> USD
            </label>
            <label @class(['cursor-pointer px-3 py-1', 'bg-brand-600 text-white' => $enBs, 'text-gray-600 dark:text-gray-300' => !$enBs])>
                <input type="radio" class="sr-only" value="BOB" wire:model.live="{{ $c['moneda'] }}" @disabled($monedaBloqueada)> Bs
            </label>
        </div>
    </div>

    @if ($enBs)
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div>
                <x-input wire:model="{{ $c['costo_bs'] }}" wire:key="monto-{{ $c['costo_bs'] }}" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full h-10" onfocus="this.select()" placeholder="0.00" :disabled="$costoBloqueado" />
                <span class="text-xs text-gray-500">{{ $etiquetaCosto }} Bs = <span x-text="usdDe('costo')">{{ \App\Support\Dinero::usd($costo) }}</span></span>
                <x-input-error for="{{ $c['costo_bs'] }}" class="mt-1" />
            </div>
            <div>
                <x-input wire:model="{{ $c['precio_bs'] }}" wire:key="monto-{{ $c['precio_bs'] }}" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full h-10" onfocus="this.select()" placeholder="0.00" :disabled="$precioBloqueado" />
                <span class="text-xs text-gray-500">Precio Bs = <span x-text="usdDe('precio')">{{ \App\Support\Dinero::usd($precio) }}</span></span>
                <x-input-error for="{{ $c['precio_bs'] }}" class="mt-1" />
            </div>
            <div>
                <x-input wire:model="{{ $c['tc'] }}" wire:key="monto-{{ $c['tc'] }}" type="number" step="0.0001" min="0" inputmode="decimal"
                    class="block w-full h-10" onfocus="this.select()" placeholder="T/C" :disabled="$monedaBloqueada" />
                <span class="text-xs text-gray-500">Tipo de cambio</span>
                <x-input-error for="{{ $c['tc'] }}" class="mt-1" />
            </div>
        </div>
    @else
        <div class="grid grid-cols-2 gap-2">
            <div>
                <x-input wire:model="{{ $c['costo'] }}" wire:key="monto-{{ $c['costo'] }}" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full h-10" onfocus="this.select()" placeholder="0.00" :disabled="$costoBloqueado" />
                <span class="text-xs text-gray-500">{{ $etiquetaCosto }} USD · <span x-text="bsDe('costo')">{{ \App\Support\Dinero::bs($costo) }}</span></span>
            </div>
            <div>
                <x-input wire:model="{{ $c['precio'] }}" wire:key="monto-{{ $c['precio'] }}" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full h-10" onfocus="this.select()" placeholder="0.00" :disabled="$precioBloqueado" />
                <span class="text-xs text-gray-500">Precio USD · <span x-text="bsDe('precio')">{{ \App\Support\Dinero::bs($precio) }}</span></span>
            </div>
        </div>
    @endif
    @if ($aviso)
        <p class="text-xs text-amber-700 dark:text-amber-300"><i class="fa-solid fa-lock mr-1"></i>{{ $aviso }}</p>
    @endif
    <x-input-error for="{{ $c['costo'] }}" class="mt-1" />
    <x-input-error for="{{ $c['precio'] }}" class="mt-1" />
</div>
