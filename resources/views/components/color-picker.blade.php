@props([
    'nameModel',
    'hexModel',
    'nombre' => null,
    'hex' => null,
    'palette' => null,
])

@php
    $palette = $palette ?? \App\Enums\RepuestoColor::palette();
@endphp

{{--
    Selector de color: paleta fija de swatches + hex libre.

    El estado se maneja en Alpine y se empuja a Livewire con
    $wire.set(prop, valor, false). El tercer argumento `false` hace el set solo
    en cliente, SIN round-trip: asi el swatch responde al instante y encaja con
    el wire:model diferido (sin .live) que usan estos modales.

    Los colores van con style inline obligatoriamente: tailwind.config.js no
    tiene safelist, asi que una clase dinamica nunca se generaria.
--}}
<div x-data="{
        nombre: @js($nombre),
        hex: @js($hex ?: '#000000'),
        activo: @js((bool) $hex),
        aplicar(n, h) {
            this.nombre = n; this.hex = h; this.activo = true;
            $wire.set(@js($nameModel), n, false);
            $wire.set(@js($hexModel), h, false);
        },
        aplicarHex(h) {
            this.hex = h; this.activo = true;
            $wire.set(@js($hexModel), h, false);
            if (! this.nombre) { this.nombre = h; $wire.set(@js($nameModel), h, false); }
        },
        limpiar() {
            this.nombre = null; this.hex = '#000000'; this.activo = false;
            $wire.set(@js($nameModel), null, false);
            $wire.set(@js($hexModel), null, false);
        }
     }">

    <div class="flex flex-wrap gap-2">
        @foreach ($palette as $pNombre => $pHex)
            <button type="button" title="{{ $pNombre }}"
                x-on:click="aplicar(@js($pNombre), @js($pHex))"
                class="w-7 h-7 rounded-full border border-gray-300 dark:border-gray-600 transition hover:scale-110 focus:outline-none"
                :class="activo && nombre === @js($pNombre) ? 'ring-2 ring-offset-2 ring-brand-500 dark:ring-offset-gray-800' : ''"
                style="background-color: {{ $pHex }};"></button>
        @endforeach

        <button type="button" x-on:click="limpiar()" title="Sin color"
            class="w-7 h-7 rounded-full border border-dashed border-gray-400 text-gray-400 text-xs leading-none flex items-center justify-center hover:bg-gray-100 dark:hover:bg-gray-700">
            &times;
        </button>
    </div>

    <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
        <div class="md:col-span-2">
            <x-label>Nombre del color:</x-label>
            <x-input type="text" class="w-full" placeholder="Ej: Negro" x-model="nombre"
                x-on:input="$wire.set(@js($nameModel), $event.target.value, false)" />
        </div>
        <div>
            <x-label>Color (hex):</x-label>
            <div class="flex items-center gap-2">
                <input type="color" class="h-10 w-14 rounded-md border border-gray-300 dark:border-gray-600 p-0"
                    x-model="hex" x-on:input="aplicarHex($event.target.value)">
                <span class="text-xs font-mono text-gray-500 dark:text-gray-400"
                    x-text="activo ? hex : 'sin color'"></span>
            </div>
        </div>
    </div>
</div>
