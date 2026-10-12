<div>
    @if ($openModal && $linea)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Anular línea de la venta #{{ $linea->venta_id }}</x-slot>

            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-800 dark:text-gray-200">
                    {!! \App\Enums\LineaTipo::badge($linea->tipo) !!} {{ $linea->descripcion() }}
                    · {{ $linea->cantidad }} × Bs {{ number_format((float) $linea->precio, 2) }}
                </p>
                <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                    @if ($linea->producto_id)
                        El equipo vuelve al inventario y se descobran sus repuestos de taller. Sus regalos se quedan con él.
                    @elseif ($linea->esCobro())
                        Se anula el cobro. La pieza sigue montada en el equipo: no vuelve al stock.
                    @else
                        Las unidades vuelven al stock de la sucursal de la venta.
                    @endif
                    Si es la última línea, la venta se anula.
                </p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-danger-button class="ml-2" wire:click="eliminarDetalle()" wire:loading.attr="disabled">Anular línea</x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
