<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Cuentas por pagar</h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-amber-100 dark:bg-amber-900 p-5 rounded-2xl shadow border border-amber-200 dark:border-amber-700">
            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Por pagar</h3>
            <p class="mt-1 text-2xl font-semibold text-amber-900 dark:text-amber-100">Bs {{ number_format((float) $resumen->saldo, 2) }}</p>
        </div>
        <div class="bg-gray-100 dark:bg-gray-900 p-5 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Compras con saldo</h3>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ (int) $resumen->compras }}</p>
        </div>
        <div class="bg-gray-100 dark:bg-gray-900 p-5 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Proveedores</h3>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ (int) $resumen->proveedores }}</p>
        </div>
    </div>

    @if ($porProveedor->isNotEmpty())
        <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Por proveedor</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">Proveedor</th>
                            <th class="p-2 text-right">Compras</th>
                            <th class="p-2 text-right">Desde</th>
                            <th class="p-2 text-right">Se le debe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porProveedor as $p)
                            <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="cxp-prov-{{ $p->id }}">
                                <td class="p-2">
                                    @can('proveedor.historial')
                                        <a href="{{ route('proveedores.historial', $p->id) }}" class="text-brand-600 hover:underline dark:text-brand-400">{{ $p->nombre }}</a>
                                    @else
                                        {{ $p->nombre }}
                                    @endcan
                                </td>
                                <td class="p-2 text-right">{{ $p->compras }}</td>
                                <td class="p-2 text-right">{{ \Carbon\Carbon::parse($p->desde)->format('d/m/Y') }}</td>
                                <td class="p-2 text-right font-semibold">Bs {{ number_format((float) $p->saldo, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-2">
        @livewire('cuenta-pagar.cuenta-pagar-table')
    </div>

    @livewire('cuenta-pagar.modals.pago-proveedor-modal')
</div>
