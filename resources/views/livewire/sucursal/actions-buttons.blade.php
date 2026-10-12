<div>
    <x-dropdown-table>
        @can('sucursal.edit')
            <button wire:click="openSucursalEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan
        {{-- El Almacen es obligatorio: el codigo lo busca por nombre. --}}
        @if ($row->nombre !== \App\Models\Sucursal::ALMACEN)
            @can('sucursal.delete')
                <button wire:click="openSucursalDestroyModal({{ $row->id }})"
                    class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                    Eliminar
                </button>
            @endcan
        @endif
    </x-dropdown-table>
</div>
