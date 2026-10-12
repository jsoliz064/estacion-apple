<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="2xl">
            <x-slot name="title">Liquidar comisiones</x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2 grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div class="sm:col-span-3">
                        <x-label value="Persona" />
                        <select wire:model.live="beneficiario"
                            class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                            <option value="">Elige a quién se le paga...</option>
                            @foreach ($personas as $clave => $nombre)
                                <option value="{{ $clave }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="beneficiario" class="mt-1" />
                    </div>
                    <div>
                        <x-label value="Desde" />
                        <x-input type="date" class="mt-1 w-full" wire:model.live="desde" />
                    </div>
                    <div>
                        <x-label value="Hasta" />
                        <x-input type="date" class="mt-1 w-full" wire:model.live="hasta" />
                    </div>
                </div>

                @if ($anteriores && (int) $anteriores->cantidad > 0)
                    <div class="m-2 p-3 rounded-md bg-amber-50 dark:bg-amber-900/40 text-sm text-amber-800 dark:text-amber-200 flex flex-wrap items-center justify-between gap-2">
                        <span>Hay {{ (int) $anteriores->cantidad }} comisión(es) ganada(s) antes del período y sin pagar: Bs {{ number_format((float) $anteriores->monto, 2) }}.</span>
                        <button type="button" wire:click="incluirAnteriores"
                            class="px-2 py-1 rounded-md bg-amber-600 text-white text-xs font-semibold hover:bg-amber-700">Incluirlas</button>
                    </div>
                @endif

                <div class="m-2 overflow-x-auto">
                    @if ($beneficiario === '')
                        <p class="text-sm text-gray-500 dark:text-gray-400">Elige una persona.</p>
                    @elseif ($items->isEmpty())
                        <p class="text-sm text-gray-500 dark:text-gray-400">No tiene comisiones ganadas y sin pagar en este período.</p>
                    @else
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                                <tr>
                                    <th class="p-2 w-8"></th>
                                    <th class="p-2 text-left">Origen</th>
                                    <th class="p-2 text-left">Fecha</th>
                                    <th class="p-2 text-right">Base</th>
                                    <th class="p-2 text-right">Comisión</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($items as $item)
                                    <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="liq-item-{{ $item->id }}">
                                        <td class="p-2">
                                            <input type="checkbox" value="{{ $item->id }}" wire:model.live="seleccion"
                                                class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                        </td>
                                        <td class="p-2">{{ $item->referencia }}</td>
                                        <td class="p-2 whitespace-nowrap">{{ $item->ganada_at?->format('d/m/Y') }}</td>
                                        <td class="p-2 text-right whitespace-nowrap">{{ number_format((float) $item->base, 2) }}</td>
                                        <td class="p-2 text-right whitespace-nowrap">
                                            {{ number_format((float) $item->monto, 2) }}
                                            <span class="block text-xs text-gray-500">{{ rtrim(rtrim(number_format((float) $item->porcentaje, 2), '0'), '.') }} %</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                    <x-input-error for="seleccion" class="mt-1" />
                </div>

                <div class="m-2">
                    <x-label value="Nota (opcional)" />
                    <x-input type="text" class="mt-1 w-full" wire:model="nota" maxlength="255" />
                </div>

                <p class="m-2 text-right text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Total a pagar: Bs {{ number_format($total, 2) }}
                </p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                @if ($items->isNotEmpty())
                    <x-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled" wire:target="guardar">Registrar pago</x-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
