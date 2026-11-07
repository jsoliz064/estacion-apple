<div class="m-3 mt-5">
    <div class="text-center">
        <h4 class="text-lg font-semibold text-gray-600 dark:text-gray-200">Inventario por modelo</h4>
    </div>

    <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:max-w-2xl">
        <div>
            <x-label for="inventarioModeloId" value="Modelo" />
            <x-select-buscador wire:model.live="modeloId" id="inventarioModeloId"
                :options="$modelos->pluck('nombre', 'id')" placeholder="Todos" />
        </div>
        <div>
            <x-label for="inventarioSucursalId" value="Sucursal" />
            <select wire:model.live="sucursalId" id="inventarioSucursalId"
                class="mt-1 block w-full h-10 rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200">
                <option value="">Todas</option>
                @foreach ($sucursales as $sucursal)
                    <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="mt-4" wire:loading.class="opacity-50">
        @if (count($bloques) === 0)
            <p class="rounded-lg bg-white p-4 text-center text-sm text-gray-500 shadow dark:bg-gray-800 dark:text-gray-400">
                No hay equipos en inventario con esos filtros.
            </p>
        @else
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                @foreach ($bloques as $bloque)
                    <div wire:key="inventario-sucursal-{{ $loop->index }}-{{ $bloque['nombre'] }}"
                        class="rounded-lg bg-white p-4 shadow dark:bg-gray-800">
                        <div class="mb-2 flex items-baseline justify-between gap-2">
                            <h3 class="text-lg font-semibold text-gray-700 dark:text-gray-200">{{ $bloque['nombre'] }}</h3>
                            <span class="text-sm font-bold tabular-nums text-gray-700 dark:text-gray-200">{{ $bloque['total'] }}</span>
                        </div>
                        <ul class="max-h-80 space-y-1 overflow-y-auto text-sm text-gray-600 dark:text-gray-300">
                            @foreach ($bloque['lineas'] as $linea)
                                <li class="flex justify-between gap-2">
                                    <span>{{ $linea['texto'] }}</span>
                                    <span class="font-bold tabular-nums">{{ $linea['total'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>

            @if (count($bloques) > 1)
                <p class="mt-3 text-right text-sm font-semibold text-gray-700 dark:text-gray-200">
                    Total: {{ array_sum(array_column($bloques, 'total')) }}
                </p>
            @endif
        @endif
    </div>
</div>
