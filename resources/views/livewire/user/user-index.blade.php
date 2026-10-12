<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Usuarios</h2>
    @can('user.create')
        <x-primary-button wire:click="openUserCreateModal()">
            Crear Usuario
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('user.user-table')
    </div>
    @livewire('user.modals.user-create-modal')
    @livewire('user.modals.user-edit-modal')
    @livewire('user.modals.user-destroy-modal')
</div>
