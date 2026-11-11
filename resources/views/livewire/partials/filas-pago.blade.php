{{-- Las filas de pago (FilasDePagoFormTrait): metodo, USD (la base) o Bs con
     su tipo de cambio. Lo comparten la venta y la compra. Variables: $metodos.

     Dos cosas que trababan el campo en Bs:
     - wire:key en cada input, con la moneda: sin el, el morph reusaba el input
       de USD como el de Bs, que seguia enlazado a `monto` (mostraba los 1000
       USD y lo tecleado iba al USD).
     - En Bs, el USD de la fila (`monto` = Bs / TC) se calcula tambien aqui, al
       teclear, y viaja con la peticion. Si solo lo calculaba el servidor, la
       respuesta traia `pagos` cambiado y Livewire reemplazaba el arreglo
       entero, pisando lo que se seguia tecleando. --}}
<div class="mt-2 space-y-2">
    @foreach ($pagos as $i => $pago)
        @php($enBs = ($pago['moneda'] ?? 'USD') === \App\Enums\Moneda::alterna()->value)
        <div class="flex flex-wrap items-center gap-2" wire:key="pago-{{ $i }}">
            <select wire:model.live="pagos.{{ $i }}.metodo_pago_id" class="block flex-1 min-w-[8rem] h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                <option value="">Método...</option>
                @foreach ($metodos as $metodo)
                    <option value="{{ $metodo->id }}">{{ $metodo->nombre }}</option>
                @endforeach
            </select>
            <select wire:model.live="pagos.{{ $i }}.moneda" class="block w-24 shrink-0 h-10 pl-3 pr-8 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm" title="Moneda">
                <option value="USD">USD</option>
                <option value="BOB">Bs</option>
            </select>
            @if ($enBs)
                <x-input type="number" min="0" step="0.01" inputmode="decimal" class="w-28 text-right" placeholder="Bs"
                    wire:model.live.debounce.400ms="pagos.{{ $i }}.monto_moneda" wire:key="pago-{{ $i }}-bs" onfocus="this.select()"
                    x-on:input="$wire.$set('pagos.{{ $i }}.monto', usdDePago($event.target.value, $wire.$get('pagos.{{ $i }}.tipo_cambio')), false)" />
                <span class="text-xs text-gray-500">a</span>
                <x-input type="number" min="0" step="0.0001" inputmode="decimal" class="w-24 text-right" title="Tipo de cambio"
                    wire:model.live.debounce.400ms="pagos.{{ $i }}.tipo_cambio" wire:key="pago-{{ $i }}-tc" onfocus="this.select()"
                    x-on:input="$wire.$set('pagos.{{ $i }}.monto', usdDePago($wire.$get('pagos.{{ $i }}.monto_moneda'), $event.target.value), false)" />
                <span class="text-sm whitespace-nowrap text-gray-700 dark:text-gray-200" x-text="'= USD ' + usdDePago($wire.$get('pagos.{{ $i }}.monto_moneda'), $wire.$get('pagos.{{ $i }}.tipo_cambio')).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })">= {{ \App\Support\Dinero::usd($this->montoBaseDe($pago)) }}</span>
            @else
                <x-input type="number" min="0" step="0.01" inputmode="decimal" class="w-36 text-right" placeholder="USD"
                    wire:model.live.debounce.400ms="pagos.{{ $i }}.monto" wire:key="pago-{{ $i }}-usd" onfocus="this.select()" />
            @endif
            @if (count($pagos) > 1)
                <button type="button" wire:click="quitarPago({{ $i }})" class="text-red-600 hover:text-red-800 text-lg px-1" title="Quitar">&times;</button>
            @endif
        </div>
    @endforeach
</div>
@error('pagos.*.monto') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
