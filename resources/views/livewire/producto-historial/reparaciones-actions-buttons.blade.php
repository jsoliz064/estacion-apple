<div>
    <x-dropdown-table>
        {{-- El mismo permiso que el "Ver Detalle" de la pestaña Todo
             (producto-historial/actions-buttons.blade.php): producto.historial
             es el gate de la PANTALLA, no del detalle de una fila. Usar ese otro
             daría botón de detalle acá a roles que no lo tienen allá. --}}
        @can('producto.historial.show')
            <button wire:click="verReparacion({{ $id }})"
                class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                Ver Detalle
            </button>
        @endcan
    </x-dropdown-table>
</div>
