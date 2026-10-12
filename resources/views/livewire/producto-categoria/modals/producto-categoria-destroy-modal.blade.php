<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar Categoria: {{ $productocategoria->nombre }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" value="{{ $productocategoria->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Marca:</x-label>
                    <x-input value="{{ $productocategoria->productoMarca->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">
                    Eliminar
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
