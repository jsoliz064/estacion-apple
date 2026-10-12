<div>
    <x-dropdown-table>
        @can('producto.edit')
            <button wire:click="openProductoEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan
        @if (in_array($row->estado, \App\Enums\ProductoEstado::vendidos(), true))
            @can('producto.garantia')
                <button wire:click="openProductoGarantiaModal({{ $row->id }})"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                    Garantía
                </button>
            @endcan
            @can('producto.trabajo-externo')
                <button wire:click="openProductoTrabajoExternoModal({{ $row->id }})"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                    Trabajo Externo
                </button>
            @endcan
        @endif
        @can('producto.regalos')
            <button wire:click="openProductoRegalosModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Regalos
            </button>
        @endcan
        @can('producto.historial')
            <button wire:click="openProductoHistorial({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Historial
            </button>
        @endcan
        @can('producto.baja')
            <button wire:click="openProductoBajaModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                {{ $row->dado_de_baja_at ? 'Revertir baja' : 'Dar de baja' }}
            </button>
        @endcan
        @can('producto.delete')
            <button wire:click="openProductoDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>
