<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Categorías de Repuestos</h2>
    @can('repuesto-categoria.create')
        <x-primary-button wire:click="openRepuestoCategoriaCreateModal()">
            Crear Categoría
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('repuesto-categoria.repuesto-categoria-table')
    </div>
    @livewire('repuesto-categoria.modals.repuesto-categoria-create-modal')
    @livewire('repuesto-categoria.modals.repuesto-categoria-edit-modal')
    @livewire('repuesto-categoria.modals.repuesto-categoria-destroy-modal')
</div>
