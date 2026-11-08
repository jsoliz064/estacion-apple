<div>
    @if ($openModal && $modelo)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Colores de {{ $modelo->nombre }}</x-slot>

            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                    Al cargar un {{ $modelo->nombre }} solo se ofrecerán los colores marcados.
                    Sin ninguno marcado, se ofrecen todos los activos.
                    @can('producto-color.create')
                        ¿Falta uno? Créalo en <a href="{{ route('productos.colores') }}" class="text-brand-600 underline dark:text-brand-400">Colores</a>.
                    @endcan
                </p>

                <div class="m-2 grid grid-cols-2 sm:grid-cols-3 gap-2">
                    @forelse ($colores as $color)
                        <label wire:key="modelo-color-{{ $color->id }}"
                            class="flex items-center gap-2 rounded-lg border border-gray-200 dark:border-gray-700 p-2 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-700">
                            <x-checkbox wire:model="marcados" value="{{ $color->id }}" />
                            <x-color-chip :color="$color" tam="punto" />
                            <span class="text-sm text-gray-800 dark:text-gray-200 truncate">{{ $color->nombre }}</span>
                            @unless ($color->activo)
                                <span class="text-xs text-gray-400">(inactivo)</span>
                            @endunless
                        </label>
                    @empty
                        <p class="text-sm text-gray-500 col-span-full">Todavía no hay colores cargados.</p>
                    @endforelse
                </div>
                <x-input-error for="marcados" />
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-primary-button class="ml-2" wire:click="guardar()" wire:loading.attr="disabled">Guardar</x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
