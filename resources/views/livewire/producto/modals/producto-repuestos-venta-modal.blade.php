<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="3xl">
            <x-slot name="title">
                <p class="text-center">Repuestos a cobrar con el equipo</p>
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="mt-4 text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold">{{ $productoImei }}</span>
                    <span class="text-gray-400">· {{ $productoDescripcion }}</span>
                </div>

                @if ($elegibles->isEmpty())
                    <p class="mt-6 text-center text-gray-400 text-sm">
                        Este equipo no tiene repuestos de reparación pendientes de cobrar.
                    </p>
                @else
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Estos repuestos ya se montaron y ya salieron del almacén. Marca los que le cobras
                        al cliente aparte del precio del teléfono; el stock no se vuelve a descontar.
                    </p>

                    <div
                        class="overflow-x-auto mt-3 border border-gray-200 dark:border-gray-700 shadow-sm rounded-lg">
                        <table class="min-w-full text-xs">
                            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                <tr>
                                    <th class="p-2 border-b w-10"></th>
                                    <th class="p-2 border-b text-left">Repuesto</th>
                                    <th class="p-2 border-b text-center">Reparación</th>
                                    <th class="p-2 border-b text-center">Cant.</th>
                                    <th class="p-2 border-b text-center">Precio (Bs)</th>
                                    <th class="p-2 border-b text-right">Subtotal (Bs)</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                                @foreach ($elegibles as $linea)
                                    @php
                                        $clave = (string) $linea->id;
                                        $marcado = in_array($clave, array_map('strval', $seleccionados), true);
                                        $precio = (float) ($precios[$clave] ?? 0);
                                    @endphp
                                    <tr wire:key="rep-rep-{{ $linea->id }}"
                                        class="{{ $marcado ? 'bg-brand-50 dark:bg-brand-900' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                        <td class="p-2 border text-center">
                                            <x-checkbox wire:model.live="seleccionados" value="{{ $linea->id }}" />
                                        </td>
                                        <td class="p-2 border">
                                            {{ $linea->repuesto?->nombre ?? 'Repuesto' }}
                                            @if ($linea->repuesto?->fabricante)
                                                <span class="text-gray-400">· {{ $linea->repuesto->fabricante }}</span>
                                            @endif
                                        </td>
                                        <td class="p-2 border text-center">
                                            #{{ $linea->producto_reparacion_id }}
                                            <span class="block text-gray-400">
                                                {{ \Carbon\Carbon::parse($linea->created_at)->format('d/m/Y') }}
                                            </span>
                                        </td>
                                        <td class="p-2 border text-center">{{ $linea->cantidad }}</td>
                                        <td class="p-2 border text-center">
                                            <input type="number" step="0.01" min="0"
                                                wire:model.lazy="precios.{{ $linea->id }}"
                                                class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                                onfocus="this.select()" />
                                            <x-input-error for="precios.{{ $linea->id }}" class="mt-1" />
                                        </td>
                                        <td class="p-2 border text-right">
                                            {{ number_format($precio * $linea->cantidad, 2) }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 flex items-center justify-between text-sm">
                        <span class="text-gray-600 dark:text-gray-300">
                            <span class="font-semibold">{{ count($seleccionados) }}</span> seleccionado(s)
                        </span>
                        <span class="text-gray-800 dark:text-gray-200">
                            Total a cobrar: <span class="font-semibold">Bs {{ number_format($totalSeleccionado, 2) }}</span>
                        </span>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($elegibles->isNotEmpty())
                    <x-primary-button class="ml-2" wire:click="confirmar" wire:loading.attr="disabled">
                        Confirmar
                    </x-primary-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
