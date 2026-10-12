<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Editar Categoria: {{ isset($repuestocategoria['nombre']) ? $repuestocategoria['nombre'] : '' }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre: *</x-label>
                    <x-input type="text" wire:model="repuestocategoria.nombre" class="w-full"></x-input>
                    <x-input-error for="repuestocategoria.nombre"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Descripcion:</x-label>
                    <x-input type="text" wire:model="repuestocategoria.descripcion" class="w-full"></x-input>
                    <x-input-error for="repuestocategoria.descripcion"></x-input-error>
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
