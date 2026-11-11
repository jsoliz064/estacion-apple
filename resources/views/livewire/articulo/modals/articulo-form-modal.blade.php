<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                {{ $articuloId ? 'Editar' : 'Agregar' }} {{ mb_strtolower(\App\Enums\ArticuloTipo::from($tipo)->label()) }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="articulo.nombre" class="w-full"></x-input>
                    <x-input-error for="articulo.nombre"></x-input-error>
                </div>

                <div class="m-2">
                    <div>
                        <x-label>Código de barras (opcional):</x-label>
                        <div class="flex gap-2" data-escaner>
                            <x-input type="text" wire:model="articulo.upc" class="w-full" placeholder="Escanee o escriba"
                                x-on:keydown.enter.prevent=""></x-input>
                            <x-boton-escaner modo="input" />
                        </div>
                        <x-input-error for="articulo.upc"></x-input-error>
                    </div>
                </div>

                @if ($this->esRepuesto())
                    <div wire:key="campos-de-repuesto">
                        <div class="m-2">
                            <x-label>Fabricante (opcional):</x-label>
                            <x-input type="text" wire:model="articulo.fabricante" class="w-full"></x-input>
                            <x-input-error for="articulo.fabricante"></x-input-error>
                        </div>

                        <div class="m-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label>Modelo:</x-label>
                                <x-select wire:model="articulo.producto_modelo_id" :options="$catalogoModelos->pluck('nombre', 'id')"
                                    placeholder="Seleccione un modelo" />
                                <x-input-error for="articulo.producto_modelo_id"></x-input-error>
                            </div>
                            <div>
                                <x-label>Categoría:</x-label>
                                <x-select wire:model="articulo.repuesto_categoria_id" :options="$catalogoCategorias->pluck('nombre', 'id')"
                                    placeholder="Sin categoría" />
                                <x-input-error for="articulo.repuesto_categoria_id"></x-input-error>
                            </div>
                        </div>
                    </div>
                @else
                    <div wire:key="campos-de-accesorio">
                        <div class="m-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <x-label>Marca (opcional):</x-label>
                                <x-input type="text" wire:model="articulo.marca" class="w-full"></x-input>
                                <x-input-error for="articulo.marca"></x-input-error>
                            </div>
                            <div>
                                <x-label>Categoría:</x-label>
                                <x-select wire:model="articulo.accesorio_categoria_id" :options="$catalogoCategorias->pluck('nombre', 'id')"
                                    placeholder="Sin categoría" />
                                <x-input-error for="articulo.accesorio_categoria_id"></x-input-error>
                            </div>
                        </div>

                        {{-- Una descripcion libre, como los detalles del equipo. --}}
                        <div class="m-2">
                            <x-label>Detalles (opcional):</x-label>
                            <textarea wire:model="articulo.detalles" rows="2" maxlength="1000" placeholder="Ej.: cable de 2 m, trenzado"
                                class="mt-1 w-full p-2 resize-none border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                            <x-input-error for="articulo.detalles"></x-input-error>
                        </div>

                        <div class="m-2">
                            <x-label>Compatible con (opcional):</x-label>
                            <select wire:model="modelos" multiple
                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-brand-500 focus:ring-brand-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white h-32">
                                @foreach ($catalogoModelos as $modelo)
                                    <option value="{{ $modelo->id }}">{{ $modelo->nombre }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Ctrl/Cmd + clic para elegir varios.</p>
                            <x-input-error for="modelos"></x-input-error>
                        </div>
                    </div>
                @endif

                <div class="m-2">
                    @include('livewire.partials.montos-moneda', [
                        'c' => [
                            'moneda' => 'articulo.moneda', 'costo' => 'articulo.costo', 'precio' => 'articulo.precio',
                            'costo_bs' => 'articulo.costo_moneda_monto', 'precio_bs' => 'articulo.precio_moneda_monto', 'tc' => 'articulo.tipo_cambio',
                        ],
                        'enBs' => $this->enBs(), 'costo' => $articulo['costo'] ?? 0, 'precio' => $articulo['precio'] ?? 0,
                        'etiquetaCosto' => 'Costo (código)',
                        'costoBloqueado' => !$puedeCosto,
                        'precioBloqueado' => !$puedePrecio,
                        'aviso' => $this->avisoPermisoMontos($puedeCosto, $puedePrecio),
                    ])
                </div>

                <div class="m-2 grid grid-cols-1 sm:grid-cols-3 gap-4">
                    @if ($conStock)
                        <div>
                            {{-- Calculado y de solo lectura: el stock vive por sucursal
                                 y el total es su suma. --}}
                            <x-label>Stock total:</x-label>
                            <x-input type="text" value="{{ $this->totalStock() }}" class="w-full" disabled="true"></x-input>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Suma de las sucursales</p>
                        </div>
                    @endif
                </div>

                @if ($conStock)
                    <div class="mt-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                        <x-label>Stock por sucursal</x-label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Cantidad y mínimo de cada sucursal. Con un mínimo, la sucursal avisa «por agotarse» al llegar a él (0 = sin mínimo).
                        </p>

                        @forelse ($stockSucursales as $sucursalId => $cantidad)
                            {{-- wire:key con el indice: el wire:model se enlaza por posicion. --}}
                            <div class="flex items-end gap-2 mt-2" wire:key="stock-{{ $sucursalId }}-{{ $loop->index }}">
                                <div class="flex-1">
                                    <span class="text-sm text-gray-700 dark:text-gray-300">
                                        {{ $sucursalesPorId[$sucursalId]->nombre ?? 'Sucursal #' . $sucursalId }}
                                    </span>
                                </div>
                                <div class="w-24">
                                    <span class="block text-xs text-gray-500">Cantidad</span>
                                    <x-input type="number" min="0" class="w-full"
                                        wire:model="stockSucursales.{{ $sucursalId }}" onfocus="this.select()"></x-input>
                                </div>
                                <div class="w-24">
                                    <span class="block text-xs text-gray-500">Mínimo</span>
                                    <x-input type="number" min="0" class="w-full"
                                        wire:model="minimosSucursales.{{ $sucursalId }}" onfocus="this.select()"></x-input>
                                </div>
                                <button type="button" wire:click="quitarSucursalStock({{ $sucursalId }})"
                                    class="px-3 py-2 text-red-600 hover:text-red-800 dark:text-red-400 text-xl font-bold leading-none"
                                    title="Quitar esta sucursal">&times;</button>
                            </div>
                            <x-input-error for="stockSucursales.{{ $sucursalId }}"></x-input-error>
                            <x-input-error for="minimosSucursales.{{ $sucursalId }}"></x-input-error>
                        @empty
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                Sin stock asignado a ninguna sucursal. Lo normal es que entre por una compra.
                            </p>
                        @endforelse

                        @if ($sucursalesDisponibles->isNotEmpty())
                            <div class="flex items-end gap-2 mt-3">
                                <div class="flex-1">
                                    <select wire:model="sucursalNueva"
                                        class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                        <option value="">Agregar una sucursal...</option>
                                        @foreach ($sucursalesDisponibles as $sucursal)
                                            <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" wire:click="agregarSucursalStock"
                                    class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-md text-sm">
                                    Agregar
                                </button>
                            </div>
                        @endif
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled">
                    Guardar
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
