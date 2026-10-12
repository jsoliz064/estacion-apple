<div>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Reservas</h2>
        @can('reserva.create')
            <x-primary-button wire:click="nuevaReserva">Nueva reserva</x-primary-button>
        @endcan
    </div>

    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-amber-100 dark:bg-amber-900 p-5 rounded-2xl shadow border border-amber-200 dark:border-amber-700">
            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Reservas activas</h3>
            <p class="mt-1 text-2xl font-semibold text-amber-900 dark:text-amber-100">{{ (int) $resumen->cantidad }}</p>
        </div>
        <div class="bg-gray-100 dark:bg-gray-900 p-5 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Señas recibidas (activas)</h3>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">Bs {{ number_format((float) $resumen->senas, 2) }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-2">
        @livewire('reserva.reserva-table')
    </div>

    @livewire('reserva.modals.reserva-create-modal')
    @livewire('reserva.modals.reserva-cancelar-modal')
    @livewire('cliente.modals.cliente-selector-modal')
    @livewire('cliente.modals.cliente-create-modal')
</div>
