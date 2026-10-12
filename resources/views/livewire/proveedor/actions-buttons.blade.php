<div>
    <x-dropdown-table>
        @can('proveedor.historial')
            <a href="{{ route('proveedores.historial', $row->id) }}"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Ver ficha
            </a>
        @endcan
        @can('proveedor.edit')
            <button wire:click="openProveedorEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan
        @can('proveedor.delete')
            <button wire:click="openProveedorDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>
