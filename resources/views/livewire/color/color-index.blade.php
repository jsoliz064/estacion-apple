<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-2">Colores de equipos</h2>
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
        Los colores que se ofrecen al cargar un equipo. Los de cada modelo se marcan en Modelos › Colores;
        un modelo sin colores marcados ofrece todos los activos.
    </p>
    @can('producto-color.create')
        <x-primary-button wire:click="openColorCreateModal()">
            Crear color
        </x-primary-button>
    @endcan
    <div class="mt-4">
        @livewire('color.color-table')
    </div>
    @livewire('color.modals.color-form-modal')
    @livewire('color.modals.color-destroy-modal')
</div>
