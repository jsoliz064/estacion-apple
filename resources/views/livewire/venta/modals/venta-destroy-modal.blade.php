<div>
    @if ($openModal && $venta)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Anular venta #{{ $venta->id }}</x-slot>
            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    Se anulan sus {{ $venta->detalles_count }} línea(s) por Bs {{ number_format((float) $venta->total, 2) }}:
                    los equipos vuelven al inventario, los repuestos y accesorios a su stock, y los cobros de taller se descobran.
                    @if ($venta->regalos_count > 0)
                        Los regalos de los equipos se quedan con su equipo.
                    @endif
                </p>
                @if ((float) $venta->pagado > 0)
                    <p class="m-2 text-sm font-semibold text-red-700 dark:text-red-300">
                        También se anulan sus pagos por Bs {{ number_format((float) $venta->pagado, 2) }}: se entiende que el dinero se devuelve.
                    </p>
                @endif
                {{-- docs/05: una comision pagada no se revierte sola. --}}
                @if ($venta->comision?->liquidacion_id)
                    <p class="m-2 text-sm font-semibold text-amber-700 dark:text-amber-300">
                        La comisión de {{ $venta->comision->user?->name ?? 'su vendedor' }} (Bs {{ number_format((float) $venta->comision->monto, 2) }})
                        ya se pagó en la liquidación #{{ $venta->comision->liquidacion_id }}: queda registrada como pagada y hay que ajustarla a mano.
                    </p>
                @endif
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-danger-button class="ml-2" wire:click="anular()" wire:loading.attr="disabled">Anular venta</x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
