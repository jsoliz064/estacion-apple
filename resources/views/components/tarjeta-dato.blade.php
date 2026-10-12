{{-- Una cifra del tablero: icono y titulo arriba, la cifra debajo a todo el
     ancho. Antes iban icono y cifra lado a lado en text-2xl, y en el celular
     (dos por fila) el monto no cabia y estiraba la tarjeta.
     Clases literales por color: Tailwind no genera 'bg-' . $color. --}}
@props(['titulo', 'valor', 'icono', 'color' => 'brand'])

@php
    $fondo = match ($color) {
        'purple' => 'bg-purple-500 border-purple-600',
        default => 'bg-brand-500 border-brand-600',
    };
    $trazo = match ($color) {
        'purple' => 'text-purple-800',
        default => 'text-brand-800',
    };
@endphp

<div {{ $attributes->class([$fondo, 'dark:bg-gray-800 dark:border-gray-600 shadow rounded-md border-b-4 p-3 text-white min-w-0']) }}>
    <div class="flex items-center gap-2">
        <span class="flex shrink-0 items-center justify-center w-8 h-8 bg-white rounded-full">
            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                class="{{ $trazo }} dark:text-gray-800">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $icono }}" />
            </svg>
        </span>
        <p class="text-sm leading-tight opacity-90">{{ $titulo }}</p>
    </div>
    <p class="mt-2 text-lg sm:text-xl font-semibold leading-tight break-words">{{ $valor }}</p>
    @if ($slot->isNotEmpty())
        <div class="mt-1 text-xs leading-snug opacity-90">{{ $slot }}</div>
    @endif
</div>
