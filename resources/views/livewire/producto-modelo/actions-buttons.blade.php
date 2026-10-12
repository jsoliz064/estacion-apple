<div>
    <x-dropdown-table>

        @can('producto-modelo.edit')
            <button wire:click="openProductoModeloEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan

        @can('producto-modelo.almacenamiento')
            <button wire:click="openProductoModeloAlmacenamientoModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Almacenamientos
            </button>
        @endcan

        @can('producto-modelo.delete')
            <button wire:click="openProductoModeloDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan

    </x-dropdown-table>
</div>
