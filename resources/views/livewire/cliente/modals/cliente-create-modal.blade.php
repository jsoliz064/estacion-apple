<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Nuevo cliente
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="m-2">
                    <x-label>Nombre: *</x-label>
                    <x-input type="text" wire:model="cliente.nombre" class="w-full"
                        placeholder="Nombre y apellido"></x-input>
                    <x-input-error for="cliente.nombre" class="mt-1"></x-input-error>
                    {{-- Dos personas pueden llamarse igual, así que el nombre no es
                         único: lo que distingue de verdad es el CI. --}}
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Lo único obligatorio.
                    </p>
                </div>

                <div class="m-2">
                    <x-label>CI:</x-label>
                    <x-input type="text" wire:model="cliente.ci" class="w-full"
                        placeholder="Carnet de identidad (opcional)"></x-input>
                    <x-input-error for="cliente.ci" class="mt-1"></x-input-error>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Si lo pones, no puede repetirse: es lo que evita tener dos fichas de la misma persona.
                    </p>
                </div>

                <div class="m-2">
                    <x-label>Teléfono:</x-label>
                    <x-input type="text" wire:model="cliente.telefono" class="w-full"
                        placeholder="Teléfono (opcional)"></x-input>
                    <x-input-error for="cliente.telefono" class="mt-1"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Correo:</x-label>
                    <x-input type="email" wire:model="cliente.correo" class="w-full"
                        placeholder="Correo (opcional)"></x-input>
                    <x-input-error for="cliente.correo" class="mt-1"></x-input-error>
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
