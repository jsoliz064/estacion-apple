<div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">
        {{ $this->tipoEnum()->plural() }}
    </h2>

    <div class="grid grid-cols-2 gap-3 sm:gap-6 mb-6" wire:loading.class="opacity-50 animate-pulse"
        wire:target="applyFilters">
        <div class="bg-green-100 dark:bg-green-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-xs sm:text-sm font-medium text-green-700 dark:text-green-300">Total de artículos</h3>
            <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                {{ $totalArticulos }}
            </p>
        </div>

        <div class="bg-red-100 dark:bg-red-800 p-4 sm:p-6 rounded-2xl shadow-lg border border-red-200 dark:border-red-700">
            <h3 class="text-xs sm:text-sm font-medium text-red-700 dark:text-red-300">Por agotarse</h3>
            <p class="mt-1 text-xl sm:text-2xl font-semibold tracking-tight text-red-900 dark:text-red-100">
                {{ $totalBajoStock }}
            </p>
        </div>
    </div>

    <x-collapse-card title="Resumen por Sucursales" :open-on-desktop="true">
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4"
            wire:loading.class="opacity-50 animate-pulse" wire:target="applyFilters">
            @foreach ($sucursalesResumen as $sucursal)
                <div class="p-4 bg-gray-50 dark:bg-gray-900 shadow rounded-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="font-semibold text-sm sm:text-base text-gray-700 dark:text-gray-200 mb-2 truncate">
                        {{ $sucursal['nombre'] }}
                    </h3>
                    <ul class="text-sm text-gray-600 dark:text-gray-300 space-y-1">
                        <li class="flex justify-between">
                            <span>Unidades</span>
                            {{-- Los negativos en rojo: esconderlos seria esconder justo
                                 lo que hay que corregir. Clases literales en las dos ramas. --}}
                            <span class="{{ $sucursal['unidades'] < 0 ? 'font-bold text-red-600 dark:text-red-400' : 'font-bold' }}">
                                {{ $sucursal['unidades'] }}
                            </span>
                        </li>
                        <li class="flex justify-between">
                            <span>Artículos</span>
                            <span class="font-bold">{{ $sucursal['articulos'] }}</span>
                        </li>
                    </ul>
                </div>
            @endforeach
        </div>
    </x-collapse-card>

    <x-collapse-card title="Filtros" :open-on-desktop="true">
        <x-slot name="actions">
            @if (!empty($selectedModelos) || !empty($selectedCategorias) || (bool) $selectedMinStock)
                <button wire:click="resetFilters"
                    class="px-3 py-1 text-sm font-medium text-white bg-red-600 rounded-lg shadow-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    Limpiar filtros
                </button>
            @endif
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-2">
            <div>
                <label for="modelos" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                    {{ $this->esRepuesto() ? 'Modelo' : 'Compatible con' }}
                </label>
                <select wire:model.live="selectedModelos" id="modelos" multiple
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white h-30">
                    @foreach ($modelos as $modelo)
                        <option value="{{ $modelo->id }}">{{ $modelo->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="categorias" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Categorías</label>
                <select wire:model.live="selectedCategorias" id="categorias" multiple
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white h-30">
                    @foreach ($categorias as $categoria)
                        <option value="{{ $categoria->id }}">{{ $categoria->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="stock" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Control de stock</label>
                <select wire:model.live="selectedMinStock" id="stock"
                    class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    <option value="0">Todos</option>
                    <option value="1">Por agotarse</option>
                </select>
            </div>
        </div>
    </x-collapse-card>

    <div class="flex flex-wrap gap-2 mb-2">
        @can($this->permiso() . '.create')
            <x-primary-button wire:click="openArticuloCreateModal()">
                Crear {{ mb_strtolower($this->tipoEnum()->label()) }}
            </x-primary-button>
        @endcan
    </div>

    <div class="mt-4">
        {{-- El tipo por parametro y no por el evento filtersUpdated, que en el
             primer render no llega a nadie. La key ata la instancia al tipo. --}}
        @livewire('articulo.articulo-table', ['tipo' => $tipo], key('tabla-' . $tipo))
    </div>

    @livewire('articulo.modals.articulo-form-modal', ['tipo' => $tipo])
    @livewire('articulo.modals.articulo-destroy-modal', ['tipo' => $tipo])
    @livewire('articulo.modals.stock-transferencia-modal')
    @livewire('articulo.modals.stock-baja-modal')
</div>
