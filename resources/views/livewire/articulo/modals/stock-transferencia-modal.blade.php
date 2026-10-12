<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Transferir stock
            </x-slot>

            <x-slot name="content">
                <hr>
                @if (!$articulo)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">El artículo ya no existe.</p>
                @else
                    <div class="mt-4">
                        <x-label>Artículo</x-label>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $articulo->etiqueta() }}</p>
                    </div>

                    @include('livewire.articulo.modals.partials.reparto', ['reparto' => $reparto, 'total' => $articulo->cantidad])

                    <div class="mt-4">
                        <x-label>Desde (origen)</x-label>
                        <select wire:model="sucursal1_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                            <option value="">Seleccione una sucursal...</option>
                            @foreach ($origenes as $fila)
                                <option value="{{ $fila->sucursal_id }}">
                                    {{ $fila->sucursal?->nombre ?? 'Sin sucursal' }} ({{ (int) $fila->cantidad }})
                                </option>
                            @endforeach
                        </select>
                        @if ($origenes->isEmpty())
                            <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">
                                Ninguna sucursal tiene unidades de este artículo.
                            </p>
                        @endif
                        <x-input-error for="sucursal1_id"></x-input-error>
                    </div>

                    <div class="mt-4">
                        <x-label>Hacia (destino)</x-label>
                        <select wire:model="sucursal2_id"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                            <option value="">Seleccione una sucursal...</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="sucursal2_id"></x-input-error>
                    </div>

                    <div class="mt-4">
                        <x-label>Unidades a transferir</x-label>
                        <x-input type="number" min="1" step="1" class="w-full" wire:model="cantidad" onfocus="this.select()" autocomplete="off" />
                        <x-input-error for="cantidad"></x-input-error>
                        {{-- 'detalles' lo pone StockService::retirar() al no haber stock. --}}
                        <x-input-error for="detalles"></x-input-error>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($articulo && $origenes->isNotEmpty())
                    <x-primary-button class="ml-2" wire:click="store()" wire:loading.attr="disabled" wire:target="store">
                        Transferir
                    </x-primary-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
