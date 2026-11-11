<div>
    <x-dropdown-table>
        {{-- El regalo no se anula suelto: se va con su equipo. --}}
        @if ($row->esRegalo())
            <span class="block px-4 py-2 text-xs text-gray-500">Regalo: se anula con su equipo</span>
        @endif
        {{-- Garantia y trabajo externo del equipo (VentaDetalleTable::openGarantia),
             como en Productos: solo vendido, y cada uno con su permiso. «(en curso)»
             si ya tiene una de ese tipo pendiente: el modal la retoma. --}}
        @if ($row->producto_id && !$row->esCobro() && in_array($row->producto?->estado, \App\Enums\ProductoEstado::vendidos(), true))
            @php($pendientes = \App\Models\ProductoReparacion::where('producto_id', $row->producto_id)->where('estado', 'Pendiente')->pluck('tipo')->all())
            @can('producto.garantia')
                <button wire:click="openGarantia({{ $row->producto_id }})"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                    Garantía{{ in_array(\App\Enums\ReparacionTipo::Garantia->value, $pendientes, true) ? ' (en curso)' : '' }}
                </button>
            @endcan
            @can('producto.trabajo-externo')
                <button wire:click="openTrabajoExterno({{ $row->producto_id }})"
                    class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                    Trabajo externo{{ in_array(\App\Enums\ReparacionTipo::Externo->value, $pendientes, true) ? ' (en curso)' : '' }}
                </button>
            @endcan
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
