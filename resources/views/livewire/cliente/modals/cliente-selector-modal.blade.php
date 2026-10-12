<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal" maxWidth="3xl">
            <x-slot name="title">
                Elegir cliente
            </x-slot>

            <x-slot name="content">
                <div class="mb-3">
                    <x-label>Buscar</x-label>
                    <x-input type="text" class="w-full" placeholder="Nombre, CI, teléfono o correo..."
                        wire:model.live.debounce.500ms="search" autocomplete="off" />
                </div>

                <div class="overflow-auto max-h-96 border rounded-md dark:border-gray-600">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100 dark:bg-gray-900 sticky top-0">
                            <tr>
                                <th class="p-2 border-b text-left">Nombre</th>
                                <th class="p-2 border-b text-left">CI</th>
                                <th class="p-2 border-b text-left">Teléfono</th>
                                <th class="p-2 border-b text-center">Órdenes</th>
                                <th class="p-2 border-b"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($clientes as $c)
                                @php
                                    $esElegido = (int) $c->id === (int) $elegido;
                                @endphp
                                {{-- Clases literales en las dos ramas: no hay safelist
                                     en tailwind.config.js. --}}
                                <tr wire:key="cliente-{{ $c->id }}"
                                    class="{{ $esElegido ? 'bg-brand-50 dark:bg-brand-900' : 'hover:bg-gray-50 dark:hover:bg-gray-700' }}">
                                    <td class="p-2 border dark:border-gray-600 font-medium">{{ $c->nombre }}</td>
                                    <td class="p-2 border dark:border-gray-600">{{ $c->ci ?: '—' }}</td>
                                    <td class="p-2 border dark:border-gray-600">{{ $c->telefono ?: '—' }}</td>
                                    <td class="p-2 border dark:border-gray-600 text-center">
                                        {{ (int) $c->ordenes }}
                                    </td>
                                    <td class="p-2 border dark:border-gray-600 text-right">
                                        @if ($esElegido)
                                            <span class="text-xs text-brand-700 dark:text-brand-300">Elegido</span>
                                        @else
                                            <x-primary-button wire:click="elegir({{ $c->id }})"
                                                wire:loading.attr="disabled">
                                                Elegir
                                            </x-primary-button>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="p-4 text-center text-gray-400">
                                        @if ($search !== '')
                                            Ningún cliente coincide con «{{ $search }}».
                                        @else
                                            Todavía no hay clientes registrados.
                                        @endif
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- El `$clientes &&` es obligatorio: con el modal cerrado la
                     variable es null, porque la consulta vive tras el guard. --}}
                @if ($clientes && $clientes->lastPage() > 1)
                    <div class="mt-3 flex items-center justify-between text-sm">
                        <button type="button" wire:click="irAPagina({{ $clientes->currentPage() - 1 }})"
                            @disabled($clientes->onFirstPage())
                            class="px-3 py-1 border rounded-md disabled:opacity-40 dark:border-gray-600">
                            Anterior
                        </button>
                        <span class="text-gray-600 dark:text-gray-300">
                            Pagina {{ $clientes->currentPage() }} de {{ $clientes->lastPage() }}
                        </span>
                        <button type="button" wire:click="irAPagina({{ $clientes->currentPage() + 1 }})"
                            @disabled(!$clientes->hasMorePages())
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
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
