<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Agregar nuevo Técnico
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="tecnico.nombre" class="w-full"></x-input>
                    <x-input-error for="tecnico.nombre"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>% Comisión sobre la mano de obra:</x-label>
                    <x-input type="number" step="0.01" min="0" max="100" wire:model="tecnico.comision_porcentaje" class="w-full"></x-input>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Los repuestos los pone el negocio: el técnico cobra este porcentaje de la mano de obra al terminar la reparación. Las reparaciones por garantía no comisionan.</p>
                    <x-input-error for="tecnico.comision_porcentaje"></x-input-error>
                </div>
                <div class="m-2">
                    <x-label>Usuario del sistema:</x-label>
                    <select wire:model="tecnico.user_id"
                        class="w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                        <option value="">Sin usuario</option>
                        @foreach ($usuarios as $usuario)
                            <option value="{{ $usuario->id }}">{{ $usuario->name }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Con su usuario, el técnico ve sus comisiones en «Mis comisiones».</p>
                    <x-input-error for="tecnico.user_id"></x-input-error>
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
