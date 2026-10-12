<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Categorías de Accesorios</h2>
    @can('accesorio-categoria.create')
        <x-primary-button wire:click="openAccesorioCategoriaCreateModal()">
            Crear Categoría
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('accesorio-categoria.accesorio-categoria-table')
    </div>
    @livewire('accesorio-categoria.modals.accesorio-categoria-create-modal')
    @livewire('accesorio-categoria.modals.accesorio-categoria-edit-modal')
    @livewire('accesorio-categoria.modals.accesorio-categoria-destroy-modal')
</div>
