<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar Técnico: {{ $tecnico->nombre }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" value="{{ $tecnico->nombre }}" class="w-full" disabled="true"></x-input>
                </div>
                @if ($tecnico->reparaciones()->exists())
                    <p class="m-2 text-sm text-red-600">Tiene reparaciones registradas: no se puede eliminar, para no perder su historial ni sus comisiones.</p>
                @endif

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
