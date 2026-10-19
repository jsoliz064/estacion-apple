<div>
    <x-dropdown-table>

        @can('rol.edit')
            <a href="{{ route('roles.editar', $row->id) }}"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </a>
        @endcan
        @can('rol.delete')
            <button wire:click="openRoleDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>
