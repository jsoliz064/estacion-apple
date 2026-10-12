<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Clientes</h2>

    @can('cliente.create')
        <x-primary-button wire:click="openClienteCreateModal()">
            Crear Cliente
        </x-primary-button>
    @endcan

    <div class="mt-4">
        @livewire('cliente.cliente-table')
    </div>

    {{-- Los modales se declaran UNA vez, al final, y se abren por evento. --}}
    @livewire('cliente.modals.cliente-create-modal')
    @livewire('cliente.modals.cliente-edit-modal')
    @livewire('cliente.modals.cliente-destroy-modal')
</div>
