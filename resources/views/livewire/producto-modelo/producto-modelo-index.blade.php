<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Modelos de Productos</h2>
    @can('producto-modelo.create')
        <x-primary-button wire:click="openProductoModeloCreateModal()">
            Crear Modelo
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('producto-modelo.producto-modelo-table')
    </div>
    @livewire('producto-modelo.modals.producto-modelo-create-modal')
    @livewire('producto-modelo.modals.producto-modelo-edit-modal')
    @livewire('producto-modelo.modals.producto-modelo-destroy-modal')
    @livewire('producto-modelo.modals.producto-modelo-almacenamiento-modal')
</div>
