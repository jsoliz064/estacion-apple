<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Roles</h2>
    @can('rol.create')
        <x-primary-button wire:click="openRoleCreateModal()">
            Crear Rol
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('role.role-table')
    </div>
    @livewire('role.modals.role-create-modal')
    @livewire('role.modals.role-edit-modal')
    @livewire('role.modals.role-destroy-modal')
</div>
