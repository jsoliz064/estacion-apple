<div>
    <x-dropdown-table>
        @can('producto.historial.show')
            <button wire:click="openProductoHistorialModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Ver Detalle
            </button>
        @endcan
    </x-dropdown-table>
</div>
