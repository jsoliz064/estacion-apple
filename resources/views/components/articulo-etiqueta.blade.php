@props(['nombre', 'fabricante' => null, 'modelo' => null])

{{-- El nombre de un artículo del catálogo con lo que se sepa de él, uniendo solo
     las partes que existen.

     Los cuatro blades de compra y venta pintaban
     «{{ nombre }} - {{ fabricante }} - {{ modelo }}» con los guiones LITERALES,
     así que un accesorio —que no tiene fabricante ni modelo por diseño, ver
     RepuestoAccesorioTrait— salía como «Funda iPhone 15 -  - Sin modelo»: dos
     guiones huérfanos y un modelo inventado.

     Mismo criterio que x-reparacion-detalle, que tampoco pinta el paréntesis
     cuando no hay nada dentro. --}}
@php
    $partes = array_values(array_filter(
        [$nombre, $fabricante, $modelo],
        fn($parte) => filled($parte),
    ));
@endphp
<span {{ $attributes }}>{{ implode(' · ', $partes) }}</span>
