<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Compras</h2>
    @can('compra.create')
        <a href="{{ route('compras.crear') }}">
            <x-primary-button>Registrar compra</x-primary-button>
        </a>
    @endcan
    <div class="mt-4">
        @livewire('compra.compra-table')
    </div>
    @livewire('compra.modals.compra-destroy-modal')
</div>
