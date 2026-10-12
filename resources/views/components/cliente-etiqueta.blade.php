@props(['nombre', 'ci' => null, 'telefono' => null])

{{-- El cliente con lo que se sepa de él, uniendo solo las partes que existen.

     Calcado de x-articulo-etiqueta y por el mismo motivo: con separadores
     literales, un cliente del que solo se tiene el nombre salía como
     «Daniel ·  · », y un «Sin CI» inventado es peor que el hueco. Solo el nombre
     es obligatorio en la ficha; el CI y el teléfono casi nunca están. --}}
@php
    $partes = array_values(array_filter(
        [$nombre, $ci, $telefono],
        fn($parte) => filled($parte),
    ));
@endphp
<span {{ $attributes }}>{{ implode(' · ', $partes) }}</span>
