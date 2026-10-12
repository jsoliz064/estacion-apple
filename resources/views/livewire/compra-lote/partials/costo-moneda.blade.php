{{-- El costo del equipo en Bs o en USD con su tipo de cambio (alta y edicion).
     En USD el costo en Bs se muestra calculado: es el que leen la compra, la
     venta y los reportes (Producto lo vuelve a calcular al guardar).
     Parametros: los wire:model de cada campo ($mMoneda, $mUsd, $mTc, $mBs), la
     moneda actual ($moneda), el costo en Bs ($costoBs) y $bloqueado (permuta). --}}
@php $enUsd = $moneda === \App\Enums\Moneda::USD->value; @endphp
<div>
    <div class="flex items-center justify-between gap-2">
        <x-label value="Costo *" />
        <div class="inline-flex overflow-hidden rounded-md border border-gray-300 text-xs font-semibold dark:border-gray-600">
            <label @class(['cursor-pointer px-3 py-1', 'bg-brand-600 text-white' => !$enUsd, 'text-gray-600 dark:text-gray-300' => $enUsd])>
                <input type="radio" class="sr-only" value="BOB" wire:model.live="{{ $mMoneda }}" @disabled($bloqueado)> Bs
            </label>
            <label @class(['cursor-pointer px-3 py-1', 'bg-brand-600 text-white' => $enUsd, 'text-gray-600 dark:text-gray-300' => !$enUsd])>
                <input type="radio" class="sr-only" value="USD" wire:model.live="{{ $mMoneda }}" @disabled($bloqueado)> USD
            </label>
        </div>
    </div>

    @if ($enUsd)
        <div class="mt-2 grid grid-cols-2 gap-2">
            <div>
                <x-input wire:model.live.debounce.400ms="{{ $mUsd }}" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full h-10" onfocus="this.select()" placeholder="USD" :disabled="$bloqueado" />
                <span class="text-xs text-gray-500">USD</span>
            </div>
            <div>
                <x-input wire:model.live.debounce.400ms="{{ $mTc }}" type="number" step="0.01" min="0" inputmode="decimal"
                    class="block w-full h-10" onfocus="this.select()" placeholder="T/C" :disabled="$bloqueado" />
                <span class="text-xs text-gray-500">Tipo de cambio</span>
            </div>
        </div>
        <p class="mt-1 text-sm text-gray-700 dark:text-gray-200">= Bs {{ number_format((float) $costoBs, 2) }}</p>
        <x-input-error for="{{ $mUsd }}" class="mt-1" />
        <x-input-error for="{{ $mTc }}" class="mt-1" />
    @else
        <x-input wire:model.lazy="{{ $mBs }}" type="number" step="0.01" min="0" inputmode="decimal"
            class="mt-2 block w-full h-10" onfocus="this.select()" placeholder="0.00 Bs" :disabled="$bloqueado" />
    @endif
    <x-input-error for="{{ $mBs }}" class="mt-1" />
</div>
