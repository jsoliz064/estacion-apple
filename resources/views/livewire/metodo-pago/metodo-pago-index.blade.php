<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Métodos de pago</h2>
    @can('metodo-pago.create')
        <x-primary-button wire:click="openMetodoPagoCreateModal()">
            Crear método
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('metodo-pago.metodo-pago-table')
    </div>
    @livewire('metodo-pago.modals.metodo-pago-form-modal')
    @livewire('metodo-pago.modals.metodo-pago-destroy-modal')
</div>
