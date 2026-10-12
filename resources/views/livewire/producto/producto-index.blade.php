<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Productos</h2>

    @can('producto.reporte')
        <x-collapse-card title="Valor de Inventario" :open="false">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-6"
                wire:loading.class="opacity-50 animate-pulse" wire:target="applyFilters">

                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Productos No Vendidos</h3>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                        {{ number_format($totalProductos, 0) }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Costo Total de Inventario</h3>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                        Bs {{ number_format($totalInventario, 2) }}
                    </p>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Inventario a precio de venta</h3>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                        Bs {{ number_format($totalInventarioVendido, 2) }}
                    </p>
                </div>

            </div>
        </x-collapse-card>
    @endcan

    <x-collapse-card title="Resumen por Sucursales" :open="false">
        <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach ($sucursalesResumen as $sucursal)
                <div class="p-4 bg-white dark:bg-gray-800 shadow rounded-lg">
                    <h3 class="font-semibold text-lg text-gray-700 dark:text-gray-200 mb-2">
                        {{ $sucursal['nombre'] }}
                    </h3>
                    <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                        @foreach (\App\Enums\ProductoEstado::cases() as $estado)
                            @if (!in_array($estado->value, \App\Enums\ProductoEstado::vendidos(), true))
                                <li class="flex justify-between">
                                    <span>{{ $estado->label() }}</span>
                                    <span class="font-bold">
                                        {{ $sucursal['resumen'][$estado->value] ?? 0 }}
                                    </span>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </x-collapse-card>

    <x-collapse-card title="Modelos de Productos" :open="false">
        @livewire('producto.producto-filter-model')
    </x-collapse-card>

    @can('producto.cambiar-sucursal')
        <div class="my-2">
            <x-primary-button wire:click="openProductoEditSucursalModal()">
                Cambiar productos de sucursal
            </x-primary-button>
        </div>
    @endcan

    <div class="my-2">
        <button wire:click="exportProductosExcel" wire:loading.attr="disabled"
            class="my-2 inline-flex items-center px-3 py-1.5 text-sm font-medium 
           bg-green-600 text-white rounded-lg shadow hover:bg-green-700 
           disabled:opacity-50 disabled:cursor-not-allowed transition"
            title="Exportar excel">
            <i class="fas fa-file-excel mr-2"></i>
            Exportar Productos

            <!-- Spinner -->
            <div wire:loading wire:target="exportProductosExcel"
                class="ml-2 inline-block h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin">
            </div>
        </button>

        @can('producto.estado-masivo')
        <button wire:click="openProductoEstadoMasivoModal()" wire:loading.attr="disabled"
            class="my-2 inline-flex items-center px-3 py-1.5 text-sm font-medium 
           bg-yellow-600 text-white rounded-lg shadow hover:bg-yellow-700 
           disabled:opacity-50 disabled:cursor-not-allowed transition"
            title="Cambiar estado o tipo de venta de varios productos a la vez">
            Cambio Masivo

            <!-- Spinner -->
            <div wire:loading wire:target="openProductoEstadoMasivoModal"
                class="ml-2 inline-block h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin">
            </div>
        </button>
        @endcan
    </div>

    <div class="mt-4">
        @livewire('producto.producto-table')
    </div>
    @livewire('producto.modals.producto-estado-modal')
    @livewire('producto.modals.producto-fotos-modal')
    @livewire('cliente.modals.cliente-selector-modal')
@livewire('cliente.modals.cliente-create-modal')
    @livewire('compra-lote.modals.compra-lote-producto-edit-modal')
    @livewire('producto.modals.producto-destroy-modal')
    @livewire('producto.modals.producto-baja-modal')
    @livewire('producto.modals.producto-regalos-modal')
    @livewire('producto.modals.producto-reparacion-cliente-modal')
    @livewire('producto.modals.producto-edit-sucursal-modal')
    @livewire('tecnico-producto.modals.reparacion-edit-modal')
    @livewire('producto.modals.producto-estado-masivo-modal')
    @livewire('reserva.modals.reserva-create-modal')
    @livewire('reserva.modals.reserva-cancelar-modal')
    @livewire('reparacion.modals.repuestos-reparacion-modal')
</div>
