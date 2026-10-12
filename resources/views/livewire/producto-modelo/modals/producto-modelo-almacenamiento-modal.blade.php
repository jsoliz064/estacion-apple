<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Almacenamientos de Modelo:
                {{ $productomodelo['nombre'] ?? '' }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="space-y-4 mt-2">
                    @foreach ($productomodelo->almacenamientos as $almacenamiento)
                        <div class="flex items-center gap-4">
                            <div class="w-1/4 text-center">
                                <x-label>{{ $almacenamiento->almacenamiento }}</x-label>
                            </div>

                            <div class="w-1/4">
                                <x-label>Costo (Bs)</x-label>
                                <x-input type="number" wire:model.defer="costos.{{ $almacenamiento->id }}"
                                    class="w-full" />
                                <x-input-error for="costos.{{ $almacenamiento->id }}" />
                            </div>

                            <div class="w-1/4">
                                <x-label>Precio vendedor (Bs)</x-label>
                                <x-input type="number" wire:model.defer="precios.{{ $almacenamiento->id }}"
                                    class="w-full" />
                                <x-input-error for="precios.{{ $almacenamiento->id }}" />
                            </div>

                            <div class="w-1/4">
                                <x-label>Precio cliente (Bs)</x-label>
                                <x-input type="number" wire:model.defer="precios_clientes.{{ $almacenamiento->id }}"
                                    class="w-full" />
                                <x-input-error for="precios_clientes.{{ $almacenamiento->id }}" />
                            </div>
                        </div>
                    @endforeach

                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>

                <x-button class="ml-2" wire:click="updateAlmacenamientos()" wire:loading.attr="disabled">
                    Actualizar
                </x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
