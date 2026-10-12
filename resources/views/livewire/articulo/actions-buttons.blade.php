<div>
    <x-dropdown-table>
        @can($tipo->permiso() . '.edit')
            <button wire:click="openArticuloEditModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Editar
            </button>
        @endcan

        @can($tipo->permiso() . '.transferir')
            <button wire:click="openStockTransferenciaModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Transferir
            </button>
        @endcan

        @can($tipo->permiso() . '.baja')
            <button wire:click="openStockBajaModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Dar de baja unidades
            </button>
        @endcan

        @can($tipo->permiso() . '.historial')
            <button wire:click="openArticuloHistorial({{ $row->id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Historial
            </button>
        @endcan

        @can($tipo->permiso() . '.delete')
            <button wire:click="openArticuloDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-600 hover:bg-red-100 w-full text-left">
                Eliminar
            </button>
        @endcan
    </x-dropdown-table>
</div>
