<div>
    @if ($openModal && $producto)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Reclamar al proveedor</x-slot>
            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ trim(($producto->modelo?->nombre ?? 'Equipo') . ' ' . $producto->almacenamiento . ' ' . $producto->color) }}
                    · IMEI <span class="font-mono">{{ $producto->imei }}</span>
                </p>
                <div class="m-2">
                    <x-label value="¿Qué tiene?" />
                    <x-input type="text" class="mt-1 w-full" wire:model="motivo" maxlength="255" placeholder="No enciende, pantalla con manchas..." />
                    <x-input-error for="motivo" class="mt-1" />
                </div>
                <p class="m-2 text-xs text-gray-500 dark:text-gray-400">
                    El equipo queda «En reclamo»: sale del inventario vendible y del catálogo hasta que se cierre el reclamo.
                </p>
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-danger-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled">Marcar en reclamo</x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
