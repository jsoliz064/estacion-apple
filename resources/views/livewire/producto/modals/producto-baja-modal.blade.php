<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                {{ $producto?->estaDadoDeBaja() ? 'Revertir baja' : 'Dar de baja el equipo' }}
            </x-slot>

            <x-slot name="content">
                <hr>
                @if (!$producto)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">El equipo ya no existe.</p>
                @else
                    <div class="mt-4">
                        <x-label>Equipo</x-label>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">
                            {{ $producto->modelo?->nombre }} {{ $producto->almacenamiento }} · IMEI {{ $producto->imei }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Estado: {{ \App\Enums\ProductoEstado::labelDe($producto->estado) }}
                        </p>
                    </div>

                    @if ($producto->estaDadoDeBaja())
                        <div class="mt-4 rounded-md border border-gray-300 dark:border-gray-600 p-3 text-sm text-gray-700 dark:text-gray-300">
                            <p><span class="font-semibold">Motivo:</span> {{ \App\Enums\BajaMotivo::labelDe($producto->motivo_baja) }}</p>
                            @if ($producto->nota_baja)
                                <p><span class="font-semibold">Nota:</span> {{ $producto->nota_baja }}</p>
                            @endif
                            <p><span class="font-semibold">Fecha:</span> {{ $producto->dado_de_baja_at->format('d/m/Y H:i') }}
                                @if ($producto->bajaUser) · {{ $producto->bajaUser->name }} @endif
                            </p>
                        </div>
                        <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                            Al revertir, el equipo vuelve a verse en el inventario con el estado que tenía.
                        </p>
                    @else
                        <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                            El equipo queda archivado: no aparece en las tablas, en ventas ni en el catálogo.
                            «Fuera» y «Roto» son estados, no bajas.
                        </p>

                        <div class="mt-4">
                            <x-label>Motivo</x-label>
                            <x-select wire:model="motivo" :options="\App\Enums\BajaMotivo::toSelectArray()" placeholder="Seleccione el motivo" />
                            <x-input-error for="motivo"></x-input-error>
                        </div>

                        <div class="mt-4">
                            <x-label>Nota (opcional)</x-label>
                            <x-input type="text" class="w-full" wire:model="nota" placeholder="Qué pasó" />
                            <x-input-error for="nota"></x-input-error>
                        </div>
                    @endif
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($producto)
                    @if ($producto->estaDadoDeBaja())
                        <x-button class="ml-2" wire:click="revertir()" wire:loading.attr="disabled" wire:target="revertir">
                            Revertir baja
                        </x-button>
                    @else
                        <x-danger-button class="ml-2" wire:click="darDeBaja()" wire:loading.attr="disabled" wire:target="darDeBaja">
                            Dar de baja
                        </x-danger-button>
                    @endif
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
