<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">
                    Cambio masivo de productos
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="mt-4 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" value="estado" wire:model.live="modo" class="text-brand-600 focus:ring-brand-500">
                        Estado
                    </label>
                    <label class="flex items-center gap-2 cursor-pointer text-sm text-gray-700 dark:text-gray-300">
                        <input type="radio" value="tipo_venta" wire:model.live="modo" class="text-brand-600 focus:ring-brand-500">
                        Tipo de venta (oferta)
                    </label>
                </div>

                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <x-label>{{ $modo === 'tipo_venta' ? 'Tipo de venta origen' : 'Estado origen' }}</x-label>
                        <select wire:model.live="origen"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                            <option value="">Seleccione una opción</option>
                            @foreach ($opciones as $valor => $nombre)
                                <option value="{{ $valor }}">{{ $nombre }}</option>
                            @endforeach
                        </select>
                        <x-input-error for="origen" class="mt-1" />
                    </div>

                    <div>
                        <x-label>{{ $modo === 'tipo_venta' ? 'Tipo de venta destino' : 'Estado destino' }}</x-label>
                        <select wire:model.live="destino"
                            class="w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm">
                            <option value="">Seleccione una opción</option>
                            @foreach ($opciones as $valor => $nombre)
                                @if ($valor !== $origen)
                                    <option value="{{ $valor }}">{{ $nombre }}</option>
                                @endif
                            @endforeach
                        </select>
                        <x-input-error for="destino" class="mt-1" />
                    </div>
                </div>

                @if (!empty($origen) && $totalEnOrigen > 0)
                    <div class="mt-4 flex flex-col sm:flex-row sm:items-center sm:justify-between bg-brand-50 p-3 rounded-lg border border-brand-200">
                        <span class="text-sm text-brand-800 font-medium">
                            Hay {{ $totalEnOrigen }} producto(s) en "{{ $opciones[$origen] ?? $origen }}".
                        </span>
                        <label class="flex items-center space-x-2 cursor-pointer mt-2 sm:mt-0">
                            <input type="checkbox" wire:model.live="seleccionarTodos" class="rounded border-gray-300 text-brand-600 shadow-sm focus:border-brand-500 focus:ring-brand-500">
                            <span class="text-sm font-medium text-gray-700">Seleccionar todos</span>
                        </label>
                    </div>
                @elseif(!empty($origen) && $totalEnOrigen == 0)
                    <div class="mt-4 flex items-center justify-between bg-yellow-50 p-3 rounded-lg border border-yellow-200">
                        <span class="text-sm text-yellow-800 font-medium">No hay productos en "{{ $opciones[$origen] ?? $origen }}".</span>
                    </div>
                @endif

                <div class="mt-4 mb-4 relative" wire:ignore.self>
                    <label class="block text-sm font-medium text-gray-700">Buscar IMEI o SKU</label>
                    {{-- Enter (pistola o camara): un IMEI o SKU exacto se agrega solo y el
                         campo queda listo para el siguiente. --}}
                    <div class="mt-1 flex gap-2" data-escaner>
                        <input type="text"
                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                            placeholder="Escanee o escriba el IMEI o SKU..." wire:model.live.debounce.500ms="searchImei"
                            x-on:keydown.enter.prevent="$wire.elegirPorCodigo($el.value); $el.value = ''"
                            wire:keydown.escape="$set('searchImei', '')" wire:keydown.tab="$set('searchImei', '')"
                            autocomplete="off" />
                        <x-boton-escaner continuo />
                    </div>

                    @if (!empty($searchImei))
                        {{-- z-50 y no z-10: la cabecera de la tabla de abajo es sticky con
                             z-10 y, a igual z-index, gana la que va despues en el HTML, que
                             es ella. Es el valor que ya usan los demas desplegables. --}}
                        <ul
                            class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                            @forelse ($filteredProductos as $producto)
                                <li class="cursor-pointer px-4 py-2 hover:bg-gray-100 text-sm text-gray-700"
                                    wire:click="selectProducto('{{ $producto->imei }}')">
                                    {{ $producto->imei }} - {{ $producto->modelo?->nombre }} - {{ $modo === 'tipo_venta' ? $producto->tipo_venta : \App\Enums\ProductoEstado::labelDe($producto->estado) }}
                                </li>
                            @empty
                                {{-- El motivo concreto cuando lo hay: "no existe" y "existe
                                     pero esta en otro estado" no son lo mismo. --}}
                                <li class="px-4 py-2 text-gray-500 text-sm">
                                    {{ $motivoBusqueda ?: 'No se encontraron resultados' }}
                                </li>
                            @endforelse
                        </ul>
                    @endif
                </div>
                <!-- Lista de Productos -->
                @if (count($productos) > 0)
                    <div class="overflow-x-auto overflow-y-auto max-h-64 mt-4 border border-gray-200 shadow-sm rounded-lg">
                        <table class="min-w-full text-xs relative">
                            <thead class="sticky top-0 bg-gray-100 shadow-sm z-10">
                                <tr>
                                    <th class="p-2 border-b w-12 text-center">#</th>
                                    <th class="p-2 border-b">IMEI</th>
                                    <th class="p-2 border-b">Descripción</th>
                                    <th class="p-2 border-b">{{ $modo === 'tipo_venta' ? 'Tipo de venta' : 'Estado' }}</th>
                                    <th class="p-2 border-b">Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($productos as $index => $producto)
                                    <tr class="hover:bg-gray-50">
                                        <td class="p-2 border text-center text-gray-500 font-medium">{{ $index + 1 }}</td>
                                        <td class="p-2 border">{{ $producto['imei'] }}</td>
                                        <td class="p-2 border">{{ $producto['descripcion'] }}</td>
                                        <td class="p-2 border">{{ $producto['estado'] ?? '' }}</td>
                                        <td class="p-2 border text-center">
                                            <button wire:click="eliminarDetalle({{ $index }})"
                                                class="text-red-600 hover:text-red-800 text-2xl font-extrabold leading-none"
                                                title="Quitar detalle">
                                                ×
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <!-- Totales -->
                    <div class="mt-4 grid grid-cols-1 md:grid-cols-1 gap-4">
                        <div>
                            <x-label>Cantidad de Productos</x-label>
                            <x-input type="number" value="{{ sizeof($productos) }}" disabled class="w-full" />
                        </div>

                        <div>
                            <x-label>Descripcion</x-label>
                            <textarea wire:model="descripcion"
                                class="w-full border-gray-300 rounded-md shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm"
                                rows="3"></textarea>
                            <x-input-error for="descripcion" class="mt-1" />
                            <x-input-error for="productos" class="mt-1" />
                        </div>
                    </div>
                @endif

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if (count($productos) > 0)
                    <x-primary-button class="ml-2" wire:click="store()" wire:loading.attr="disabled">
                        Cambiar Estado
                    </x-primary-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
