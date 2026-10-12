<div>
    <x-dropdown-table>
        {{-- <button wire:click="openProductoEditModal({{ $row->id }})"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
            Agregar a Venta Rápida
        </button> --}}
        @can('producto.edit')
            <button wire:click="openCompraLoteProductoEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan
        {{-- Fallado de fabrica: se reclama al proveedor (ReclamoService). --}}
        @if (in_array($row->estado, ['Inventario', 'Roto'], true) && !$row->dado_de_baja_at)
            @can('compra.reclamo')
                <button wire:click="openReclamoAbrirModal({{ $row->id }})"
                    class="block px-4 py-2 text-sm text-rose-700 hover:bg-rose-50 w-full text-left">
                    Reclamar al proveedor
                </button>
            @endcan
        @endif
        @can('producto.delete')
            <button wire:click="openCompraLoteProductoDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan

    </x-dropdown-table>
</div>
