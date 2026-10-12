<div>
    @if ($openModal && $pago)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Anular pago al proveedor</x-slot>
            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    Se anula el pago de <strong>Bs {{ number_format((float) $pago->monto, 2) }}</strong>
                    ({{ $pago->descripcion() }}) del {{ $pago->fecha->format('d/m/Y H:i') }}
                    a {{ $pago->compra?->proveedor?->nombre }}, compra #{{ $pago->compra_id }}.
                </p>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">La compra vuelve a tener ese saldo. Queda registrado en su bitácora.</p>
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-danger-button class="ml-2" wire:click="anular()" wire:loading.attr="disabled">Anular pago</x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
