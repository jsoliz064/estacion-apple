<div>
    @if ($openModal && $pago)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Anular pago</x-slot>
            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    Se anula el pago de <strong>Bs {{ number_format((float) $pago->monto, 2) }}</strong>
                    en {{ $pago->metodo?->nombre }} del {{ $pago->fecha->format('d/m/Y H:i') }}
                    (venta #{{ $pago->venta_id }}{{ $pago->user ? ', recibido por ' . $pago->user->name : '' }}).
                </p>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    La venta vuelve a tener ese saldo: si estaba pagada, queda a crédito y sus equipos pasan a «Venta a crédito».
                    Queda registrado en la bitácora de la venta.
                </p>
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-danger-button class="ml-2" wire:click="anular()" wire:loading.attr="disabled">Anular pago</x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
