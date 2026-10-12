<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Reportes</h2>
    @include('livewire.reporte.partials.nav', ['actual' => 'reportes.vendedores'])
    @include('livewire.reporte.partials.filtros')

    @php $bs = fn($v) => number_format((float) $v, 2); @endphp

    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Por vendedor</h3>
        @if ($filas->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No hay ventas en el período.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">Vendedor</th>
                            <th class="p-2 text-right">Ventas</th>
                            <th class="p-2 text-right">Monto</th>
                            <th class="p-2 text-right">Ganancia</th>
                            <th class="p-2 text-right">Margen</th>
                            <th class="p-2 text-right">Ticket prom.</th>
                            <th class="p-2 text-right">Comisión</th>
                            <th class="p-2 text-right">Pendiente</th>
                            <th class="p-2 text-right">Por pagar</th>
                            <th class="p-2 text-right">Pagada</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $f)
                            <tr wire:key="rv-{{ $f->clave }}" wire:click="verVendedor('{{ $f->clave }}')" @class([
                                'border-t border-gray-200 dark:border-gray-700 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700/50',
                                'bg-brand-50 dark:bg-brand-900/30' => $vendedor === $f->clave,
                            ])>
                                <td class="p-2 font-semibold text-brand-700 dark:text-brand-300">{{ $f->nombre }}</td>
                                <td class="p-2 text-right">{{ $f->ventas }}</td>
                                <td class="p-2 text-right">{{ $bs($f->monto) }}</td>
                                <td class="p-2 text-right">{{ $bs($f->ganancia) }}</td>
                                <td class="p-2 text-right">{{ number_format($f->margen, 1) }} %</td>
                                <td class="p-2 text-right">{{ $bs($f->ticket) }}</td>
                                <td class="p-2 text-right font-semibold">{{ $bs($f->comision) }}</td>
                                <td class="p-2 text-right">{{ $bs($f->pendiente) }}</td>
                                <td class="p-2 text-right">{{ $bs($f->por_pagar) }}</td>
                                <td class="p-2 text-right">{{ $bs($f->pagada) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white">
                        <tr>
                            <td class="p-2">TOTAL</td>
                            <td class="p-2 text-right">{{ $total->ventas }}</td>
                            <td class="p-2 text-right">{{ $bs($total->monto) }}</td>
                            <td class="p-2 text-right">{{ $bs($total->ganancia) }}</td>
                            <td class="p-2 text-right">{{ $total->monto > 0 ? number_format($total->ganancia / $total->monto * 100, 1) : '0.0' }} %</td>
                            <td class="p-2 text-right">{{ $total->ventas > 0 ? $bs($total->monto / $total->ventas) : $bs(0) }}</td>
                            <td class="p-2 text-right">{{ $bs($total->comision) }}</td>
                            <td class="p-2 text-right">{{ $bs($total->pendiente) }}</td>
                            <td class="p-2 text-right">{{ $bs($total->por_pagar) }}</td>
                            <td class="p-2 text-right">{{ $bs($total->pagada) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Montos en Bs. El monto incluye la mano de obra; la ganancia no (suma al total y al costo). Toca un vendedor para ver sus ventas.
            </p>
        @endif
    </div>

    @if ($detalle)
        <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Ventas de {{ $nombreVendedor ?? '—' }}</h3>
                @if ($vendedor !== 'sin')
                    @can('user.historial')
                        <a href="{{ route('users.historial', (int) $vendedor) }}" class="text-sm text-brand-600 hover:underline dark:text-brand-400">Ver su ficha</a>
                    @endcan
                @endif
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">Fecha</th>
                            <th class="p-2 text-left">Venta</th>
                            <th class="p-2 text-left">Cliente</th>
                            <th class="p-2 text-right">Total</th>
                            <th class="p-2 text-right">Ganancia</th>
                            <th class="p-2 text-right">Comisión</th>
                            <th class="p-2 text-left">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($detalle as $v)
                            @php
                                $estado = $v->comision === null ? null
                                    : ($v->liquidacion_id ? \App\Enums\ComisionEstado::Pagada
                                        : ($v->ganada_at ? \App\Enums\ComisionEstado::PorPagar : \App\Enums\ComisionEstado::Pendiente));
                            @endphp
                            <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="rv-venta-{{ $v->id }}">
                                <td class="p-2 whitespace-nowrap">{{ \Carbon\Carbon::parse($v->created_at)->format('d/m/Y') }}</td>
                                <td class="p-2">
                                    @can('venta.detalle')
                                        <a href="{{ route('ventas.detalles', $v->id) }}" class="text-brand-600 hover:underline dark:text-brand-400">#{{ $v->id }}</a>
                                    @else
                                        #{{ $v->id }}
                                    @endcan
                                    @if ((float) $v->saldo > 0)
                                        <span class="text-xs text-amber-700">· saldo {{ $bs($v->saldo) }}</span>
                                    @endif
                                </td>
                                <td class="p-2">{{ $v->cliente_nombre ?? ($v->cliente ?: '—') }}</td>
                                <td class="p-2 text-right">{{ $bs($v->total) }}</td>
                                <td class="p-2 text-right">{{ $bs($v->total - $v->costo_total) }}</td>
                                <td class="p-2 text-right">{{ $v->comision !== null ? $bs($v->comision) : '—' }}</td>
                                <td class="p-2">{!! $estado?->badge() !!}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2">{{ $detalle->links() }}</div>
        </div>
    @endif
</div>
