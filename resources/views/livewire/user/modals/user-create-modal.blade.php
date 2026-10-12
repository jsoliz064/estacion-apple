<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Agregar nuevo Usuario
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="user.name" class="w-full"></x-input>
                    <x-input-error for="user.name"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Correo:</x-label>
                    <x-input type="email" wire:model="email" class="w-full"></x-input>
                    <x-input-error for="email"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Contraseña:</x-label>
                    <x-input type="password" wire:model="user.password" class="w-full"></x-input>
                    <x-input-error for="user.password"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Confirmar Contraseña:</x-label>
                    <x-input type="password" wire:model="user.cpassword" class="w-full"></x-input>
                    <x-input-error for="user.cpassword"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Rol:</x-label>
                    <x-select wire:model="user.rol_id" :options="$roles->pluck('name', 'id')" placeholder="Seleccione un rol" />
                    <x-input-error for="user.rol_id"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>% Comisión sobre la ganancia:</x-label>
                    <x-input type="number" step="0.01" min="0" max="100" wire:model="user.comision_porcentaje" class="w-full"></x-input>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se calcula sobre la ganancia de cada venta (precio menos costo) y se gana cuando la venta queda cobrada por completo. 0 si no cobra comisión.</p>
                    <x-input-error for="user.comision_porcentaje"></x-input-error>
                </div>

                <div class="m-2">
                    <label class="inline-flex items-center gap-2">
                        <x-checkbox wire:model="user.activo" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Activo</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Un usuario inactivo no puede entrar al sistema, pero sus ventas y su historial se conservan.</p>
                    <x-input-error for="user.activo"></x-input-error>
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
