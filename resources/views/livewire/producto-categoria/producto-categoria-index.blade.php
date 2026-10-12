<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Categorías de Productos</h2>
    @can('producto-categoria.create')
        <x-primary-button wire:click="openProductoCategoriaCreateModal()">
            Crear Categoría
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('producto-categoria.producto-categoria-table')
    </div>
    @livewire('producto-categoria.modals.producto-categoria-create-modal')
    @livewire('producto-categoria.modals.producto-categoria-edit-modal')
    @livewire('producto-categoria.modals.producto-categoria-destroy-modal')
</div>
