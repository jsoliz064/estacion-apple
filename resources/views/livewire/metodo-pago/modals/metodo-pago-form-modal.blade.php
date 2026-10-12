<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">{{ $metodoId ? 'Editar método: ' . $nombre : 'Nuevo método de pago' }}</x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" wire:model="nombre" class="w-full" maxlength="60" placeholder="Efectivo, QR, Transferencia..." />
                    <x-input-error for="nombre" />
                </div>
                <div class="m-2">
                    <x-label>Orden:</x-label>
                    <x-input type="number" min="0" wire:model="orden" class="w-32" />
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">El primero de la lista es el que se propone al cobrar.</p>
                    <x-input-error for="orden" />
                </div>
                <div class="m-2">
                    <label class="inline-flex items-center gap-2">
                        <x-checkbox wire:model="activo" />
                        <span class="text-sm text-gray-700 dark:text-gray-300">Activo</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Uno inactivo deja de ofrecerse al cobrar; sus pagos viejos se conservan.</p>
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-primary-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled">Guardar</x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
