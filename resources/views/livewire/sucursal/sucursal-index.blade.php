<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Sucursales</h2>
    @can('sucursal.create')
        <x-primary-button wire:click="openSucursalCreateModal()">
            Crear Sucursal
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('sucursal.sucursal-table')
    </div>
    @livewire('sucursal.modals.sucursal-create-modal')
    @livewire('sucursal.modals.sucursal-edit-modal')
    @livewire('sucursal.modals.sucursal-destroy-modal')
</div>
