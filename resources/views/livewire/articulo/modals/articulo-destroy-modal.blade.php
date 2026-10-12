<div>
    @if ($openModal && $articulo)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Eliminar {{ mb_strtolower(\App\Enums\ArticuloTipo::from($tipo)->label()) }}: {{ $articulo->nombre }}
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Nombre:</x-label>
                    <x-input type="text" value="{{ $articulo->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Stock por sucursal:</x-label>
                    <div class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                        {!! $articulo->desgloseStock() !!}
                    </div>
                </div>

                @if ($movimientos > 0)
                    <div class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">Tiene {{ $movimientos }} movimiento(s): compras, ventas, transferencias o bajas.</p>
                        <p class="mt-1">No se puede eliminar: su historia se perdería. Si ya no se vende, déjalo con stock en cero.</p>
                    </div>
                @else
                    <p class="m-2 text-sm text-gray-600 dark:text-gray-300">
                        No tiene movimientos. Al eliminarlo se borra también su stock de todas las sucursales.
                    </p>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>
                @if ($movimientos === 0)
                    <x-danger-button class="ml-2" wire:click="destroy()" wire:loading.attr="disabled">
                        Eliminar
                    </x-danger-button>
                @endif
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
