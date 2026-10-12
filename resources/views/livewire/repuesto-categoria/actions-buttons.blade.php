<div>
    <x-dropdown-table>

        @can('repuesto-categoria.edit')
            <button wire:click="openRepuestoCategoriaEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan
        @can('repuesto-categoria.delete')
            <button wire:click="openRepuestoCategoriaDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan

    </x-dropdown-table>
</div>
