<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Reservar equipo</x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label value="Equipo" />
                    @if ($producto)
                        <div class="mt-1 flex items-center justify-between gap-2 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 dark:border-brand-700 dark:bg-brand-900">
                            <span class="text-sm">
                                <span class="font-semibold">{{ trim(($producto->modelo?->nombre ?? 'Equipo') . ' ' . $producto->almacenamiento . ' ' . $producto->color) }}</span>
                                <span class="block text-xs font-mono text-gray-600 dark:text-gray-300">IMEI {{ $producto->imei }} · Bs {{ number_format((float) $producto->precio_cliente, 2) }}</span>
                            </span>
                            <button type="button" wire:click="quitarEquipo" class="text-red-600 text-lg" title="Quitar">&times;</button>
                        </div>
                    @else
                        <div class="relative">
                            <div class="mt-1 flex gap-2" data-escaner>
                                <x-input type="text" class="w-full" placeholder="Escanee o escriba el IMEI o SKU..."
                                    wire:model.live.debounce.400ms="buscarEquipo"
                                    x-on:keydown.enter.prevent="$wire.elegirEquipoPorCodigo($el.value); $el.value = ''" autocomplete="off" />
                                <x-boton-escaner />
                            </div>
                            @if (!empty($equipos))
                                <ul class="absolute z-50 mt-1 w-full max-h-60 overflow-y-auto rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                    @foreach ($equipos as $e)
                                        <li wire:key="res-eq-{{ $e['id'] }}">
                                            <button type="button" wire:click="elegirEquipo({{ $e['id'] }})" class="w-full px-3 py-2 text-left text-sm hover:bg-brand-50 dark:hover:bg-gray-700">
                                                <span class="font-medium">{{ $e['etiqueta'] }}</span>
                                                <span class="block text-xs text-gray-500">{{ $e['detalle'] }}</span>
                                            </button>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>
                    @endif
                    <x-input-error for="productoId" class="mt-1" />
                </div>

                <div class="m-2">
                    <x-cliente-picker :search="$searchCliente" :sugerencias="$filteredClientes"
                        :nombre="$clienteNombre" :cliente-id="$clienteId" label="Cliente" />
                    <x-input-error for="clienteId" class="mt-1" />
                    <x-input-error for="cliente" class="mt-1" />
                </div>

                <div class="m-2 grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <x-label value="Seña (Bs)" />
                        <x-input type="number" min="0" step="0.01" class="mt-1 w-full" wire:model="sena" onfocus="this.select()" />
                        <x-input-error for="sena" class="mt-1" />
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

                <div class="m-2">
                    <x-label value="Nota (opcional)" />
                    <x-input type="text" class="mt-1 w-full" wire:model="nota" maxlength="255" />
                </div>

                <x-input-error for="detalles" class="m-2" />
                <p class="m-2 text-xs text-gray-500 dark:text-gray-400">
                    El equipo queda apartado: no se vende a otro ni sale en el catálogo. Al concretar la venta, la seña se descuenta del total.
                </p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled" wire:target="guardar">Reservar</x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
