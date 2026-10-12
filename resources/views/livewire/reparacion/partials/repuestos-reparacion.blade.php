{{-- Las piezas del negocio que se montan en una reparacion
     (RepuestosReparacionFormTrait). La busqueda vive en su propio modal
     (RepuestosReparacionModal), que abre el boton. Lo comparten los modales de
     estado, de editar la reparacion y de garantia/trabajo externo.
     Variables: $repuestos, $titulo (opcional). --}}
<div class="m-2">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <x-label>{{ $titulo ?? 'Repuestos propios' }}</x-label>
        <button type="button" wire:click="abrirSelectorRepuestos" wire:loading.attr="disabled"
            class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md bg-brand-600 text-white shadow-sm hover:bg-brand-700 disabled:opacity-50">
            <i class="fa-solid fa-plus"></i> Agregar repuesto
        </button>
    </div>

    @if (count($repuestos) > 0)
        <div class="overflow-x-auto mt-2">
            <table class="min-w-full border-collapse text-xs">
                <thead>
                    <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                        <th class="p-2 border border-gray-300 dark:border-gray-600">Repuesto</th>
                        <th class="p-2 border border-gray-300 dark:border-gray-600">Costo</th>
                        <th class="p-2 border border-gray-300 dark:border-gray-600">Cant.</th>
                        <th class="p-2 border border-gray-300 dark:border-gray-600">Subtotal</th>
                        <th class="p-2 border border-gray-300 dark:border-gray-600">Acción</th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                    @foreach ($repuestos as $index => $detalle)
                        {{-- wire:key con el indice: el wire:model se enlaza por posicion. --}}
                        <tr wire:key="detalle-{{ $detalle['repuesto_id'] }}-{{ $index }}">
                            <td class="p-2 border border-gray-300 dark:border-gray-600">
                                {{ collect([$detalle['nombre'] ?? null, $detalle['fabricante'] ?? null, $detalle['modelo'] ?? null])->filter()->implode(' · ') }}
                                @if (!empty($detalle['sucursal']))
                                    <span class="block text-gray-500">sale de {{ $detalle['sucursal'] }}</span>
                                @endif
                            </td>
                            <td class="p-2 border border-gray-300 dark:border-gray-600">
                                <input type="number" step="0.01"
                                    wire:model.lazy="repuestos.{{ $index }}.costo"
                                    class="w-24 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                    onfocus="this.select()" />
                            </td>
                            <td class="p-2 border border-gray-300 dark:border-gray-600">
                                <input type="number"
                                    wire:model.lazy="repuestos.{{ $index }}.cantidad"
                                    class="w-20 rounded text-xs p-1 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-900 dark:text-gray-300"
                                    onfocus="this.select()" />
                            </td>
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                {{ number_format($detalle['subtotal_costo'] ?? 0, 2) }}
                            </td>
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-center">
                                <button type="button" wire:click="eliminarRepuesto({{ $index }})"
                                    class="text-red-600 dark:text-red-500 hover:text-red-800 dark:hover:text-red-400 text-2xl font-extrabold leading-none"
                                    title="Quitar repuesto">
                                    ×
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Sin repuestos del negocio.</p>
    @endif

    <x-input-error for="repuestos" class="mt-2" />
    {{-- Lo que rechaza StockService::retirar() (sin stock en esa sucursal). --}}
    <x-input-error for="detalles" class="mt-2" />
</div>
