<div>
    <x-dropdown-table>
        @can('user.historial')
            <button wire:click="openUserHistorial({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Historial
            </button>
        @endcan
        @can('user.edit')
            <button wire:click="openUserEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan
        @can('user.delete')
            <button wire:click="openUserDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>