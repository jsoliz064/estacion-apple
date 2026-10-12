<div>
    <x-dropdown-table>
        <button wire:click="openReparacionShow({{ $id }})"
            class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
            <span style="color: green">Ver Detalles</span>
        </button>

        @can('tecnico.producto.edit')
            <button wire:click="openReparacionEdit({{ $id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                <span style="color: orange">Editar</span>
            </button>
        @endcan
    </x-dropdown-table>
</div>
