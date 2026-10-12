<div>
    @if ($openModal && $liquidacion)
        <x-dialog-modal wire:model="openModal" maxWidth="2xl">
            <x-slot name="title">Liquidación #{{ $liquidacion->id }} — {{ $liquidacion->beneficiarioNombre() }}</x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2 text-sm text-gray-700 dark:text-gray-300 space-y-1">
                    <p>Del {{ $liquidacion->desde->format('d/m/Y') }} al {{ $liquidacion->hasta->format('d/m/Y') }}</p>
                    <p>Pagada el {{ $liquidacion->created_at->format('d/m/Y H:i') }} por {{ $liquidacion->pagadoPor?->name ?? '—' }}</p>
                    @if ($liquidacion->nota)
                        <p>Nota: {{ $liquidacion->nota }}</p>
                    @endif
                </div>

                <div class="m-2 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                            <tr>
                                <th class="p-2 text-left">Origen</th>
                                <th class="p-2 text-left">Fecha</th>
                                <th class="p-2 text-right">Base</th>
                                <th class="p-2 text-right">Comisión</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($liquidacion->comisiones as $item)
                                <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="liq-ver-{{ $item->id }}">
                                    <td class="p-2">
                                        {{ $item->referencia }}
                                        @if (!$item->venta_id && !$item->producto_reparacion_id)
                                            <span class="text-xs text-rose-600">(anulada después de pagar)</span>
                                        @endif
                                    </td>
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
                </div>

                <p class="m-2 text-right text-lg font-semibold text-gray-800 dark:text-gray-100">
                    Total pagado: Bs {{ number_format((float) $liquidacion->total, 2) }}
                </p>

                @if ($confirmarAnular)
                    <div class="m-2 p-3 rounded-md bg-rose-50 dark:bg-rose-900/40 text-sm text-rose-800 dark:text-rose-200">
                        ¿Anular la liquidación? Sus comisiones vuelven a estar por pagar, recalculadas con su venta o reparación de hoy.
                        Las de ventas anuladas después de pagarlas se borran.
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cerrar</x-secondary-button>
                @can('comision.anular')
                    @if ($confirmarAnular)
                        <x-danger-button class="ml-2" wire:click="anular()" wire:loading.attr="disabled" wire:target="anular">Sí, anular</x-danger-button>
                    @else
                        <x-danger-button class="ml-2" wire:click="$set('confirmarAnular', true)">Anular</x-danger-button>
                    @endif
                @endcan
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
