<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Reportes</h2>
    @include('livewire.reporte.partials.nav', ['actual' => 'reportes.inventario'])
    @include('livewire.reporte.partials.filtros')

    @php
        $bs = fn($v) => number_format((float) $v, 2);
        $caja = 'mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4';
        $thead = 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200';
        $tfoot = 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white';
        $fila = 'border-t border-gray-200 dark:border-gray-700';
    @endphp

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">El valor, los estados, los grados y lo que está por agotarse son a hoy. Las pérdidas son del período.</p>

    {{-- Valor por sucursal --}}
    <div class="{{ $caja }}">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Valor del inventario por sucursal</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="{{ $thead }}">
                    <tr>
                        <th class="p-2 text-left" rowspan="2">Sucursal</th>
                        <th class="p-2 text-center" colspan="3">Equipos</th>
                        <th class="p-2 text-center" colspan="3">Repuestos y accesorios</th>
                        <th class="p-2 text-center" colspan="2">Total</th>
                    </tr>
                    <tr>
                        <th class="p-2 text-right">Cant.</th>
                        <th class="p-2 text-right">Al costo</th>
                        <th class="p-2 text-right">A precio</th>
                        <th class="p-2 text-right">Unid.</th>
                        <th class="p-2 text-right">Al costo</th>
                        <th class="p-2 text-right">A precio</th>
                        <th class="p-2 text-right">Al costo</th>
                        <th class="p-2 text-right">A precio</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($valor as $v)
                        <tr class="{{ $fila }}" wire:key="ri-val-{{ $loop->index }}">
                            <td class="p-2 font-semibold">{{ $v->sucursal }}</td>
                            <td class="p-2 text-right">{{ $v->equipos }}</td>
                            <td class="p-2 text-right">{{ $bs($v->equipos_costo) }}</td>
                            <td class="p-2 text-right">{{ $bs($v->equipos_venta) }}</td>
                            <td class="p-2 text-right">{{ $v->unidades }}</td>
                            <td class="p-2 text-right">{{ $bs($v->articulos_costo) }}</td>
                            <td class="p-2 text-right">{{ $bs($v->articulos_venta) }}</td>
                            <td class="p-2 text-right font-semibold">{{ $bs($v->equipos_costo + $v->articulos_costo) }}</td>
                            <td class="p-2 text-right font-semibold">{{ $bs($v->equipos_venta + $v->articulos_venta) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="p-2 text-gray-500">No hay inventario.</td></tr>
                    @endforelse
                </tbody>
                <tfoot class="{{ $tfoot }}">
                    <tr>
                        <td class="p-2">TOTAL</td>
                        <td class="p-2 text-right">{{ $valor->sum('equipos') }}</td>
                        <td class="p-2 text-right">{{ $bs($valor->sum('equipos_costo')) }}</td>
                        <td class="p-2 text-right">{{ $bs($valor->sum('equipos_venta')) }}</td>
                        <td class="p-2 text-right">{{ $valor->sum('unidades') }}</td>
                        <td class="p-2 text-right">{{ $bs($valor->sum('articulos_costo')) }}</td>
                        <td class="p-2 text-right">{{ $bs($valor->sum('articulos_venta')) }}</td>
                        <td class="p-2 text-right">{{ $bs($valor->sum('equipos_costo') + $valor->sum('articulos_costo')) }}</td>
                        <td class="p-2 text-right">{{ $bs($valor->sum('equipos_venta') + $valor->sum('articulos_venta')) }}</td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            Montos en Bs. Equipos sin vender (también en reparación, reservados, rotos y fuera; no los dados de baja): al costo total y a precio vendedor; el roto, a su costo.
        </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Por estado --}}
        <div class="{{ $caja }}">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Equipos por estado</h3>
            <table class="min-w-full text-sm">
                <thead class="{{ $thead }}">
                    <tr><th class="p-2 text-left">Estado</th><th class="p-2 text-right">Equipos</th><th class="p-2 text-right">Costo</th></tr>
                </thead>
                <tbody>
                    @foreach ($estados as $e)
                        <tr class="{{ $fila }}" wire:key="ri-est-{{ $loop->index }}">
                            <td class="p-2"><span class="inline-block w-2.5 h-2.5 rounded-full mr-1" style="background-color: {{ $e->color }}"></span>{{ $e->estado }}</td>
                            <td class="p-2 text-right">{{ $e->cantidad }}</td>
                            <td class="p-2 text-right">{{ $bs($e->costo) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Por grado --}}
        <div class="{{ $caja }}">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Equipos sin vender por grado</h3>
            <table class="min-w-full text-sm">
                <thead class="{{ $thead }}">
                    <tr><th class="p-2 text-left">Grado</th><th class="p-2 text-right">Equipos</th><th class="p-2 text-right">Costo</th></tr>
                </thead>
                <tbody>
                    @forelse ($grados as $g)
                        <tr class="{{ $fila }}" wire:key="ri-gra-{{ $loop->index }}">
                            <td class="p-2 font-semibold">{{ $g->grado }}</td>
                            <td class="p-2 text-right">{{ $g->cantidad }}</td>
                            <td class="p-2 text-right">{{ $bs($g->costo) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="p-2 text-gray-500">No hay equipos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Perdidas --}}
    <div class="{{ $caja }}">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Pérdidas {{ $this->periodoTexto() }}</h3>
        @if ($porMotivo->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hubo bajas en el período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="p-2 text-left">Motivo</th>
                            <th class="p-2 text-right">Equipos</th>
                            <th class="p-2 text-right">Costo equipos</th>
                            <th class="p-2 text-right">Unidades</th>
                            <th class="p-2 text-right">Costo unidades</th>
                            <th class="p-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porMotivo as $m)
                            <tr class="{{ $fila }}" wire:key="ri-mot-{{ $loop->index }}">
                                <td class="p-2 font-semibold">{{ $m->motivo }}</td>
                                <td class="p-2 text-right">{{ $m->equipos }}</td>
                                <td class="p-2 text-right">{{ $bs($m->equipos_costo) }}</td>
                                <td class="p-2 text-right">{{ $m->unidades }}</td>
                                <td class="p-2 text-right">{{ $bs($m->unidades_costo) }}</td>
                                <td class="p-2 text-right font-semibold">{{ $bs($m->equipos_costo + $m->unidades_costo) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="{{ $tfoot }}">
                        <tr>
                            <td class="p-2">TOTAL</td>
                            <td class="p-2 text-right">{{ $porMotivo->sum('equipos') }}</td>
                            <td class="p-2 text-right">{{ $bs($porMotivo->sum('equipos_costo')) }}</td>
                            <td class="p-2 text-right">{{ $porMotivo->sum('unidades') }}</td>
                            <td class="p-2 text-right">{{ $bs($porMotivo->sum('unidades_costo')) }}</td>
                            <td class="p-2 text-right">{{ $bs($porMotivo->sum('equipos_costo') + $porMotivo->sum('unidades_costo')) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="mt-4 grid grid-cols-1 lg:grid-cols-2 gap-4 text-sm">
                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Equipos dados de baja</p>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($bajasEquipos as $b)
                            <li class="py-1 flex justify-between gap-2" wire:key="ri-be-{{ $b->id }}">
                                <span>
                                    {{ \Carbon\Carbon::parse($b->dado_de_baja_at)->format('d/m/Y') }} ·
                                    @can('producto.historial')
                                        <a href="{{ route('productos.historial', $b->id) }}" class="text-brand-600 hover:underline dark:text-brand-400">{{ trim(($b->modelo ?? 'Equipo') . ' ' . $b->almacenamiento) }}</a>
                                    @else
                                        {{ trim(($b->modelo ?? 'Equipo') . ' ' . $b->almacenamiento) }}
                                    @endcan
                                    <span class="text-xs text-gray-500">· {{ \App\Enums\BajaMotivo::labelDe($b->motivo_baja) }}</span>
                                </span>
                                <span>{{ $bs($b->costo_total) }}</span>
                            </li>
                        @empty
                            <li class="py-1 text-gray-500">Ninguno.</li>
                        @endforelse
                    </ul>
                </div>
                <div>
                    <p class="font-semibold text-gray-700 dark:text-gray-200 mb-1">Unidades dadas de baja</p>
                    <ul class="divide-y divide-gray-100 dark:divide-gray-700">
                        @forelse ($bajasUnidades as $b)
                            <li class="py-1 flex justify-between gap-2" wire:key="ri-bu-{{ $b->id }}">
                                <span>
                                    {{ \Carbon\Carbon::parse($b->created_at)->format('d/m/Y') }} · {{ $b->cantidad }} × {{ $b->nombre }}
                                    <span class="text-xs text-gray-500">· {{ \App\Enums\BajaMotivo::labelDe($b->motivo) }}</span>
                                </span>
                                <span>{{ $bs($b->cantidad * $b->costo) }}</span>
                            </li>
                        @empty
                            <li class="py-1 text-gray-500">Ninguna.</li>
                        @endforelse
                    </ul>
                </div>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">La devolución al proveedor no cuenta como pérdida.</p>
        @endif
    </div>

    {{-- Por agotarse --}}
    <div class="{{ $caja }}">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Repuestos y accesorios por agotarse ({{ $agotarse->count() }})</h3>
        @if ($agotarse->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Nada por agotarse. El mínimo de cada sucursal se fija en la ficha del artículo.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="p-2 text-left">Artículo</th>
                            <th class="p-2 text-left">Tipo</th>
                            <th class="p-2 text-left">Sucursal</th>
                            <th class="p-2 text-right">Hay</th>
                            <th class="p-2 text-right">Mínimo</th>
                            <th class="p-2 text-right">Faltan</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($agotarse as $a)
                            <tr class="{{ $fila }}" wire:key="ri-ag-{{ $a->tipo->value }}-{{ $a->id }}-{{ $loop->index }}">
                                <td class="p-2">
                                    @can($a->tipo->permiso() . '.historial')
                                        <a href="{{ route($a->tipo === \App\Enums\ArticuloTipo::Repuesto ? 'repuestos.historial' : 'accesorios.historial', $a->id) }}"
                                            class="font-semibold text-brand-600 hover:underline dark:text-brand-400">{{ $a->nombre }}</a>
                                    @else
                                        <span class="font-semibold">{{ $a->nombre }}</span>
                                    @endcan
                                    @if ($a->sku)
                                        <span class="block text-xs text-gray-500">{{ $a->sku }}</span>
                                    @endif
                                </td>
                                <td class="p-2">{{ $a->tipo === \App\Enums\ArticuloTipo::Repuesto ? 'Repuesto' : 'Accesorio' }}</td>
                                <td class="p-2">{{ $a->sucursal }}</td>
                                <td class="p-2 text-right font-semibold text-red-600">{{ $a->cantidad }}</td>
                                <td class="p-2 text-right">{{ $a->minimo }}</td>
                                <td class="p-2 text-right">{{ $a->faltan }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
