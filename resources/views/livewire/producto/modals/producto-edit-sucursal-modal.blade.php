<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">
                    Transferir Productos de Sucursal
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>

                @if (count($productos) == 0)
                    <div class="mb-4">
                        <x-label for="sucursal1_id" value="Sucursal Origen:" />
                        <select wire:model.live="sucursal1_id" id="sucursal1_id"
                            class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm transition-all duration-200 h-10">
                            <option value="">Seleccione una Sucursal</option>
                            @foreach ($sucursales as $sucursal)
                                <option value="{{ $sucursal->id }}" @if ($sucursal1_id == $sucursal->id) selected @endif>
                                    {{ $sucursal->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error for="sucursal1_id" class="mt-1" />
                    </div>
                @else
                    <div class="mb-4">
                        <x-label for="sucursal1_id" value="Sucursal Origen:" />
                        <x-input type="text" value="{{ $sucursal1?->nombre }}" disabled class="w-full" />
                    </div>
                @endif

                @if (count($productos) == 0)
                    <div class="mb-4">
                        <x-label for="sucursal2_id" value="Sucursal Destino:" />
                        <select wire:model.live="sucursal2_id" id="sucursal2_id"
                            class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm transition-all duration-200 h-10">
                            <option value="">Seleccione una Sucursal</option>
                            @foreach ($destinos as $sucursal)
                                <option value="{{ $sucursal->id }}" @if ($sucursal2_id == $sucursal->id) selected @endif>
                                    {{ $sucursal->nombre }}
                                </option>
                            @endforeach
                        </select>
                        <x-input-error for="sucursal2_id" class="mt-1" />
                        <x-input-error for="productos" class="mt-1" />
                    </div>
                @else
                    <div class="mb-4">
                        <x-label for="sucursal2_id" value="Sucursal Destino:" />
                        <x-input type="text" value="{{ $sucursal2?->nombre }}" disabled class="w-full" />
                        <x-input-error for="productos" class="mt-1" />
                    </div>
                @endif


                @if ($sucursal1 != null && $sucursal2 != null)
                    <div class="mb-4 relative" wire:ignore.self>
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
                            {{-- z-50, como los demas desplegables: por encima de cualquier
                                 cabecera sticky que lleve la tabla de abajo. --}}
                            <ul
                                class="absolute z-50 mt-1 w-full bg-white border border-gray-300 rounded-md shadow-lg max-h-60 overflow-auto">
                                @forelse ($filteredProductos as $producto)
                                    <li class="cursor-pointer px-4 py-2 hover:bg-gray-100 text-sm text-gray-700"
                                        wire:click="selectProducto('{{ $producto->imei }}')">
                                        {{ $producto->imei }} - {{ $producto->modelo?->nombre }} - {{ \App\Enums\ProductoEstado::labelDe($producto->estado) }}
                                    </li>
                                @empty
                                    {{-- El motivo concreto cuando lo hay: "no existe" y "esta
                                         en la otra sucursal" no son lo mismo. --}}
                                    <li class="px-4 py-2 text-gray-500 text-sm">
                                        {{ $motivoBusqueda ?: 'No se encontraron resultados' }}
                                    </li>
                                @endforelse
                            </ul>
                        @endif
                    </div>
                @endif
                <!-- Lista de Productos -->
                @if (count($productos) > 0)
                    <div class="overflow-x-auto mt-4">
                        <table class="min-w-full border text-xs">
                            <thead>
                                <tr class="bg-gray-100">
                                    <th class="p-2 border">IMEI</th>
                                    <th class="p-2 border">Descripción</th>
                                    <th class="p-2 border">Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($productos as $index => $producto)
                                    <tr>
                                        <td class="p-2 border">{{ $producto['imei'] }}</td>
                                        <td class="p-2 border">{{ $producto['descripcion'] }}</td>
                                        <td class="p-2 border">{{ $producto['estado'] }}</td>
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
                    </div>
                @endif

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if (count($productos) > 0)
                    <x-primary-button class="ml-2" wire:click="store()" wire:loading.attr="disabled">
                        Realizar Transferencia
                    </x-primary-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
