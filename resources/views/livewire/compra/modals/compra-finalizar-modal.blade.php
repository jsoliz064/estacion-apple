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
                        <p class="font-semibold text-gray-900 dark:text-gray-100">{{ \App\Support\Dinero::usd((float) $compra->total) }}</p>
                    </div>
                </div>

                <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    Al finalizar, los repuestos y accesorios entran al stock de su sucursal y los equipos pasan a su estado
                    (Inventario, salvo los que cargaste como Fuera o Roto): desde ahí ya se pueden vender.
                </p>

                @if ($pagada)
                    <div class="mt-4 flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-3 text-sm text-green-900 dark:border-green-800 dark:bg-green-900/30 dark:text-green-100">
                        <i class="fa-solid fa-circle-check mt-0.5 text-green-600 dark:text-green-300"></i>
                        <p>
                            <span class="font-semibold">Pagada por adelantado:</span>
                            {{ \App\Support\Dinero::usd((float) $compra->pagado) }}. No queda nada por pagar al proveedor.
                        </p>
                    </div>
                @else
                <div class="mt-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-100">Pagado al recibir</h3>
                        <button type="button" wire:click="agregarPago" class="text-sm text-brand-600 hover:underline">
                            <i class="fa-solid fa-plus"></i> Agregar método
                        </button>
                    </div>
                    @if ((float) $compra->pagado > 0)
                        <p class="text-xs text-gray-500 dark:text-gray-400">Ya hay {{ \App\Support\Dinero::usd((float) $compra->pagado) }} pagados por adelantado.</p>
                    @endif
                    @include('livewire.partials.filas-pago')
                    <x-input-error for="pagos" class="mt-1" />
                    @php($saldo = $this->saldoPrevisto())
                    <p class="mt-3 text-sm text-gray-700 dark:text-gray-200">
                        Pagado ahora: <strong>{{ \App\Support\Dinero::usd($this->sumaFilas()) }}</strong>
                        @if ($saldo > 0)
                            · <span class="font-semibold text-amber-700 dark:text-amber-300">Queda debiendo {{ \App\Support\Dinero::usd($saldo) }}</span>
                        @elseif ($saldo < 0)
                            · <span class="font-semibold text-red-600">Se paga {{ \App\Support\Dinero::usd(-$saldo) }} de más</span>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pon 0 si no se pagó nada: todo queda en cuentas por pagar.</p>
                </div>
                @endif

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
