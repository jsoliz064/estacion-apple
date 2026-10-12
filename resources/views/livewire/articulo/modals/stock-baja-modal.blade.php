<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Dar de baja unidades
            </x-slot>

            <x-slot name="content">
                <hr>
                @if (!$articulo)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">El artículo ya no existe.</p>
                @else
                    <div class="mt-4">
                        <x-label>Artículo</x-label>
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $articulo->etiqueta() }}</p>
                    </div>

                    @include('livewire.articulo.modals.partials.reparto', ['reparto' => $reparto, 'total' => $articulo->cantidad])

                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <x-label>Sucursal</x-label>
                            <select wire:model="sucursal_id"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                <option value="">Seleccione...</option>
                                @foreach ($origenes as $fila)
                                    <option value="{{ $fila->sucursal_id }}">
                                        {{ $fila->sucursal?->nombre ?? 'Sin sucursal' }} ({{ (int) $fila->cantidad }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error for="sucursal_id"></x-input-error>
                        </div>
                        <div>
                            <x-label>Unidades</x-label>
                            <x-input type="number" min="1" step="1" class="w-full" wire:model="cantidad" onfocus="this.select()" />
                            <x-input-error for="cantidad"></x-input-error>
                        </div>
                    </div>

                    <div class="mt-4">
                        <x-label>Motivo</x-label>
                        <x-select wire:model="motivo" :options="\App\Enums\BajaMotivo::toSelectArray()" placeholder="Seleccione el motivo" />
                        <x-input-error for="motivo"></x-input-error>
                    </div>

                    <div class="mt-4">
                        <x-label>Nota (opcional)</x-label>
                        <x-input type="text" class="w-full" wire:model="nota" placeholder="Qué pasó" />
                        <x-input-error for="nota"></x-input-error>
                        <x-input-error for="detalles"></x-input-error>
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($articulo && $origenes->isNotEmpty())
                    <x-danger-button class="ml-2" wire:click="store()" wire:loading.attr="disabled" wire:target="store">
                        Dar de baja
                    </x-danger-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
