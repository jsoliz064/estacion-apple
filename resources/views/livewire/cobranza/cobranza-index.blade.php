<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Cobranzas</h2>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-amber-100 dark:bg-amber-900 p-5 rounded-2xl shadow border border-amber-200 dark:border-amber-700">
            <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Por cobrar</h3>
            <p class="mt-1 text-2xl font-semibold text-amber-900 dark:text-amber-100">Bs {{ number_format((float) $resumen->saldo, 2) }}</p>
        </div>
        <div class="bg-gray-100 dark:bg-gray-900 p-5 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Ventas a crédito</h3>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ (int) $resumen->ventas }}</p>
        </div>
        <div class="bg-gray-100 dark:bg-gray-900 p-5 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Clientes con deuda</h3>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ (int) $resumen->clientes }}</p>
        </div>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-2">
        @livewire('cobranza.cobranza-table')
    </div>

    @livewire('cobranza.modals.cobro-modal')
</div>
