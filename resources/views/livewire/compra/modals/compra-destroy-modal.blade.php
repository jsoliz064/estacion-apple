<div>
    @if ($openModal && $compra)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar compra #{{ $compra->id }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    Proveedor: <strong>{{ $compra->proveedor?->nombre }}</strong> · Fecha {{ $compra->fecha->format('d/m/Y') }}
                    · Total Bs {{ number_format((float) $compra->total, 2) }}
                </p>

                @if ($borrador)
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                        Es un borrador: se borra con todo lo cargado{{ $equipos > 0 ? ", incluidos sus {$equipos} equipo(s)" : '' }}. Su stock nunca entró, así que no se mueve nada.
                    </p>
                    <x-input-error for="detalles" class="m-2" />
                @elseif ($equipos > 0)
                    <div class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        La compra tiene {{ $equipos }} equipo(s). Quítalos primero desde el detalle de la compra.
                    </div>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                        Sus repuestos y accesorios saldrán del stock. Si alguno ya se vendió, la compra no se podrá eliminar.
                    </p>
                    <x-input-error for="detalles" class="m-2" />
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($borrador || $equipos === 0)
                    <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">
                        Eliminar
                    </x-danger-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
