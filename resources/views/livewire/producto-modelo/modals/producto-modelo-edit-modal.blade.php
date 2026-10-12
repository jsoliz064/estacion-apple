<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Editar Modelo: {{ isset($productomodelo['nombre']) ? $productomodelo['nombre'] : '' }}
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
                        placeholder="Seleccione una Categoria" />
                    <x-input-error for="productomodelo.producto_categoria_id"></x-input-error>
                </div>

            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                    Actualizar
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
