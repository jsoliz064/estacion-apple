<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">
                    Detalle de Repracion Nro.{{ $reparacion->id }}
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="m-2">
                    <x-label>Producto:</x-label>
                    <x-input type="text" value="{{ $reparacion->producto->descripcion }}" class="w-full"
                        disabled="true"></x-input>
                </div>

                <x-reparacion-detalle :reparacion="$reparacion" />

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
