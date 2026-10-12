<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Marcas de Productos</h2>
    @can('producto-marca.create')
        <x-primary-button wire:click="openProductoMarcaCreateModal()">
            Crear Marca
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('producto-marca.producto-marca-table')
    </div>
    @livewire('producto-marca.modals.producto-marca-create-modal')
    @livewire('producto-marca.modals.producto-marca-edit-modal')
    @livewire('producto-marca.modals.producto-marca-destroy-modal')
</div>
