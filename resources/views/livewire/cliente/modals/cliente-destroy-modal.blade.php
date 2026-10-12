<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar cliente: {{ $cliente->nombre }}
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input value="{{ $cliente->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>CI:</x-label>
                    <x-input value="{{ $cliente->ci ?? 'Sin CI' }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Teléfono:</x-label>
                    <x-input value="{{ $cliente->telefono ?? 'Sin teléfono' }}" class="w-full"
                        disabled="true"></x-input>
                </div>

                {{-- El aviso ANTES de pulsar, no después de que la base lo rechace.
                     La FK va en restrict a propósito: si se borrara en cascada, sus
                     ventas quedarían sin dueño y su historial desaparecería sin
                     avisar. El catch del 1451 es la red por si alguien registra una
                     venta entre que se abre este modal y se confirma. --}}
                @if ($ordenes > 0)
                    <div
                        class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">
                            Este cliente tiene {{ $ordenes }} orden(es) registradas.
                        </p>
                        <p class="mt-1">
                            No se puede eliminar sin dejar esas ventas sin dueño. Si es una ficha duplicada,
                            edítala en lugar de borrarla.
                        </p>
                    </div>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                        No tiene ninguna venta registrada, así que se puede eliminar sin consecuencias.
                    </p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled"
                    :disabled="$ordenes > 0">
                    Eliminar
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
