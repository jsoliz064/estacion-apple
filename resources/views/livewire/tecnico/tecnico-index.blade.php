<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Tecnicos</h2>
    @can('tecnico.create')
        <x-primary-button wire:click="openTecnicoCreateModal()">
            Crear Técnico
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('tecnico.tecnico-table')
    </div>
    @livewire('tecnico.modals.tecnico-create-modal')
    @livewire('tecnico.modals.tecnico-edit-modal')
    @livewire('tecnico.modals.tecnico-destroy-modal')
</div>
