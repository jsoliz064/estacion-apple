<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Proveedores</h2>
    @can('proveedor.create')
        <x-primary-button wire:click="openProveedorCreateModal()">
            Crear Proveedor
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('proveedor.proveedor-table')
    </div>
    @livewire('proveedor.modals.proveedor-create-modal')
    @livewire('proveedor.modals.proveedor-edit-modal')
    @livewire('proveedor.modals.proveedor-destroy-modal')
</div>
