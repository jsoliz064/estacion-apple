<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Editar Sucursal: {{ $sucursal['nombre'] ?? '' }}
            </x-slot>

            <x-slot name="content">
                <hr>
                @include('livewire.sucursal.modals.campos', ['esAlmacen' => $esAlmacen])
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
