<div>
    @if ($openModal && $compra)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Finalizar compra #{{ $compra->id }}
            </x-slot>

            <x-slot name="content">
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm">
                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-300">Equipos</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $equipos }}</p>
                    </div>
                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-300">Repuestos</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $repuestos }} u.</p>
                    </div>
                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-300">Accesorios</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $accesorios }} u.</p>
                    </div>
                    <div class="p-2 rounded-lg bg-gray-50 dark:bg-gray-700">
                        <p class="text-xs text-gray-500 dark:text-gray-300">Total</p>
                        <p class="font-semibold text-gray-900 dark:text-gray-100">Bs {{ number_format((float) $compra->total, 2) }}</p>
                    </div>
                </div>

                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    Al finalizar, los repuestos y accesorios entran al stock de su sucursal y los equipos pasan a su estado
                    (Inventario, salvo los que cargaste como Fuera o Roto): desde ahí ya se pueden vender.
                </p>

                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">Pagado al recibir</h3>
                        <button type="button" wire:click="agregarPago" class="text-sm text-brand-600 hover:underline">
                            <i class="fa-solid fa-plus"></i> Agregar método
                        </button>
                    </div>
                    @if ((float) $compra->pagado > 0)
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ya hay Bs {{ number_format((float) $compra->pagado, 2) }} pagados por adelantado.</p>
                    @endif
                    @include('livewire.partials.filas-pago')
                    <x-input-error for="pagos" class="mt-1" />
                    @php($saldo = $this->saldoPrevisto())
                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                        Pagado ahora: <strong>Bs {{ number_format($this->sumaFilas(), 2) }}</strong>
                        @if ($saldo > 0)
                            · <span class="font-semibold text-amber-700 dark:text-amber-300">Queda debiendo Bs {{ number_format($saldo, 2) }}</span>
                        @elseif ($saldo < 0)
                            · <span class="font-semibold text-red-600">Se paga Bs {{ number_format(-$saldo, 2) }} de más</span>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pon 0 si no se pagó nada: todo queda en cuentas por pagar.</p>
                </div>

                <x-input-error for="detalles" class="mt-2" />
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-button class="ml-2" wire:click="finalizar" wire:loading.attr="disabled" wire:target="finalizar">
                    Finalizar compra
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
