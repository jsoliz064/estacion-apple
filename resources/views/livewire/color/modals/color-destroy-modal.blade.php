<div>
    @if ($openModal && $color)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Eliminar color: {{ $color->nombre }}</x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2"><x-color-chip :color="$color" /></div>
                @if ($equipos > 0)
                    <div class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">Este color lo tienen {{ $equipos }} equipo(s).</p>
                        <p class="mt-1">
                            No se puede eliminar sin dejar esos equipos con un color fuera de la lista.
                            {{ $color->activo ? 'Puedes desactivarlo: dejará de ofrecerse al cargar equipos.' : 'Ya está desactivado.' }}
                        </p>
                    </div>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">Ningún equipo lo usa: se puede eliminar sin consecuencias (también se quita de los modelos que lo tenían marcado).</p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                @if ($equipos > 0)
                    @if ($color->activo)
                        <x-danger-button class="ml-2" wire:click="desactivar()" wire:loading.attr="disabled">Desactivar</x-danger-button>
                    @endif
                @else
                    <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">Eliminar</x-danger-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
