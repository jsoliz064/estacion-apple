<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-1">Mis comisiones</h2>
    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        {{ auth()->user()->name }}: {{ rtrim(rtrim(number_format((float) auth()->user()->comision_porcentaje, 2), '0'), '.') }} % de la ganancia de tus ventas
        @if ($tecnico)
            y {{ rtrim(rtrim(number_format((float) $tecnico->comision_porcentaje, 2), '0'), '.') }} % de la mano de obra de tus reparaciones como técnico ({{ $tecnico->nombre }})
        @endif.
        La comisión de una venta se gana cuando queda cobrada entera; la de una reparación, al terminarla.
    </p>

    <x-comision-cifras :cifras="$cifras" class="mb-6" />

    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-2">
        @livewire('comision.comision-table', ['usuarioId' => auth()->id()])
    </div>

    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Mis liquidaciones</h3>
        @include('livewire.comision.partials.liquidaciones', ['liquidaciones' => $liquidaciones])
    </div>

    @livewire('comision.modals.liquidacion-ver-modal')
</div>
