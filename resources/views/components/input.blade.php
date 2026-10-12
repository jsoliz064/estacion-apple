{{--
    `disabled` es prop (no atributo suelto) porque hay llamadas que pasan
    disabled="true" como cadena, y un atributo HTML suelto no distinguiria eso
    de disabled="false".
--}}
@props(['disabled' => false])

@php
    // Un campo de solo lectura tiene que NOTARSE. Antes salia con el mismo
    // fondo y el mismo borde que uno editable, asi que en pantallas como la
    // venta -- donde Subtotal y Total son calculados y solo Descuento se
    // escribe -- la gente intentaba escribir encima de los totales.
    // readonly comparte el aspecto pero NO se convierte en disabled: un campo
    // readonly sigue enviando su valor y se puede seleccionar para copiar.
    $deshabilitado = filter_var($disabled, FILTER_VALIDATE_BOOLEAN);
    $bloqueado = $deshabilitado || $attributes->has('readonly');

    // El fondo y el color de texto se eligen, no se acumulan: dos utilidades
    // del mismo tipo en la misma clase las resuelve el orden de la hoja de
    // estilos, no el del atributo, y el resultado seria impredecible.
    $estado = $bloqueado
        ? 'bg-gray-100 text-gray-500 border-gray-200 cursor-not-allowed dark:bg-gray-800 dark:text-gray-400 dark:border-gray-700'
        : 'border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600';
@endphp

<input {{ $deshabilitado ? 'disabled' : '' }}
    {!! $attributes->merge(['class' => 'rounded-md shadow-sm ' . $estado]) !!}>
