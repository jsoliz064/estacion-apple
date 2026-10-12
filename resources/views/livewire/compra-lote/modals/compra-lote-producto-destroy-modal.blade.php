<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar Producto: {{ $producto->imei }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Descripcion:</x-label>
                    <x-input type="text" value="{{ $producto->descripcion }}" class="w-full" disabled="true"></x-input>
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
