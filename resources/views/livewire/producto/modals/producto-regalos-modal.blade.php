<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="3xl">
            <x-slot name="title">
                Regalos del equipo
            </x-slot>

            <x-slot name="content">
                <hr>
                @if (!$producto)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">El equipo ya no existe.</p>
                @else
                    <div class="mt-4 flex flex-wrap items-baseline justify-between gap-2">
                        <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $producto->modelo?->nombre }} {{ $producto->almacenamiento }} · IMEI {{ $producto->imei }}
                        </p>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Regalos: <span class="font-semibold">Bs {{ number_format((float) $producto->costo_regalos, 2) }}</span>
                            · Costo total: <span class="font-semibold">Bs {{ number_format((float) $producto->costo_total, 2) }}</span>
                        </p>
                    </div>

                    @unless ($editable)
                        <p class="mt-3 rounded-md bg-gray-100 dark:bg-gray-800 p-2 text-sm text-gray-600 dark:text-gray-400">
                            El equipo ya está vendido o dado de baja: sus regalos se muestran pero no se pueden cambiar.
                        </p>
                    @endunless

                    <x-input-error for="detalles" class="mt-2"></x-input-error>
                    <x-input-error for="cantidad" class="mt-2"></x-input-error>

                    {{-- Lo ya regalado --}}
                    <div class="mt-4 overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="text-left text-gray-500 dark:text-gray-400 border-b border-gray-200 dark:border-gray-700">
                                    <th class="py-2 pr-2">Accesorio</th>
                                    <th class="py-2 pr-2">Salió de</th>
                                    <th class="py-2 pr-2 text-right">Costo</th>
                                    <th class="py-2 pr-2">Cantidad</th>
                                    <th class="py-2 text-right">Subtotal</th>
                                    @if ($editable)
                                        <th class="py-2"></th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($producto->regalos as $regalo)
                                    <tr wire:key="regalo-{{ $regalo->id }}" class="border-b border-gray-100 dark:border-gray-800 text-gray-800 dark:text-gray-200">
                                        <td class="py-2 pr-2">{{ $regalo->accesorio?->nombre }}</td>
                                        <td class="py-2 pr-2">{{ $regalo->sucursal?->nombre }}</td>
                                        <td class="py-2 pr-2 text-right">Bs {{ number_format((float) $regalo->costo, 2) }}</td>
                                        <td class="py-2 pr-2">
                                            @if ($editable)
                                                <div class="flex items-center gap-1">
                                                    <x-input type="number" min="0" step="1" class="w-20" wire:model="cantidades.{{ $regalo->id }}" onfocus="this.select()" />
                                                    <button type="button" wire:click="ajustar({{ $regalo->id }})" wire:loading.attr="disabled"
                                                        class="text-xs text-brand-700 dark:text-brand-300 hover:underline">Guardar</button>
                                                </div>
                                            @else
                                                {{ (int) $regalo->cantidad }}
                                            @endif
                                        </td>
                                        <td class="py-2 text-right">Bs {{ number_format((float) $regalo->subtotal_costo, 2) }}</td>
                                        @if ($editable)
                                            <td class="py-2 pl-2 text-right">
                                                <button type="button" wire:click="quitar({{ $regalo->id }})" wire:loading.attr="disabled"
                                                    wire:confirm="¿Quitar este regalo? Las unidades vuelven al stock."
                                                    class="text-xs text-red-600 hover:underline">Quitar</button>
                                            </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-3 text-center text-gray-500 dark:text-gray-400">Sin regalos.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Agregar uno --}}
                    @if ($editable)
                        <div class="mt-6 rounded-md border border-gray-200 dark:border-gray-700 p-3">
                            <x-label>Agregar regalo</x-label>

                            @if ($elegido)
                                <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $elegido->nombre }} · Bs {{ number_format((float) $elegido->costo, 2) }}
                                    </p>
                                    <button type="button" wire:click="$set('accesorioId', null)" class="text-xs text-gray-500 hover:underline">Cambiar</button>
                                </div>
                                <div class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div class="sm:col-span-2">
                                        <x-label>Sale de</x-label>
                                        <select wire:model="sucursal_id"
                                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                            <option value="">Seleccione...</option>
                                            @foreach ($origenes as $fila)
                                                <option value="{{ $fila->sucursal_id }}">{{ $fila->sucursal?->nombre }} ({{ (int) $fila->cantidad }})</option>
                                            @endforeach
                                        </select>
                                        <x-input-error for="sucursal_id"></x-input-error>
                                    </div>
                                    <div>
                                        <x-label>Cantidad</x-label>
                                        <x-input type="number" min="1" step="1" class="w-full" wire:model="cantidad" onfocus="this.select()" />
                                    </div>
                                </div>
                                <div class="mt-3 flex justify-end">
                                    <x-button wire:click="agregar()" wire:loading.attr="disabled" wire:target="agregar">Agregar</x-button>
                                </div>
                            @else
                                <div class="mt-1 flex gap-2" data-escaner>
                                    <x-input type="text" class="w-full" wire:model.live.debounce.300ms="busqueda"
                                        x-on:keydown.enter.prevent="$wire.elegirPorCodigo($el.value); $el.value = ''"
                                        placeholder="Escanee o escriba nombre, SKU o código de barras" />
                                    <x-boton-escaner />
                                </div>
                                <x-input-error for="accesorioId"></x-input-error>
                                @if (trim($busqueda) === '' && $resultados->isNotEmpty())
                                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Compatibles con este modelo:</p>
                                @endif
                                <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-800">
                                    @forelse ($resultados as $accesorio)
                                        <li wire:key="acc-{{ $accesorio->id }}">
                                            <button type="button" wire:click="elegir({{ $accesorio->id }})"
                                                class="w-full flex justify-between gap-2 py-2 text-left text-sm text-gray-800 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-800">
                                                <span>
                                                    {{ $accesorio->nombre }}
                                                    @if ($accesorio->sku)
                                                        <span class="text-xs text-gray-500">· {{ $accesorio->sku }}</span>
                                                    @endif
                                                </span>
                                                <span class="text-xs text-gray-500">Stock {{ (int) $accesorio->cantidad }} · Bs {{ number_format((float) $accesorio->costo, 2) }}</span>
                                            </button>
                                        </li>
                                    @empty
                                        @if (trim($busqueda) !== '')
                                            <li class="py-2 text-sm text-gray-500 dark:text-gray-400">Ningún accesorio con stock coincide.</li>
                                        @endif
                                    @endforelse
                                </ul>
                            @endif
                        </div>
                    @endif
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
