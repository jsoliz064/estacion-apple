<div>
    <div
        class="antialiased text-gray-900 dark:text-gray-100 bg-gradient-to-br from-slate-50 to-stone-100 dark:from-gray-800 dark:to-gray-900 min-h-screen p-4 sm:p-6 lg:p-8">
        <div class="container mx-auto max-w-6xl">

            <header class="mb-6 text-center">
                <h1
                    class="text-3xl sm:text-4xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-brand-600 via-purple-600 to-pink-600 pb-2">
                    Reportes
                </h1>
            </header>

            @include('livewire.reporte.partials.nav', ['actual' => 'reporte'])

            {{-- Aviso de carga. El boton "Cancelar" que habia aqui llamaba a
                 $set('cancelLoading'), una propiedad que no existe: reventaba
                 con PropertyNotFoundException al pulsarlo. --}}
            <div wire:loading class="fixed top-0 left-0 right-0 z-50 flex items-center justify-center p-4">
                <div
                    class="bg-brand-600 dark:bg-brand-700 text-white text-sm font-semibold px-4 py-2 rounded-lg shadow-xl flex items-center">
                    <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg"
                        fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                            stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor"
                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                        </path>
                    </svg>
                    Procesando...
                </div>
            </div>

            {{-- Filtros --}}
            <x-collapse-card title="Filtros" :open-on-desktop="true">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end p-2">
                    <div>
                        <label for="startDate"
                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Fecha Inicio:</label>
                        <input type="date" id="startDate" wire:model.live="startDate" wire:change="validateDates"
                            min="2000-01-01" max="{{ now()->format('Y-m-d') }}"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 shadow-sm focus:border-brand-500 focus:ring focus:ring-brand-500 focus:ring-opacity-50 p-3 text-sm dark:bg-gray-700 dark:text-white
                                   @error('startDate') border-red-500 @enderror">
                        @error('startDate')
                            <span class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="endDate"
                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Fecha Fin:</label>
                        <input type="date" id="endDate" wire:model.live="endDate" wire:change="validateDates"
                            min="2000-01-01" max="{{ now()->format('Y-m-d') }}"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 shadow-sm focus:border-brand-500 focus:ring focus:ring-brand-500 focus:ring-opacity-50 p-3 text-sm dark:bg-gray-700 dark:text-white
                                   @error('endDate') border-red-500 @enderror">
                        @error('endDate')
                            <span class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div>
                        <label for="reportType"
                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Agrupar por:</label>
                        <select id="reportType" wire:model.live="reportType"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 shadow-sm focus:border-brand-500 focus:ring focus:ring-brand-500 focus:ring-opacity-50 p-3 text-sm dark:bg-gray-700 dark:text-white">
                            <option value="daily">Día</option>
                            <option value="monthly">Mes</option>
                        </select>
                    </div>

                    <div>
                        <label for="sucursalId"
                            class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-1">Sucursal:</label>
                        <select id="sucursalId" wire:model.live="sucursalId"
                            class="mt-1 block w-full rounded-lg border-gray-300 dark:border-gray-600 shadow-sm focus:border-brand-500 focus:ring focus:ring-brand-500 focus:ring-opacity-50 p-3 text-sm dark:bg-gray-700 dark:text-white">
                            <option value="">Todas</option>
                            @foreach ($this->sucursales() as $sucursal)
                                <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}{{ $sucursal->activa ? '' : ' (inactiva)' }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if ($avisoAgrupacion)
                    <div class="mx-2 mb-2 p-3 rounded-lg bg-amber-50 dark:bg-amber-900/30 border border-amber-300 dark:border-amber-700 text-sm text-amber-800 dark:text-amber-200">
                        {{ $avisoAgrupacion }}
                    </div>
                @endif

                @if (session('date_error'))
                    <div class="mx-2 mb-2 p-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-300 dark:border-red-700 text-sm text-red-700 dark:text-red-300">
                        {{ session('date_error') }}
                    </div>
                @endif
                @if (session('date_info'))
                    <div class="mx-2 mb-2 p-3 rounded-lg bg-brand-50 dark:bg-brand-900/30 border border-brand-300 dark:border-brand-700 text-sm text-brand-700 dark:text-brand-300">
                        {{ session('date_info') }}
                    </div>
                @endif
            </x-collapse-card>

            @php
                $claseTarjeta = 'bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700';
                $claseEtiqueta = 'text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400';
                $claseValor = 'mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-gray-900 dark:text-white';
                $claseTendencia = fn($v) => $v >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
            @endphp

            {{-- ============ RESUMEN GENERAL ============
                 Fijo: no depende de la pestaña. Aquí vive la mano de obra, que
                 no pertenece a ninguna línea de negocio y por tanto no cabe en
                 ninguna pestaña. Sin este bloque el total del negocio no
                 aparecería en ninguna parte. --}}
            <section class="mb-8" aria-label="Resumen general del período">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                    Todo el negocio
                    <span class="normal-case font-normal">
                        &mdash; del {{ \Carbon\Carbon::parse($startDate)->format('d/m/Y') }}
                        al {{ \Carbon\Carbon::parse($endDate)->format('d/m/Y') }}
                    </span>
                </h2>

                <div class="grid grid-cols-2 lg:grid-cols-6 gap-3 sm:gap-6">
                    <div class="{{ $claseTarjeta }}">
                        <h3 class="{{ $claseEtiqueta }}">Ingreso total</h3>
                        <p class="{{ $claseValor }}">Bs {{ number_format($general['ingreso'], 2) }}</p>
                        <p class="mt-1 text-xs {{ $claseTendencia($general['tendenciaIngreso']) }}">
                            {{ $general['tendenciaIngreso'] >= 0 ? '↑' : '↓' }} {{ abs($general['tendenciaIngreso']) }}% vs período anterior
                        </p>
                    </div>

                    <div class="{{ $claseTarjeta }}">
                        <h3 class="{{ $claseEtiqueta }}">Inversión total</h3>
                        <p class="{{ $claseValor }}">Bs {{ number_format($general['inversion'], 2) }}</p>
                        <p class="mt-1 text-xs {{ $claseTendencia($general['tendenciaInversion']) }}">
                            {{ $general['tendenciaInversion'] >= 0 ? '↑' : '↓' }} {{ abs($general['tendenciaInversion']) }}% vs período anterior
                        </p>
                    </div>

                    <div class="bg-green-100 dark:bg-green-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
                        <h3 class="text-xs sm:text-sm font-medium text-green-700 dark:text-green-300">Ganancia total</h3>
                        <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                            Bs {{ number_format($general['ganancia'], 2) }}
                        </p>
                        <p class="mt-1 text-xs {{ $general['tendenciaGanancia'] >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-600 dark:text-red-400' }}">
                            {{ $general['tendenciaGanancia'] >= 0 ? '↑' : '↓' }} {{ abs($general['tendenciaGanancia']) }}% vs período anterior
                        </p>
                    </div>

                    <div class="{{ $claseTarjeta }}">
                        <h3 class="{{ $claseEtiqueta }}">Margen global</h3>
                        <p class="{{ $claseValor }}">{{ number_format($general['margen'], 1) }} %</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Ticket Bs {{ number_format($general['ticket'], 2) }}
                        </p>
                    </div>

                    {{-- Bajas: equipos y unidades de stock que salieron sin venta. --}}
                    <div class="bg-red-50 dark:bg-red-900/40 p-4 sm:p-6 rounded-2xl shadow-lg border border-red-200 dark:border-red-800">
                        <h3 class="text-xs sm:text-sm font-medium text-red-700 dark:text-red-300">Pérdidas</h3>
                        <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-red-900 dark:text-red-100">
                            Bs {{ number_format($general['perdidas'], 2) }}
                        </p>
                        <p class="mt-1 text-xs text-red-700 dark:text-red-300">Bajas a su costo</p>
                    </div>

                    <div class="{{ $claseTarjeta }}">
                        <h3 class="{{ $claseEtiqueta }}">Mano de obra</h3>
                        <p class="{{ $claseValor }}">Bs {{ number_format($general['manoObra'], 2) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            No pertenece a ninguna línea
                        </p>
                    </div>
                </div>

                {{-- Composición del ingreso: de dónde salen esos totales. --}}
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                    Ingreso por línea:
                    @foreach ($general['desglose'] as $linea)
                        <span class="whitespace-nowrap">
                            <span class="font-medium text-gray-700 dark:text-gray-300">{{ $linea['etiqueta'] }}</span>
                            Bs {{ number_format($linea['monto'], 2) }}
                        </span>{{ !$loop->last ? ' · ' : '' }}
                    @endforeach
                </p>
            </section>

            {{-- Pestanas. Estado en el servidor (no Alpine): cambiar de pestana
                 tiene que recalcular los agregados y volver a despachar el
                 grafico. --}}
            <div class="border-b border-gray-200 dark:border-gray-700 mb-4">
                <nav class="-mb-px flex gap-1 sm:gap-8 overflow-x-auto" role="tablist" aria-label="Línea de negocio">
                    @foreach (\App\Livewire\Reporte\ReporteIndex::TABS as $valor => $etiqueta)
                        <button type="button" role="tab" wire:key="tab-{{ $valor }}"
                            aria-selected="{{ $tab === $valor ? 'true' : 'false' }}"
                            wire:click="seleccionarTab('{{ $valor }}')"
                            class="shrink-0 whitespace-nowrap border-b-2 px-4 sm:px-1 py-3 text-sm font-medium rounded-t
                                   focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 transition
                                   {{ $tab === $valor
                                       ? 'border-brand-500 text-brand-600 dark:text-brand-400 dark:border-brand-400'
                                       : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200' }}">
                            {{ $etiqueta }}
                        </button>
                    @endforeach
                </nav>
            </div>

            {{-- La frase que faltaba: decir en prosa que se esta mirando. --}}
            <p class="mb-6 text-sm text-gray-600 dark:text-gray-300">
                <span class="font-semibold text-gray-900 dark:text-white">{{ $resumen['titulo'] }}</span>
                &mdash; {{ $resumen['descripcion'] }}
            </p>

            {{-- ============ VENTAS DE LA LINEA ============
                 Un unico bloque para las tres pestañas: resumenPestana()
                 devuelve siempre las mismas claves, asi que las cifras son
                 comparables de una pestaña a otra. --}}
            <div wire:loading.class="opacity-50 animate-pulse" wire:target="seleccionarTab">
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                    Ventas de {{ mb_strtolower($resumen['titulo']) }}
                </h3>
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-6 mb-6">
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Ingreso</h4>
                        <p class="{{ $claseValor }}">Bs {{ number_format($resumen['ingreso'], 2) }}</p>
                        <p class="mt-1 text-xs {{ $claseTendencia($resumen['tendenciaIngreso']) }}">
                            {{ $resumen['tendenciaIngreso'] >= 0 ? '↑' : '↓' }} {{ abs($resumen['tendenciaIngreso']) }}%
                        </p>
                    </div>
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Unidades vendidas</h4>
                        <p class="{{ $claseValor }}">{{ number_format($resumen['unidades'], 0) }}</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            {{ number_format($resumen['operaciones'], 0) }} operación(es)
                        </p>
                    </div>
                    <div class="bg-green-100 dark:bg-green-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
                        <h4 class="text-xs sm:text-sm font-medium text-green-700 dark:text-green-300">Ganancia</h4>
                        <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                            Bs {{ number_format($resumen['ganancia'], 2) }}
                        </p>
                        <p class="mt-1 text-xs {{ $resumen['tendenciaGanancia'] >= 0 ? 'text-green-700 dark:text-green-300' : 'text-red-600 dark:text-red-400' }}">
                            {{ $resumen['tendenciaGanancia'] >= 0 ? '↑' : '↓' }} {{ abs($resumen['tendenciaGanancia']) }}%
                        </p>
                    </div>
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Margen</h4>
                        <p class="{{ $claseValor }}">{{ number_format($resumen['margen'], 1) }} %</p>
                    </div>
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Ticket promedio</h4>
                        <p class="{{ $claseValor }}">Bs {{ number_format($resumen['ticket'], 2) }}</p>
                    </div>
                </div>

                {{-- ============ COMPRAS DE LA LINEA ============ --}}
                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-3">
                    Compras de {{ mb_strtolower($resumen['titulo']) }}
                </h3>
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-6 mb-6">
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Inversión</h4>
                        <p class="{{ $claseValor }}">Bs {{ number_format($resumen['inversion'], 2) }}</p>
                        <p class="mt-1 text-xs {{ $claseTendencia($resumen['tendenciaInversion']) }}">
                            {{ $resumen['tendenciaInversion'] >= 0 ? '↑' : '↓' }} {{ abs($resumen['tendenciaInversion']) }}%
                        </p>
                    </div>
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Unidades compradas</h4>
                        <p class="{{ $claseValor }}">{{ number_format($resumen['unidadesCompradas'], 0) }}</p>
                    </div>
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Costo promedio por unidad</h4>
                        <p class="{{ $claseValor }}">Bs {{ number_format($resumen['costoPromedio'], 2) }}</p>
                    </div>
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Pérdidas (bajas)</h4>
                        <p class="{{ $claseValor }} {{ $resumen['perdidas'] > 0 ? 'text-red-700 dark:text-red-300' : '' }}">Bs {{ number_format($resumen['perdidas'], 2) }}</p>
                    </div>
                </div>

                @if (!empty($resumen['porTipoVenta']))
                    {{-- Con el tipo de venta CONGELADO en la línea. --}}
                    <p class="-mt-3 mb-6 text-xs text-gray-500 dark:text-gray-400">
                        Por tipo de venta:
                        @foreach ($resumen['porTipoVenta'] as $fila)
                            <span class="whitespace-nowrap">
                                <span class="font-medium text-gray-700 dark:text-gray-300">{{ $fila->tipo_venta }}</span>
                                {{ $fila->unidades }} u · Bs {{ number_format((float) $fila->monto, 2) }}
                            </span>{{ !$loop->last ? ' · ' : '' }}
                        @endforeach
                    </p>
                @endif

                {{-- ============ RANKINGS ============ (del lado de la venta) --}}
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-6 mb-6">
                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Mejor vendedor</h4>
                        @if ($resumen['mejorVendedor'])
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $resumen['mejorVendedor']->nombre }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Bs {{ number_format($resumen['mejorVendedor']->monto, 2) }}
                            </p>
                        @else
                            <p class="mt-1 text-sm text-gray-400">Sin datos en el período</p>
                        @endif
                    </div>

                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }}">Mejor sucursal</h4>
                        @if ($resumen['mejorSucursal'])
                            <p class="mt-1 text-lg font-semibold text-gray-900 dark:text-white">
                                {{ $resumen['mejorSucursal']->nombre }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Bs {{ number_format($resumen['mejorSucursal']->monto, 2) }}
                            </p>
                        @else
                            <p class="mt-1 text-sm text-gray-400">Sin datos en el período</p>
                        @endif
                    </div>

                    <div class="{{ $claseTarjeta }}">
                        <h4 class="{{ $claseEtiqueta }} mb-2">Más vendidos</h4>
                        @forelse ($resumen['topArticulos'] as $articulo)
                            <div class="flex items-center justify-between text-sm py-1 border-b border-gray-100 dark:border-gray-700 last:border-0">
                                <span class="truncate text-gray-800 dark:text-gray-200" title="{{ $articulo->nombre }}">
                                    {{ $articulo->nombre }}
                                </span>
                                <span class="shrink-0 ml-2 text-gray-600 dark:text-gray-300">
                                    {{ $articulo->unidades }} u · Bs {{ number_format($articulo->monto, 2) }}
                                </span>
                            </div>
                        @empty
                            <p class="text-sm text-gray-400">Sin ventas en el período</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ============ TABLA ============
                 Mismas columnas en las tres pestañas. --}}
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl border border-gray-200 dark:border-gray-700 mb-6 overflow-hidden">
                <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200 p-4 border-b dark:border-gray-700">
                    Resumen Analítico &mdash; {{ $resumen['titulo'] }}
                </h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3 text-left">Periodo</th>
                                <th class="px-4 py-3 text-right">Unid. vend.</th>
                                <th class="px-4 py-3 text-right">Ingreso</th>
                                <th class="px-4 py-3 text-right">Costo</th>
                                <th class="px-4 py-3 text-right">Ganancia</th>
                                <th class="px-4 py-3 text-right">Unid. comp.</th>
                                <th class="px-4 py-3 text-right">Inversión</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @forelse ($tablaData as $fila)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                    <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                        {{ $fila->period_key }}
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ number_format($fila->unidades, 0) }}</td>
                                    <td class="px-4 py-3 text-right">Bs {{ number_format($fila->ingreso, 2) }}</td>
                                    <td class="px-4 py-3 text-right">Bs {{ number_format($fila->costo, 2) }}</td>
                                    <td class="px-4 py-3 text-right {{ $fila->ganancia >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                        Bs {{ number_format($fila->ganancia, 2) }}
                                    </td>
                                    <td class="px-4 py-3 text-right">{{ number_format($fila->comp_unidades, 0) }}</td>
                                    <td class="px-4 py-3 text-right">Bs {{ number_format($fila->inversion, 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-gray-400">
                                        No hay movimientos de {{ mb_strtolower($resumen['titulo']) }} en este período.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="p-4">
                    {{ $tablaData->links('livewire.custom-pagination') }}
                </div>
            </div>

            {{-- ============ GRAFICO ============
                 SIEMPRE renderizado, FUERA de los @if de pestaña. El JS captura
                 getElementById('gananciaChart') una sola vez al arrancar: si el
                 canvas viviera dentro de un @if, al no estar activa esa pestaña
                 devolveria null y el TypeError abortaria todo el listener de
                 livewire:initialized. La pestaña solo cambia los DATOS. --}}
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl border border-gray-200 dark:border-gray-700 p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                    <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        Evolución &mdash; {{ $resumen['titulo'] }}
                    </h2>
                    <div class="flex gap-1 bg-gray-100 dark:bg-gray-700 p-1 rounded-lg">
                        <button wire:click="$set('chartMetric', 'totales')"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $chartMetric === 'totales' ? 'bg-white dark:bg-gray-600 text-brand-600 dark:text-brand-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                            💰 Totales
                        </button>
                        <button wire:click="$set('chartMetric', 'cantidades')"
                            class="px-4 py-2 text-sm font-medium rounded-md transition-colors {{ $chartMetric === 'cantidades' ? 'bg-white dark:bg-gray-600 text-brand-600 dark:text-brand-400 shadow-sm' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200' }}">
                            # Cantidades
                        </button>
                    </div>
                </div>

                <div wire:ignore class="min-h-[350px] sm:min-h-[450px] flex items-center justify-center relative">
                    <canvas id="gananciaChart" class="w-full h-full absolute inset-0"></canvas>

                    <div id="chartLoadingMessage" class="absolute inset-0 flex items-center justify-center"
                        style="display: none;">
                        <div class="text-center">
                            <svg class="animate-spin h-8 w-8 text-brand-600 mx-auto mb-2"
                                xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                    stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor"
                                    d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                            </svg>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Cargando gráfico...</p>
                        </div>
                    </div>

                    <div id="noDataMessage" class="absolute inset-0 flex items-center justify-center"
                        style="display: none;">
                        <p class="text-sm text-gray-400">No hay datos para mostrar en este período.</p>
                    </div>
                </div>
            </div>

        </div>
    </div>
    @push('js')
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

        <script>
            document.addEventListener('livewire:initialized', () => {
                const ctx = document.getElementById('gananciaChart').getContext('2d');
                const chartLoadingMessageEl = document.getElementById('chartLoadingMessage');
                const noDataMessageEl = document.getElementById('noDataMessage');
                const canvasEl = document.getElementById('gananciaChart');

                let gananciaChartInstance = null;


                let currentMetric = @json($this->chartMetric);

                const chartConfig = {
                    type: 'line',
                    data: {
                        labels: [],
                        datasets: []
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: {
                            duration: 600,
                            easing: 'easeOutQuart'
                        },
                        scales: {
                            y: {
                                beginAtZero: false,
                                ticks: {
                                    callback: function(value) {
                                        if (currentMetric === 'totales') {
                                            return 'Bs ' + value.toLocaleString('es-BO', {
                                                minimumFractionDigits: 2,
                                                maximumFractionDigits: 2
                                            });
                                        } else {
                                            return value.toLocaleString('es-BO');
                                        }
                                    },
                                    color: '#4B5563',
                                    font: {
                                        size: 11,
                                        family: 'Inter, sans-serif'
                                    },
                                    padding: 5,
                                },
                                grid: {
                                    color: '#E5E7EB',
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 0,
                                    color: '#4B5563',
                                    font: {
                                        size: 11,
                                        family: 'Inter, sans-serif'
                                    },
                                    padding: 10,
                                },
                                grid: {
                                    display: false,
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                position: 'bottom',
                                align: 'center',
                                labels: {
                                    usePointStyle: true,
                                    pointStyle: 'rectRounded',
                                    padding: 25,
                                    color: '#1F2937',
                                    font: {
                                        size: 12,
                                        family: 'Inter, sans-serif',
                                        weight: '500'
                                    },
                                    filter: function(item, chart) {
                                        return !item.hidden;
                                    }
                                }
                            },
                            tooltip: {
                                enabled: true,
                                mode: 'index',
                                intersect: false,
                                backgroundColor: 'rgba(17, 24, 39, 0.9)',
                                titleFont: {
                                    size: 14,
                                    family: 'Inter, sans-serif',
                                    weight: 'bold'
                                },
                                bodyFont: {
                                    size: 12,
                                    family: 'Inter, sans-serif'
                                },
                                padding: 12,
                                cornerRadius: 6,
                                displayColors: true,
                                boxPadding: 4,
                                titleColor: '#F9FAFB',
                                bodyColor: '#D1D5DB',
                                callbacks: {
                                    title: function(tooltipItems) {
                                        return tooltipItems[0].label;
                                    },
                                    label: function(context) {
                                        let label = context.dataset.label || '';
                                        if (label) {
                                            label += ': ';
                                        }
                                        if (context.parsed.y !== null) {
                                            if (currentMetric === 'totales') {
                                                label += 'Bs ' + context.parsed.y.toLocaleString('es-BO', {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2
                                                });
                                            } else {
                                                label += context.parsed.y.toLocaleString('es-BO');
                                            }
                                        }
                                        return label;
                                    }
                                }
                            }
                        },
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        elements: {
                            line: {
                                borderWidth: 2.5,
                                tension: 0.4
                            },
                            point: {
                                radius: 0,
                                hoverRadius: 6,
                                hitRadius: 20,
                                backgroundColor: 'white',
                                borderWidth: 2,
                            }
                        },
                        onHover: (event, chartElement) => {
                            event.native.target.style.cursor = chartElement[0] ? 'pointer' : 'default';
                        }
                    }
                };

                function showLoadingState(isLoading) {
                    if (isLoading) {
                        canvasEl.style.display = 'none';
                        noDataMessageEl.style.display = 'none';
                        chartLoadingMessageEl.style.display = 'block';
                    } else {
                        chartLoadingMessageEl.style.display = 'none';
                    }
                }

                function showNoDataMessage(show) {
                    if (show) {
                        canvasEl.style.display = 'none';
                        noDataMessageEl.style.display = 'block';
                    } else {
                        noDataMessageEl.style.display = 'none';
                        canvasEl.style.display = 'block';
                    }
                }

                function initializeOrUpdateChart(chartData, metric) {
                    try {
                        showLoadingState(false);
                        currentMetric = metric || currentMetric;

                        if (!chartData || !chartData.labels || chartData.labels.length === 0) {
                            showNoDataMessage(true);
                            if (gananciaChartInstance) {
                                gananciaChartInstance.destroy();
                                gananciaChartInstance = null;
                            }
                            return;
                        }

                        // Se oculta por una marca que viaja en el propio dataset, no
                        // por indice. Antes era `datasets[2]`, que en la pestana de
                        // Ventas (Celulares | Repuestos | Accesorios) habria escondido
                        // Accesorios en vez de una serie de ganancia.
                        chartData.datasets.forEach(ds => {
                            ds.hidden = currentMetric === 'cantidades' && ds.hideOnCantidades === true;
                        });

                        showNoDataMessage(false);

                        if (gananciaChartInstance) {
                            gananciaChartInstance.data.labels = chartData.labels;
                            gananciaChartInstance.data.datasets = chartData.datasets;
                            gananciaChartInstance.update();
                        } else {
                            chartConfig.data = chartData;
                            gananciaChartInstance = new Chart(ctx, chartConfig);
                        }
                    } catch (error) {
                        console.error('Error updating chart:', error);
                        showLoadingState(false);
                        showNoDataMessage(true);
                        if (gananciaChartInstance) {
                            gananciaChartInstance.destroy();
                            gananciaChartInstance = null;
                        }
                    }
                }

                const initialChartData = @json($this->chartData);
                showLoadingState(true);
                setTimeout(() => {
                    try {
                        initializeOrUpdateChart(initialChartData, currentMetric);
                    } catch (error) {
                        console.error('Initial chart load error:', error);
                        showLoadingState(false);
                        showNoDataMessage(true);
                    }
                }, 50);

                window.addEventListener('updateChart', event => {
                    try {
                        const newChartData = event.detail.data;
                        showLoadingState(true);

                        setTimeout(() => {
                            try {
                                initializeOrUpdateChart(newChartData, newChartData.metric);
                            } catch (error) {
                                console.error('Chart update error:', error);
                                showLoadingState(false);
                                showNoDataMessage(true);
                            }
                        }, 50);
                    } catch (error) {
                        console.error('Event processing error:', error);
                        showLoadingState(false);
                    }
                });

                Livewire.hook('element.updating', (fromEl, toEl, component) => {
                    try {
                        if (fromEl.hasAttribute('wire:model.live') || fromEl.hasAttribute(
                                'wire:model.live.debounce.300ms')) {
                            showLoadingState(true);
                        }
                    } catch (error) {
                        console.error('Update hook error:', error);
                        showLoadingState(false);
                    }
                });

                Livewire.hook('message.failed', (message, component) => {
                    showLoadingState(false);
                    showNoDataMessage(true);
                });

                Livewire.hook('message.processed', (message, component) => {
                    setTimeout(() => {
                        showLoadingState(false);
                    }, 1000);
                });
            });
        </script>
    @endpush
</div>
