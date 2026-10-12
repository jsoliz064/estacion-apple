{{-- Abre el lector por camara (components/escaner-overlay) sobre el campo de
     al lado. Va dentro de un contenedor con data-escaner junto a su <input>:
     asi no hacen falta ids unicos.

     modo     'enter' (el campo tiene su Enter, que lee $el.value) o 'input'
              (un wire:model comun: ficha, busqueda de tabla).
     continuo true donde lo normal es leer muchos seguidos (compra, transferencia). --}}
@props(['modo' => 'enter', 'continuo' => false])

<button type="button" title="Escanear con la cámara" aria-label="Escanear con la cámara" x-data
    x-on:click="$dispatch('abrir-escaner', {
        destino: $el.closest('[data-escaner]')?.querySelector('input'),
        modo: @js($modo),
        continuo: @js((bool) $continuo),
    })"
    {{ $attributes->merge(['class' => 'inline-flex items-center justify-center px-3 rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 disabled:cursor-not-allowed']) }}>
    <i class="fa-solid fa-camera"></i>
</button>
