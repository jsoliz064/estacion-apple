<div class="flex items-center space-x-1 relative overflow-visible">
    @can('compra.detalle')
        <button wire:click="verCompra({{ $row->id }})"
            class="p-1 text-brand-600 hover:text-brand-900 hover:bg-brand-50 rounded-md" title="Ver detalle y cargar equipos">
            <img src="{{ asset('icons/add.svg') }}" class="h-5 w-5" alt="Ver detalle">
        </button>
    @endcan
    <x-dropdown-table>
        @can('compra.edit')
            <button wire:click="editarCompra({{ $row->id }})"
                class="flex items-center w-full px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem">
                <img src="{{ asset('icons/edit.svg') }}" class="h-5 w-5 mr-2 text-gray-400" alt="Editar">
                Editar
            </button>
        @endcan
        @can('compra.delete')
            <button wire:click="openCompraDestroyModal({{ $row->id }})"
                class="flex items-center w-full px-4 py-2 text-sm text-red-600 hover:bg-gray-100 hover:text-red-800" role="menuitem">
                <img src="{{ asset('icons/remove.svg') }}" class="h-5 w-5 mr-2 text-red-400" alt="Eliminar">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>
