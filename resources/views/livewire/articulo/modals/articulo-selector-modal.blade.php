<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="4xl">
            <x-slot name="title">
                <p class="text-center">Seleccionar repuestos o accesorios</p>
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="mt-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                        <x-label>Tipo</x-label>
                        <select wire:model.live="tipo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            @foreach (\App\Enums\ArticuloTipo::cases() as $t)
                                <option value="{{ $t->value }}">{{ $t->plural() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-label>Buscar</x-label>
                        <div class="mt-1 flex gap-2" data-escaner>
                            <x-input type="text" class="w-full" placeholder="Nombre, SKU o código..."
                                wire:model.live.debounce.500ms="search"
                                x-on:keydown.enter.prevent="$wire.marcarPorCodigo($el.value); $el.value = ''"
                                autocomplete="off" />
                            <x-boton-escaner continuo />
                        </div>
                    </div>
                    <div>
                        <x-label>Categoría</x-label>
                        <select wire:model.live="filtroCategoria"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            <option value="">Todas</option>
                            @foreach ($categorias as $c)
                                <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <x-label>{{ $tipoEnum === \App\Enums\ArticuloTipo::Repuesto ? 'Modelo' : 'Compatible con' }}</x-label>
                        <select wire:model.live="filtroModelo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            <option value="">Todos</option>
                            @foreach ($modelos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold">{{ count($seleccionados) }}</span> seleccionado(s)
                    @if ($articulos)
                        &middot; {{ $articulos->total() }} resultado(s)
                    @endif
                </div>

                <div class="overflow-x-auto overflow-y-auto max-h-80 mt-3 border border-gray-200 dark:border-gray-700 shadow-sm rounded-lg">
                    <table class="min-w-full text-xs relative">
                        <thead class="sticky top-0 bg-gray-100 dark:bg-gray-700 shadow-sm z-10">
                            <tr>
                                <th class="p-2 border-b w-10"></th>
                                <th class="p-2 border-b text-left">Artículo</th>
                                <th class="p-2 border-b text-left">SKU</th>
                                <th class="p-2 border-b text-left">Categoría</th>
                                <th class="p-2 border-b text-right">Precio</th>
                                <th class="p-2 border-b text-center">{{ $sucursalId ? 'Stock en ' . $nombreSucursal : 'Stock total' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($articulos as $a)
                                @php
                                    $stock = $sucursalId ? $a->stockEn($sucursalId) : (int) $a->cantidad;
                                    $bloqueado = $soloConStock && $stock <= 0;
                                    $marcado = in_array($tipoEnum->value . ':' . $a->id, $seleccionados, true);
                                    // Clases LITERALES en cada rama: no hay safelist.
                                    if ($bloqueado) {
                                        $claseFila = 'opacity-50 cursor-not-allowed';
                                    } elseif ($marcado) {
                                        $claseFila = 'bg-brand-50 dark:bg-brand-900 cursor-pointer';
                                    } else {
                                        $claseFila = 'hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer';
                                    }
                                @endphp
                                {{-- Toda la fila selecciona; el checkbox es decorativo (sin wire:model). --}}
                                <tr wire:key="sel-{{ $tipoEnum->value }}-{{ $a->id }}" class="{{ $claseFila }}"
                                    @if (!$bloqueado) wire:click="alternar({{ $a->id }})" @endif
                                    title="{{ $bloqueado ? 'Sin stock en ' . $nombreSucursal : '' }}">
                                    <td class="p-2 border text-center">
                                        <x-checkbox class="pointer-events-none" :checked="$marcado" :disabled="$bloqueado" />
                                    </td>
                                    <td class="p-2 border">
                                        {{ $a->nombre }}
                                        @if ($tipoEnum === \App\Enums\ArticuloTipo::Repuesto && $a->modelo)
                                            <span class="text-gray-500">· {{ $a->modelo->nombre }}</span>
                                        @endif
                                    </td>
                                    <td class="p-2 border">{{ $a->sku ?: '—' }}</td>
                                    <td class="p-2 border">{{ $a->categoria?->nombre ?? '—' }}</td>
                                    <td class="p-2 border text-right">Bs {{ number_format((float) $a->precio, 2) }}</td>
                                    <td class="p-2 border text-center">
                                        @if ($stock <= 0)
                                            <span class="text-red-600 font-bold">0</span>
                                        @else
                                            <span class="text-green-600">{{ $stock }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="p-4 text-center text-gray-400">No se encontraron artículos con esos filtros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($articulos && $articulos->lastPage() > 1)
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <button type="button" wire:click="irAPagina({{ $articulos->currentPage() - 1 }})"
                            @disabled($articulos->onFirstPage())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">Anterior</button>
                        <span class="text-gray-600 dark:text-gray-300">Página {{ $articulos->currentPage() }} de {{ $articulos->lastPage() }}</span>
                        <button type="button" wire:click="irAPagina({{ $articulos->currentPage() + 1 }})"
                            @disabled(!$articulos->hasMorePages())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">Siguiente</button>
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
