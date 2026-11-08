<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">{{ $colorId ? 'Editar color: ' . $nombre : 'Nuevo color' }}</x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="nombre" class="w-full" maxlength="50"
                        placeholder="Ultramarino, Naranja cósmico..." :disabled="$equipos > 0" />
                    @if ($equipos > 0)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Lo usan {{ $equipos }} equipo(s): el nombre ya no se cambia. Para otro tono, cree un color nuevo.
                        </p>
                    @endif
                    <x-input-error for="nombre" />
                </div>
                <div class="m-2">
                    <x-label>Muestra:</x-label>
                    <div class="flex items-center gap-3" x-data="{ hex: @entangle('hex') }">
                        <input type="color" x-model="hex"
                            class="h-10 w-14 rounded-md border border-gray-300 dark:border-gray-600 p-0 cursor-pointer">
                        <x-input type="text" x-model="hex" class="w-32 font-mono uppercase" maxlength="7" />
                        <span class="inline-flex items-center rounded-full border border-gray-300 dark:border-gray-600 px-3 py-1 text-xs font-medium"
                            x-bind:style="'background-color:' + hex">Muestra</span>
                    </div>
                    <x-input-error for="hex" />
                </div>
                <div class="m-2">
                    <x-label>Orden:</x-label>
                    <x-input type="number" min="0" wire:model="orden" class="w-32" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Los colores se ofrecen en este orden al cargar un equipo.</p>
                    <x-input-error for="orden" />
                </div>
                <div class="m-2">
                    <label class="inline-flex items-center gap-2">
                        <x-checkbox wire:model="activo" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Activo</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Uno inactivo deja de ofrecerse al cargar equipos; los que ya lo tienen lo conservan.</p>
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-primary-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled">Guardar</x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
