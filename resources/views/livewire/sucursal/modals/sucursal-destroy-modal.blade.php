<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar sucursal: {{ $sucursal->nombre }}
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input value="{{ $sucursal->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

                {{-- El aviso ANTES de pulsar. Las FK hacia sucursales son casi
                     todas nullOnDelete: borrar no fallaria, dejaria las ventas sin
                     sucursal en silencio. --}}
                @if ($movimientos > 0)
                    <div
                        class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">
                            Esta sucursal tiene {{ $movimientos }} movimiento(s): productos, ventas, compras o stock.
                        </p>
                        <p class="mt-1">
                            No se puede eliminar sin dejar esos registros sin sucursal.
                            @if ($sucursal->activa)
                                Puedes desactivarla: dejará de ofrecerse al cargar productos y ventas, y sus ventas viejas seguirán en los reportes.
                            @else
                                Ya está desactivada.
                            @endif
                        </p>
                    </div>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                        No tiene ningún movimiento registrado, así que se puede eliminar sin consecuencias.
                    </p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($movimientos > 0)
                    @if ($sucursal->activa)
                        <x-danger-button class="ml-2" wire:click="desactivar()" wire:loading.attr="disabled">
                            Desactivar
                        </x-danger-button>
                    @endif
                @else
                    <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">
                        Eliminar
                    </x-danger-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
