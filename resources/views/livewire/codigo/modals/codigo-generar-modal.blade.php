<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Generar códigos de barras</x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2 grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <x-label>Cantidad de códigos:</x-label>
                        <x-input type="number" min="1" max="{{ \App\Services\CodigoBarrasService::MAX_CODIGOS }}" wire:model="cantidad" class="w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Uno distinto por cada equipo.</p>
                        <x-input-error for="cantidad" />
                    </div>
                    <div>
                        <x-label>Copias de cada código:</x-label>
                        <x-input type="number" min="1" max="{{ \App\Services\CodigoBarrasService::MAX_COPIAS }}" wire:model="copias" class="w-full" />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Para accesorios iguales: 1 código × 20 copias para 20 cargadores.</p>
                        <x-input-error for="copias" />
                    </div>
                </div>
                <div class="m-2">
                    <x-label>Tamaño de etiqueta:</x-label>
                    <x-select wire:model="tamano" class="w-full">
                        @foreach ($tamanos as $clave => $t)
                            <option value="{{ $clave }}">{{ $t['nombre'] }}</option>
                        @endforeach
                    </x-select>
                    <x-input-error for="tamano" />
                </div>
                <div class="m-2">
                    <x-label>Nota (opcional):</x-label>
                    <x-input type="text" wire:model="nota" class="w-full" maxlength="255" placeholder="Compra de octubre, cargadores USB-C..." />
                    <x-input-error for="nota" />
                </div>
                <p class="m-2 text-xs text-gray-500 dark:text-gray-400">
                    Cada código es único y no se repite nunca. Al generar se abre la hoja para imprimir.
                </p>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Cancelar</x-secondary-button>
                <x-primary-button class="ml-2" wire:click="generar()" wire:loading.attr="disabled">Generar e imprimir</x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
