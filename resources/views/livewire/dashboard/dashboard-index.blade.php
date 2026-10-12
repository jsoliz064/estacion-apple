<div>
    @livewire('producto.modals.producto-estado-modal')

    <div class="h-full">

        <!-- Statistics Cards -->
        {{-- Dos por fila en el celular, tres en tablet y seis en pantalla ancha.
             Oferta va dentro de Inventario (es un subconteo, no una cifra aparte) y
             lo que antes se amontonaba en la tarjeta de reparacion tiene la suya. --}}
        <div class="grid grid-cols-2 lg:grid-cols-3 2xl:grid-cols-6 gap-3 p-3">

            <x-tarjeta-dato titulo="Ventas del día" :valor="'Bs ' . number_format($ventas_dia, 2)"
                icono="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                {{ $ventas_dia_cant }} venta(s)
            </x-tarjeta-dato>

            <x-tarjeta-dato titulo="Ventas del mes" :valor="'Bs ' . number_format($ventas_mes, 2)"
                icono="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6">
                {{ $ventas_mes_cant }} venta(s)
            </x-tarjeta-dato>

            <x-tarjeta-dato titulo="En inventario" :valor="$productos_inventario"
                icono="M20 12v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6m16 0H4m16 0l-1.5-9h-11L4 12m5 4h6">
                <span class="inline-block rounded bg-purple-500 px-1.5">{{ $productos_oferta }} en oferta</span>
            </x-tarjeta-dato>

            <x-tarjeta-dato titulo="En reparación" :valor="$productos_reparacion"
                icono="M15.232 5.232a3.75 3.75 0 01-5.304 5.304l-5.46 5.46a2.121 2.121 0 103 3l5.46-5.46a3.75 3.75 0 015.304-5.304l-3-3z">
                {{ $productos_reserva }} reservados · {{ $productos_credito }} a crédito
            </x-tarjeta-dato>

            @can('cobranza.index')
                <x-tarjeta-dato titulo="Por cobrar" :valor="'Bs ' . number_format($por_cobrar, 2)"
                    icono="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z">
                    <a href="{{ route('cobranzas') }}" class="underline">{{ $ventas_credito }} venta(s) a crédito</a>
                </x-tarjeta-dato>
            @endcan

            @canany(['cuenta-pagar.index', 'comision.index'])
                <x-tarjeta-dato titulo="Por pagar"
                    :valor="auth()->user()->can('cuenta-pagar.index') ? 'Bs ' . number_format($por_pagar, 2) : '—'"
                    icono="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z">
                    @can('cuenta-pagar.index')
                        <a href="{{ route('cuentas-por-pagar') }}" class="block underline">A proveedores</a>
                    @endcan
                    @can('comision.index')
                        <a href="{{ route('comisiones') }}" class="block underline">Comisiones: Bs {{ number_format($comisiones_por_pagar, 2) }}</a>
                    @endcan
                </x-tarjeta-dato>
            @endcanany

        </div>
        <!-- ./Statistics Cards -->

        <div class="m-3 mt-5">
            <div class="text-center">
                <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-200">Últimos Productos Vendidos</h4>
            </div>
            @livewire('venta.venta-producto-table')
        </div>

        <div class="m-3 mt-5">
            <div class="text-center">
                <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-200">Productos que no estan en inventario
                </h4>
            </div>
            @livewire('dashboard.producto-dashboard-table')
        </div>

    </div>
    @livewire('reparacion.modals.repuestos-reparacion-modal')
</div>
