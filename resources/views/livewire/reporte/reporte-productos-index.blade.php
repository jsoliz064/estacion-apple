<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Reportes</h2>
    @include('livewire.reporte.partials.nav', ['actual' => 'reportes.productos'])
    @include('livewire.reporte.partials.filtros')

    @php $bs = fn($v) => number_format((float) $v, 2); @endphp

    {{-- Por modelo --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Equipos vendidos por modelo</h3>
            <select wire:model.live="orden"
                class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">
                @foreach (\App\Livewire\Reporte\ReporteProductosIndex::ORDENES as $clave => $titulo)
                    <option value="{{ $clave }}">{{ $titulo }}</option>
                @endforeach
            </select>
        </div>
        @if ($modelos->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No se vendieron equipos en el período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">Modelo</th>
                            <th class="p-2 text-right">Vendidos</th>
                            <th class="p-2 text-right">Ingreso</th>
                            <th class="p-2 text-right">Costo</th>
                            <th class="p-2 text-right">Ganancia</th>
                            <th class="p-2 text-right">Margen</th>
                            <th class="p-2 text-right">Días en venderse</th>
                            <th class="p-2 text-right">En stock hoy</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($modelos as $f)
                            <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="rp-mod-{{ $loop->index }}-{{ md5($f->modelo) }}">
                                <td class="p-2 font-semibold">{{ $f->modelo }}</td>
                                <td class="p-2 text-right">{{ $f->unidades }}</td>
                                <td class="p-2 text-right">{{ $bs($f->ingreso) }}</td>
                                <td class="p-2 text-right">{{ $bs($f->costo) }}</td>
                                <td class="p-2 text-right font-semibold">{{ $bs($f->ganancia) }}</td>
                                <td class="p-2 text-right">{{ number_format($f->margen, 1) }} %</td>
                                <td class="p-2 text-right">{{ $f->dias ?? '—' }}</td>
                                <td @class(['p-2 text-right', 'text-red-600 font-semibold' => $f->en_stock === 0])>{{ $f->en_stock }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white">
                        @php
                            $ti = $modelos->sum('ingreso');
                            $tc = $modelos->sum('costo');
                        @endphp
                        <tr>
                            <td class="p-2">TOTAL</td>
                            <td class="p-2 text-right">{{ $modelos->sum('unidades') }}</td>
                            <td class="p-2 text-right">{{ $bs($ti) }}</td>
                            <td class="p-2 text-right">{{ $bs($tc) }}</td>
                            <td class="p-2 text-right">{{ $bs($ti - $tc) }}</td>
                            <td class="p-2 text-right">{{ $ti > 0 ? number_format(($ti - $tc) / $ti * 100, 1) : '0.0' }} %</td>
                            <td class="p-2"></td>
                            <td class="p-2 text-right">{{ $modelos->sum('en_stock') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Montos en Bs, con el descuento de cada venta repartido entre sus líneas. Días en venderse: desde la fecha de la compra (o el alta, si vino en permuta) hasta la venta.
            </p>
        @endif
    </div>

    {{-- Parados --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">
                Equipos parados: {{ (int) $totalParados->cantidad }} · Bs {{ $bs($totalParados->costo) }} al costo
            </h3>
            <label class="text-sm text-gray-600 dark:text-gray-300">
                Sin venderse hace
                <select wire:model.live="diasParado"
                    class="ml-1 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">
                    @foreach (\App\Livewire\Reporte\ReporteProductosIndex::DIAS_PARADO as $d)
                        <option value="{{ $d }}">{{ $d }} días o más</option>
                    @endforeach
                </select>
            </label>
        </div>
        @if ($parados->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay equipos parados con ese criterio.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">Equipo</th>
                            <th class="p-2 text-left">Grado</th>
                            <th class="p-2 text-left">Sucursal</th>
                            <th class="p-2 text-right">Días</th>
                            <th class="p-2 text-right">Costo</th>
                            <th class="p-2 text-right">Precio</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($parados as $p)
                            <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="rp-par-{{ $p->id }}">
                                <td class="p-2">
                                    @can('producto.historial')
                                        <a href="{{ route('productos.historial', $p->id) }}" class="font-semibold text-brand-600 hover:underline dark:text-brand-400">{{ trim(($p->modelo ?? 'Equipo') . ' ' . $p->almacenamiento . ' ' . $p->color) }}</a>
                                    @else
                                        <span class="font-semibold">{{ trim(($p->modelo ?? 'Equipo') . ' ' . $p->almacenamiento . ' ' . $p->color) }}</span>
                                    @endcan
                                    <span class="block text-xs text-gray-500 font-mono">IMEI {{ $p->imei }}</span>
                                </td>
                                <td class="p-2">{{ $p->estado_grado ?? '—' }}</td>
                                <td class="p-2">{{ $p->sucursal ?? '—' }}</td>
                                <td class="p-2 text-right font-semibold">{{ $p->dias }}</td>
                                <td class="p-2 text-right">{{ $bs($p->costo_total) }}</td>
                                <td class="p-2 text-right">{{ $bs($p->precio_cliente) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $parados->links() }}</div>
        @endif
    </div>
</div>
