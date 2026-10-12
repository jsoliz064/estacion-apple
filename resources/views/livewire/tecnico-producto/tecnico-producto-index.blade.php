<div>
    <h2 class="text-center text-lg sm:text-2xl font-bold text-gray-800 dark:text-white mb-3 sm:mb-5">
        Reparaciones de {{ $tecnico->nombre }}
    </h2>

    {{-- Una sola fila compacta: pendientes + las tres cifras de comision. --}}
    <x-comision-cifras :cifras="$cifras" columnas="grid-cols-2 sm:grid-cols-4" class="mb-4">
        <div class="border-l-4 px-2 py-1.5 sm:p-3 rounded-lg shadow-sm dark:bg-gray-800 min-w-0 bg-brand-50 border-brand-500">
            <p class="text-[11px] sm:text-sm font-medium truncate text-brand-900 dark:text-brand-200">Reparaciones pendientes</p>
            <p class="text-sm sm:text-xl font-bold text-brand-800 dark:text-brand-100">{{ $cant_productos_pendientes }}</p>
            <p class="hidden sm:block text-xs truncate text-brand-700 dark:text-brand-300">Sin terminar</p>
        </div>
    </x-comision-cifras>

    @can('comision.liquidar')
        <x-primary-button wire:click="liquidar">
            Liquidar comisiones
        </x-primary-button>
    @endcan
    @can('tecnico.productos.terminar')
        <button wire:click="openTecnicoTerminarModal({{ $tecnico->id }})" class="mt-2 inline-flex items-center px-4 py-2 bg-gray-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150">
             Terminar Reparaciones
        </button>
    @endcan
    @can('tecnico.productos.exportar')
        <button wire:click="exportProductosExcel" wire:loading.attr="disabled"
            class="my-2 inline-flex items-center px-3 py-1.5 text-sm font-medium 
           bg-green-600 text-white rounded-lg shadow hover:bg-green-700 
           disabled:opacity-50 disabled:cursor-not-allowed transition"
            title="Exportar excel">
            <i class="fas fa-file-excel mr-2"></i>
            Exportar Productos Pendientes

            <!-- Spinner -->
            <div wire:loading wire:target="exportProductosExcel"
                class="ml-2 inline-block h-4 w-4 border-2 border-white border-t-transparent rounded-full animate-spin">
            </div>
        </button>
    @endcan
    <div class="mt-4">
        @livewire('tecnico-producto.tecnico-producto-table', ['tecnico_id' => $tecnico->id])
    </div>

    @livewire('tecnico-producto.modals.reparacion-edit-modal')
    @livewire('tecnico-producto.modals.reparacion-show-modal')
    @can('comision.liquidar')
        @livewire('comision.modals.comision-liquidar-modal')
    @endcan
    @livewire('tecnico-producto.modals.tecnico-terminar-modal')
    @livewire('reparacion.modals.repuestos-reparacion-modal')
</div>
