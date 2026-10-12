<div>
    <x-dropdown-table>
        {{-- El regalo no se anula suelto: se va con su equipo. --}}
        @if ($row->esRegalo())
            <span class="block px-4 py-2 text-xs text-gray-500">Regalo: se anula con su equipo</span>
        @endif
        @can('venta.detalle.delete')
            @if (!$row->esRegalo())
            <button wire:click="openVentaDetalleDestroyModal({{ $row->id }})"
                class="block px-4 py-2 text-sm text-red-700 hover:bg-gray-100 w-full text-left">
                Anular línea
            </button>
            @endif
        @endcan
    </x-dropdown-table>
</div>
