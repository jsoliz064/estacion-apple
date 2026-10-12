<div>
    @if ($openModal && $proveedor)
        <x-dialog-modal wire:model="openModal" maxWidth="2xl">
            <x-slot name="title">Pago a {{ $proveedor->nombre }}</x-slot>

            <x-slot name="content">
                <hr>
                @if ($compras->isEmpty())
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">No se le debe nada a este proveedor.</p>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                        Se le debe: <strong>Bs {{ number_format($compras->sum(fn($c) => $c->saldoPendiente()), 2) }}</strong>
                    </p>

                    <div class="m-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <x-label value="Monto pagado" />
                            <div class="mt-1 flex gap-2">
                                <select wire:model.live="moneda"
                                    class="block w-20 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                                    <option value="BOB">Bs</option>
                                    <option value="USD">USD</option>
                                </select>
                                <x-input type="number" min="0" step="0.01" class="w-full" wire:model.live.debounce.400ms="montoRecibido"
                                    onfocus="this.select()" placeholder="Se reparte de la más antigua a la más nueva" />
                            </div>
                            @if ($moneda === 'USD')
                                <div class="mt-2 flex items-center gap-2 text-sm">
                                    <span>Tipo de cambio</span>
                                    <x-input type="number" min="0" step="0.0001" class="w-28" wire:model.live.debounce.400ms="tipo_cambio" />
                                    <span>= Bs {{ number_format($this->recibidoBs(), 2) }}</span>
                                </div>
                                <x-input-error for="tipo_cambio" class="mt-1" />
                            @endif
                        </div>
                        <div>
                            <x-label value="Método" />
                            <select wire:model="metodo_pago_id"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                                <option value="">Método...</option>
                                @foreach ($metodos as $metodo)
                                    <option value="{{ $metodo->id }}">{{ $metodo->nombre }}</option>
                                @endforeach
                            </select>
                            <x-input-error for="metodo_pago_id" class="mt-1" />
                        </div>
                    </div>

                    <div class="m-2 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                <tr>
                                    <th class="p-2 text-left">Compra</th>
                                    <th class="p-2 text-right">Saldo</th>
                                    <th class="p-2 text-right w-36">Paga Bs</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($compras as $venta)
                                    <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="pago-compra-{{ $venta->id }}">
                                        <td class="p-2">
                                            #{{ $venta->id }}
                                            <span class="block text-xs text-gray-500">{{ $venta->fecha?->format('d/m/Y') }} · total Bs {{ number_format((float) $venta->total, 2) }}</span>
                                        </td>
                                        <td class="p-2 text-right whitespace-nowrap">{{ number_format($venta->saldoPendiente(), 2) }}</td>
                                        <td class="p-2">
                                            <x-input type="number" min="0" step="0.01" max="{{ $venta->saldoPendiente() }}" class="w-full text-right"
                                                wire:model.live.debounce.400ms="montos.{{ $venta->id }}" onfocus="this.select()" />
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @error('montos.*') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
                        <x-input-error for="montos" class="mt-1" />
                        <x-input-error for="pagos" class="mt-1" />
                    </div>

                    <div class="m-2">
                        <x-label value="Nota (opcional)" />
                        <x-input type="text" class="mt-1 w-full" wire:model="nota" maxlength="255" />
                    </div>

                    <p class="m-2 text-right text-lg font-semibold text-gray-800 dark:text-gray-100">
                        A pagar: Bs {{ number_format($this->totalACobrar(), 2) }}
                    </p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                @if ($compras->isNotEmpty())
                    <x-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled" wire:target="guardar">Registrar pago</x-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
