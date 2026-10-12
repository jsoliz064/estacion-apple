<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="3xl">
            <x-slot name="title">
                <p class="text-center">Agregar repuestos a la reparación</p>
            </x-slot>

            <x-slot name="content">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="sm:col-span-2">
                        <x-label>Sucursal de donde salen las piezas *</x-label>
                        <select wire:model.live="sucursalId"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            <option value="">Seleccione una sucursal...</option>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="sucursalId" class="mt-1" />
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
                        <x-label>Modelo</x-label>
                        <select wire:model.live="filtroModelo"
                            class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm sm:text-sm">
                            <option value="">Todos</option>
                            @foreach ($modelos as $m)
                                <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <x-label>Buscar</x-label>
                        {{-- El Enter manda $el.value: el debounce todavia no tiene el codigo de la pistola. --}}
                        <div class="mt-1 flex gap-2" data-escaner>
                            <x-input type="text" class="w-full" placeholder="Nombre, SKU o código..."
                                wire:model.live.debounce.500ms="search"
                                x-on:keydown.enter.prevent="$wire.marcarPorCodigo($el.value); $el.value = ''"
                                autocomplete="off" />
                            <x-boton-escaner continuo />
                        </div>
                    </div>
                </div>

                <div class="mt-3 text-sm text-gray-600 dark:text-gray-300">
                    <span class="font-semibold">{{ count($seleccionados) }}</span> seleccionado(s)
                    @if ($repuestos)
                        &middot; {{ $repuestos->total() }} resultado(s)
                    @endif
                </div>

                <div class="overflow-x-auto overflow-y-auto max-h-80 mt-2 border border-gray-200 dark:border-gray-700 shadow-sm rounded-lg">
                    <table class="min-w-full text-xs relative">
                        <thead class="sticky top-0 bg-gray-100 dark:bg-gray-700 shadow-sm z-10">
                            <tr>
                                <th class="p-2 border-b w-10"></th>
                                <th class="p-2 border-b text-left">Repuesto</th>
                                <th class="p-2 border-b text-right">Costo</th>
                                <th class="p-2 border-b text-center">{{ $nombreSucursal !== '' ? 'Stock en ' . $nombreSucursal : 'Stock' }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($repuestos as $r)
                                @php
                                    $stock = $sucursalId !== '' ? $r->stockEn((int) $sucursalId) : null;
                                    $bloqueado = $stock !== null && $stock <= 0;
                                    $marcado = in_array($r->id, $seleccionados, true);
                                    // Clases LITERALES en cada rama: no hay safelist.
                                    if ($bloqueado) {
                                        $claseFila = 'opacity-50 cursor-not-allowed';
                                    } elseif ($marcado) {
                                        $claseFila = 'bg-brand-50 dark:bg-brand-900 cursor-pointer';
                                    } else {
                                        $claseFila = 'hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer';
                                    }
                                @endphp
                                <tr wire:key="rep-sel-{{ $r->id }}" class="{{ $claseFila }}"
                                    @if (!$bloqueado) wire:click="alternar({{ $r->id }})" @endif
                                    title="{{ $bloqueado ? 'Sin stock en ' . $nombreSucursal : '' }}">
                                    <td class="p-2 border text-center">
                                        <x-checkbox class="pointer-events-none" :checked="$marcado" :disabled="$bloqueado" />
                                    </td>
                                    <td class="p-2 border">
                                        <span class="text-gray-900 dark:text-gray-100">{{ $r->nombre }}</span>
                                        <span class="block text-gray-500">
                                            {{ collect([$r->modelo?->nombre, $r->fabricante, $r->categoria?->nombre, $r->sku])->filter()->implode(' · ') }}
                                        </span>
                                    </td>
                                    <td class="p-2 border text-right whitespace-nowrap">Bs {{ number_format((float) $r->costo, 2) }}</td>
                                    <td class="p-2 border text-center">
                                        @if ($stock === null)
                                            <span class="text-gray-400">—</span>
                                        @elseif ($stock <= 0)
                                            <span class="text-red-600 font-bold">0</span>
                                        @else
                                            <span class="text-green-600">{{ $stock }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="p-4 text-center text-gray-400">No se encontraron repuestos con esos filtros.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($repuestos && $repuestos->lastPage() > 1)
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <button type="button" wire:click="irAPagina({{ $repuestos->currentPage() - 1 }})"
                            @disabled($repuestos->onFirstPage())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">Anterior</button>
                        <span class="text-gray-600 dark:text-gray-300">Página {{ $repuestos->currentPage() }} de {{ $repuestos->lastPage() }}</span>
                        <button type="button" wire:click="irAPagina({{ $repuestos->currentPage() + 1 }})"
                            @disabled(!$repuestos->hasMorePages())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">Siguiente</button>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="agregar" wire:loading.attr="disabled"
                    :disabled="count($seleccionados) === 0">
                    Agregar {{ count($seleccionados) }} repuesto(s)
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
