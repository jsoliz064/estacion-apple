{{-- Las filas de pago (FilasDePagoFormTrait): metodo, Bs o USD con su tipo de
     cambio. Lo comparten la venta y la compra. Variables: $metodos. --}}
<div class="mt-2 space-y-2">
    @foreach ($pagos as $i => $pago)
        @php($enUsd = ($pago['moneda'] ?? 'BOB') === 'USD')
        <div class="flex flex-wrap items-center gap-2" wire:key="pago-{{ $i }}">
            <select wire:model.live="pagos.{{ $i }}.metodo_pago_id" class="block flex-1 min-w-[8rem] h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                <option value="">Método...</option>
                @foreach ($metodos as $metodo)
                    <option value="{{ $metodo->id }}">{{ $metodo->nombre }}</option>
                @endforeach
            </select>
            <select wire:model.live="pagos.{{ $i }}.moneda" class="block w-20 h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" title="Moneda">
                <option value="BOB">Bs</option>
                <option value="USD">USD</option>
            </select>
            @if ($enUsd)
                <x-input type="number" min="0" step="0.01" class="w-28 text-right" placeholder="USD"
                    wire:model.live.debounce.400ms="pagos.{{ $i }}.monto_moneda" onfocus="this.select()" />
                <span class="text-xs text-gray-500">a</span>
                <x-input type="number" min="0" step="0.0001" class="w-24 text-right" title="Tipo de cambio"
                    wire:model.live.debounce.400ms="pagos.{{ $i }}.tipo_cambio" onfocus="this.select()" />
                <span class="text-sm whitespace-nowrap">= Bs {{ number_format($this->montoBsDe($pago), 2) }}</span>
            @else
                <x-input type="number" min="0" step="0.01" class="w-36 text-right"
                    wire:model.live.debounce.400ms="pagos.{{ $i }}.monto" onfocus="this.select()" />
            @endif
            @if (count($pagos) > 1)
                <button type="button" wire:click="quitarPago({{ $i }})" class="text-red-600 hover:text-red-800 text-lg px-1" title="Quitar">&times;</button>
            @endif
        </div>
    @endforeach
</div>
@error('pagos.*.monto') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
