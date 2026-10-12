{{--
    La celda Descripcion de las tres tablas de historial: la frase de negocio,
    si la hay, y debajo el antes/despues de lo que cambio.

    Una fila 'editado' del observer no lleva frase -- el observer no sabe POR QUE
    cambio el precio --, asi que ahi solo se ven los cambios.
--}}
<div class="whitespace-normal">
    @if ($descripcion)
        <span>{{ $descripcion }}</span>
    @endif

    <x-bitacora-cambios :cambios="$cambios" />
</div>
