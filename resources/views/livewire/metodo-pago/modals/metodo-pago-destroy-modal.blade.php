<div>
    @if ($openModal && $metodo)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Eliminar método: {{ $metodo->nombre }}</x-slot>

            <x-slot name="content">
                <hr>
                @if ($pagos > 0)
                    <div class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">Este método tiene {{ $pagos }} pago(s) registrados.</p>
                        <p class="mt-1">
                            No se puede eliminar sin perder de qué forma se cobraron.
                            {{ $metodo->activo ? 'Puedes desactivarlo: dejará de ofrecerse al cobrar.' : 'Ya está desactivado.' }}
                        </p>
                    </div>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">No tiene pagos registrados: se puede eliminar sin consecuencias.</p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                @if ($pagos > 0)
                    @if ($metodo->activo)
                        <x-danger-button class="ml-2" wire:click="desactivar()" wire:loading.attr="disabled">Desactivar</x-danger-button>
                    @endif
                @else
                    <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">Eliminar</x-danger-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
