<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Agregar nuevo modelo
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="productomodelo.nombre" class="w-full"></x-input>
                    <x-input-error for="productomodelo.nombre"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Categoria:</x-label>
                    <x-select wire:model="productomodelo.producto_categoria_id" :options="$categorias->pluck('nombre', 'id')"
                        placeholder="Seleccione una categoria" />
                    <x-input-error for="productomodelo.producto_categoria_id"></x-input-error>
                </div>

                {{-- Un Apple Watch no tiene almacenamiento: sin la casilla, el modelo
                     no tiene capacidades y sus equipos se cargan sin almacenamiento. --}}
                <div class="m-2">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model="llevaAlmacenamiento"
                            class="rounded border-gray-300 text-brand-600 shadow-sm focus:ring-brand-500 dark:border-gray-600 dark:bg-gray-700">
                        Lleva almacenamiento (32GB a 2TB)
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Desmárcala para un Apple Watch u otro equipo sin capacidad.</p>
                </div>

            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="store()" wire:loading.attr="disabled">
                    Guardar
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
