<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Reportes</h2>
    @include('livewire.reporte.partials.nav', ['actual' => 'reportes.clientes'])
    @include('livewire.reporte.partials.filtros')

    @php
        $bs = fn($v) => number_format((float) $v, 2);
        $caja = 'mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4';
        $thead = 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200';
        $tfoot = 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white';
        $fila = 'border-t border-gray-200 dark:border-gray-700';
        $puedeFicha = auth()->user()->can('cliente.historial');
        $nombre = fn($id, $texto) => $puedeFicha
            ? '<a href="' . route('clientes.historial', $id) . '" class="font-semibold text-brand-600 hover:underline dark:text-brand-400">' . e($texto) . '</a>'
            : '<span class="font-semibold">' . e($texto) . '</span>';
    @endphp

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-amber-50 dark:bg-gray-800 border-l-4 border-amber-500 p-4 rounded-lg shadow-sm">
            <p class="text-sm text-amber-900 dark:text-amber-200">Deuda de clientes (a hoy)</p>
            <p class="text-2xl font-bold text-amber-800 dark:text-amber-100">Bs {{ $bs($deuda->deuda) }}</p>
            <p class="text-xs text-amber-700 dark:text-amber-300">{{ (int) $deuda->clientes }} cliente(s)</p>
        </div>
        <div class="bg-brand-50 dark:bg-gray-800 border-l-4 border-brand-500 p-4 rounded-lg shadow-sm">
            <p class="text-sm text-brand-900 dark:text-brand-200">Clientes nuevos del período</p>
            <p class="text-2xl font-bold text-brand-800 dark:text-brand-100">{{ $nuevos->count() }}</p>
        </div>
        <div class="bg-gray-50 dark:bg-gray-800 border-l-4 border-gray-400 p-4 rounded-lg shadow-sm">
            <p class="text-sm text-gray-700 dark:text-gray-300">Ventas de mostrador (sin ficha)</p>
            <p class="text-2xl font-bold text-gray-800 dark:text-gray-100">{{ $mostrador->sin_ficha }} de {{ $mostrador->ventas }}</p>
            <p class="text-xs text-gray-600 dark:text-gray-400">Bs {{ $bs($mostrador->monto_sin_ficha) }} de Bs {{ $bs($mostrador->monto) }}</p>
        </div>
    </div>

    {{-- Los que mas compran --}}
    <div class="{{ $caja }}">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Los que más compran (top {{ \App\Livewire\Reporte\ReporteClientesIndex::TOP }})</h3>
            <select wire:model.live="ordenTop"
                class="border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm text-sm">
                <option value="monto">Por monto</option>
                <option value="compras">Por cantidad de compras</option>
            </select>
        </div>
        @if ($top->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay ventas a clientes con ficha en el período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="p-2 text-left">#</th>
                            <th class="p-2 text-left">Cliente</th>
                            <th class="p-2 text-right">Compras</th>
                            <th class="p-2 text-right">Monto</th>
                            <th class="p-2 text-right">Última</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($top as $c)
                            <tr class="{{ $fila }}" wire:key="rc-top-{{ $c->id }}">
                                <td class="p-2 text-gray-500">{{ $loop->iteration }}</td>
                                <td class="p-2">{!! $nombre($c->id, $c->nombre) !!}</td>
                                <td class="p-2 text-right">{{ $c->compras }}</td>
                                <td class="p-2 text-right font-semibold">{{ $bs($c->monto) }}</td>
                                <td class="p-2 text-right whitespace-nowrap">{{ \Carbon\Carbon::parse($c->ultima)->format('d/m/Y') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Deuda --}}
    <div class="{{ $caja }}">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Deuda por cliente y su antigüedad (a hoy)</h3>
        @if ($deudores->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Ningún cliente debe.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="p-2 text-left">Cliente</th>
                            <th class="p-2 text-right">Ventas</th>
                            <th class="p-2 text-right">0–30 días</th>
                            <th class="p-2 text-right">31–60</th>
                            <th class="p-2 text-right">61–90</th>
                            <th class="p-2 text-right">Más de 90</th>
                            <th class="p-2 text-right">Deuda</th>
                            <th class="p-2 text-right">Más antigua</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($deudores as $d)
                            <tr class="{{ $fila }}" wire:key="rc-deu-{{ $d->id }}">
                                <td class="p-2">
                                    {!! $nombre($d->id, $d->nombre) !!}
                                    @if ($d->telefono)
                                        <span class="block text-xs text-gray-500">{{ $d->telefono }}</span>
                                    @endif
                                </td>
                                <td class="p-2 text-right">{{ $d->ventas }}</td>
                                <td class="p-2 text-right">{{ $bs($d->t30) }}</td>
                                <td class="p-2 text-right">{{ $bs($d->t60) }}</td>
                                <td class="p-2 text-right">{{ $bs($d->t90) }}</td>
                                <td @class(['p-2 text-right', 'text-red-600 font-semibold' => (float) $d->tmas > 0])>{{ $bs($d->tmas) }}</td>
                                <td class="p-2 text-right font-semibold">{{ $bs($d->deuda) }}</td>
                                <td class="p-2 text-right whitespace-nowrap">{{ (int) $d->antiguedad }} días</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="{{ $tfoot }}">
                        <tr>
                            <td class="p-2">TOTAL</td>
                            <td class="p-2"></td>
                            <td class="p-2 text-right">{{ $bs($deuda->t30) }}</td>
                            <td class="p-2 text-right">{{ $bs($deuda->t60) }}</td>
                            <td class="p-2 text-right">{{ $bs($deuda->t90) }}</td>
                            <td class="p-2 text-right">{{ $bs($deuda->tmas) }}</td>
                            <td class="p-2 text-right">{{ $bs($deuda->deuda) }}</td>
                            <td class="p-2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="mt-2">{{ $deudores->links() }}</div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">La antigüedad se cuenta desde la fecha de cada venta con saldo.</p>
        @endif
    </div>

    {{-- Nuevos --}}
    <div class="{{ $caja }}">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Clientes nuevos {{ $this->periodoTexto() }}</h3>
        @if ($nuevos->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No se dieron de alta clientes en el período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="{{ $thead }}">
                        <tr>
                            <th class="p-2 text-left">Cliente</th>
                            <th class="p-2 text-left">Alta</th>
                            <th class="p-2 text-right">Compras en el período</th>
                            <th class="p-2 text-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($nuevos as $n)
                            <tr class="{{ $fila }}" wire:key="rc-nue-{{ $n->id }}">
                                <td class="p-2">{!! $nombre($n->id, $n->nombre) !!}</td>
                                <td class="p-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($n->created_at)->format('d/m/Y') }}</td>
                                <td class="p-2 text-right">{{ (int) $n->compras }}</td>
                                <td class="p-2 text-right">{{ $bs($n->monto) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
