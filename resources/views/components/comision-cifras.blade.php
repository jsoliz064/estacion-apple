{{--
    Las tres cifras de comisiones de docs/05: pendiente, por pagar y pagado.
    Recibe el arreglo de Comision::cifras(). Lo usan la pantalla de comisiones,
    «Mis comisiones» y las fichas del usuario y del técnico.

    Compacto a proposito: en el celular las tres tarjetas grandes llenaban el
    primer pantallazo y la tabla quedaba abajo. Van en fila tambien en el
    celular, sin la explicacion (que solo se ve desde sm). El $slot va primero
    dentro de la grilla, para sumar una tarjeta propia de la pantalla (la del
    tecnico agrega sus reparaciones pendientes) con `columnas` a juego.
--}}
@props(['cifras', 'columnas' => 'grid-cols-3'])

@php
    $bs = fn($v) => number_format((float) $v, 2, ',', '.');
    $tarjeta = 'border-l-4 px-2 py-1.5 sm:p-3 rounded-lg shadow-sm dark:bg-gray-800 min-w-0';
    $etiqueta = 'text-[11px] sm:text-sm font-medium truncate';
    $valor = 'text-sm sm:text-xl font-bold truncate';
    $nota = 'hidden sm:block text-xs truncate';
@endphp

<div {{ $attributes->merge(['class' => 'grid gap-2 sm:gap-3 ' . $columnas]) }}>
    {{ $slot }}
    <div class="{{ $tarjeta }} bg-amber-50 border-amber-500">
        <p class="{{ $etiqueta }} text-amber-900 dark:text-amber-200">Pendiente</p>
        <p class="{{ $valor }} text-amber-800 dark:text-amber-100"><span class="text-xs sm:text-base font-semibold">Bs</span> {{ $bs($cifras['pendiente'] ?? 0) }}</p>
        <p class="{{ $nota }} text-amber-700 dark:text-amber-300">Sin cobrar entera o sin terminar</p>
    </div>
    <div class="{{ $tarjeta }} bg-blue-50 border-blue-500">
        <p class="{{ $etiqueta }} text-blue-900 dark:text-blue-200">Por pagar</p>
        <p class="{{ $valor }} text-blue-800 dark:text-blue-100"><span class="text-xs sm:text-base font-semibold">Bs</span> {{ $bs($cifras['por_pagar'] ?? 0) }}</p>
        <p class="{{ $nota }} text-blue-700 dark:text-blue-300">Ganado, esperando liquidación</p>
    </div>
    <div class="{{ $tarjeta }} bg-green-50 border-green-500">
        <p class="{{ $etiqueta }} text-green-900 dark:text-green-200">Pagado</p>
        <p class="{{ $valor }} text-green-800 dark:text-green-100"><span class="text-xs sm:text-base font-semibold">Bs</span> {{ $bs($cifras['pagado'] ?? 0) }}</p>
        <p class="{{ $nota }} text-green-700 dark:text-green-300">Lo que ya se entregó</p>
    </div>
</div>
