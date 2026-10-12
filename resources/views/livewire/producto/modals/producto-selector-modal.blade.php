<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="5xl">
            <x-slot name="title">
                <p class="text-center">Seleccionar Productos</p>
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <x-label>Buscar</x-label>
                        <div class="mt-1 flex gap-2" data-escaner>
                            <x-input type="text" class="w-full" placeholder="IMEI, SKU, modelo, color..."
                                wire:model.live.debounce.500ms="search"
                                x-on:keydown.enter.prevent="$wire.marcarPorCodigo($el.value); $el.value = ''"
                                autocomplete="off" />
                            <x-boton-escaner continuo />
                        </div>
                    </div>

                    <div>
                        <x-label>Modelo</x-label>
                        <select wire:model.live="filtroModelo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            <option value="">Todos</option>
                            @foreach ($modelos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-label>Sucursal</x-label>
                        <select wire:model.live="filtroSucursal"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            <option value="">Todas</option>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3 flex items-center justify-between text-sm">
                    <span class="text-gray-600 dark:text-gray-300">
                        <span class="font-semibold">{{ count($seleccionados) }}</span> seleccionado(s)
                        @if ($productos)
                            &middot; {{ $productos->total() }} resultado(s)
                        @endif
                    </span>
                    <div class="space-x-3">
                        @if (count($seleccionados))
                            <button type="button" wire:click="limpiarSeleccion"
                                class="text-red-600 hover:underline">Limpiar seleccion</button>
                        @endif
                        <button type="button" wire:click="limpiarFiltros"
                            class="text-brand-600 hover:underline">Limpiar filtros</button>
                    </div>
                </div>

                <div
                    class="overflow-x-auto overflow-y-auto max-h-80 mt-3 border border-gray-200 dark:border-gray-700 shadow-sm rounded-lg">
                    <table class="min-w-full text-xs relative">
                        <thead class="sticky top-0 bg-gray-100 dark:bg-gray-700 shadow-sm z-10">
                            <tr>
                                <th class="p-2 border-b w-10"></th>
                                <th class="p-2 border-b text-left">Modelo</th>
                                <th class="p-2 border-b text-left">IMEI</th>
                                <th class="p-2 border-b text-center">Capacidad</th>
                                <th class="p-2 border-b text-center">Version</th>
                                <th class="p-2 border-b text-left">Color</th>
                                <th class="p-2 border-b text-center">Grado</th>
                                <th class="p-2 border-b text-center">Bateria</th>
                                <th class="p-2 border-b text-left">Sucursal</th>
                                <th class="p-2 border-b text-center">Estado</th>
                                <th class="p-2 border-b text-right">
                                    Precio ({{ $this->esVendedor() ? 'Vendedor' : 'Cliente' }})
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($productos as $p)
                                @php
                                    $marcado = in_array((string) $p->id, array_map('strval', $seleccionados), true);
                                @endphp
                                <tr wire:key="prod-{{ $p->id }}"
                                    class="{{ $marcado ? 'bg-brand-50 dark:bg-brand-900' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                    <td class="p-2 border text-center">
                                        <x-checkbox wire:model.live="seleccionados" value="{{ $p->id }}" />
                                    </td>
                                    <td class="p-2 border">{{ $p->modelo?->nombre ?? 'Sin modelo' }}</td>
                                    <td class="p-2 border font-mono">{{ $p->imei }}</td>
                                    <td class="p-2 border text-center">{{ $p->almacenamiento }}</td>
                                    <td class="p-2 border text-center">{{ $p->version ?: '-' }}</td>
                                    <td class="p-2 border">{{ $p->color }}</td>
                                    <td class="p-2 border text-center">{{ $p->estado_grado ?: '-' }}</td>
                                    <td class="p-2 border text-center">
                                        {{-- Por debajo de 80% la bateria es un dato que conviene ver antes de vender. --}}
                                        @if ($p->bateria_porcentaje < 80)
                                            <span class="text-amber-600 font-semibold">{{ $p->bateria_porcentaje }}%</span>
                                        @else
                                            <span class="text-green-600">{{ $p->bateria_porcentaje }}%</span>
                                        @endif
                                    </td>
                                    <td class="p-2 border">{{ $p->sucursal?->nombre ?? '-' }}</td>
                                    <td class="p-2 border text-center">
                                        {{-- Aqui solo llegan equipos disponibles (Inventario):
                                             lo que distingue la oferta es el tipo de venta. --}}
                                        @if ($p->tipo_venta === App\Enums\ProductoTipoVenta::Oferta->value)
                                            <span class="text-purple-600 font-semibold">Oferta</span>
                                        @else
                                            {{ $p->estado }}
                                        @endif
                                    </td>
                                    <td class="p-2 border text-right">
                                        {{ number_format($this->esVendedor() ? $p->precio_vendedor : $p->precio_cliente, 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="11" class="p-4 text-center text-gray-400">
                                        No se encontraron productos con esos filtros.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($productos && $productos->lastPage() > 1)
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <button type="button" wire:click="irAPagina({{ $productos->currentPage() - 1 }})"
                            @disabled($productos->onFirstPage())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">
                            Anterior
                        </button>
                        <span class="text-gray-600 dark:text-gray-300">
                            Pagina {{ $productos->currentPage() }} de {{ $productos->lastPage() }}
                        </span>
                        <button type="button" wire:click="irAPagina({{ $productos->currentPage() + 1 }})"
                            @disabled(!$productos->hasMorePages())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">
                            Siguiente
                        </button>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="agregarSeleccionados" wire:loading.attr="disabled"
                    :disabled="count($seleccionados) === 0">
                    Agregar {{ count($seleccionados) }} seleccionado(s)
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
