<div>
    @if ($openModal && $reclamo)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Cerrar reclamo #{{ $reclamo->id }}</x-slot>
            <x-slot name="content">
                <hr>
                <p class="m-2 text-sm text-gray-700 dark:text-gray-300">
                    {{ trim(($reclamo->producto?->modelo?->nombre ?? 'Equipo') . ' ' . $reclamo->producto?->almacenamiento . ' ' . $reclamo->producto?->color) }}
                    · IMEI <span class="font-mono">{{ $reclamo->producto?->imei }}</span>
                    · costo Bs {{ number_format((float) $reclamo->producto?->costo_unidad, 2) }}
                    <span class="block text-xs text-gray-500">«{{ $reclamo->motivo }}» · {{ $reclamo->compra?->proveedor?->nombre }} · desde el {{ $reclamo->created_at->format('d/m/Y') }}</span>
                </p>

                <div class="m-2 space-y-2">
                    @foreach (\App\Enums\ReclamoResolucion::cases() as $opcion)
                        <label class="flex items-start gap-2 text-sm text-gray-800 dark:text-gray-200">
                            <input type="radio" wire:model.live="resolucion" value="{{ $opcion->value }}" class="mt-1 text-brand-600 focus:ring-brand-500">
                            <span>
                                <span class="font-semibold">{{ $opcion->label() }}</span>
                                <span class="block text-xs text-gray-500">
                                    @switch($opcion->value)
                                        @case('Reemplazo') Entra el equipo nuevo en la misma compra, con el mismo costo. El fallado vuelve al proveedor. @break
                                        @case('Descuento') El fallado vuelve al proveedor y su costo sale del total de la compra. @break
                                        @default El negocio se queda el equipo, reparado o como está.
                                    @endswitch
                                </span>
                            </span>
                        </label>
                    @endforeach
                    <x-input-error for="resolucion" />
                </div>

                @if ($resolucion === 'Reemplazo')
                    <div class="m-2 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="sm:col-span-2">
                            <x-label value="IMEI del reemplazo" />
                            <div class="mt-1 flex gap-2" data-escaner>
                                <x-input type="text" class="w-full" wire:model="imei" inputmode="numeric" autocomplete="off"
                                    placeholder="Escanee o escriba el IMEI" x-on:keydown.enter.prevent="" />
                                <x-boton-escaner modo="input" />
                            </div>
                            <x-input-error for="imei" />
                        </div>
                        <div>
                            <x-label value="Batería (%)" />
                            <x-input type="number" min="1" max="100" class="mt-1 w-full" wire:model="bateria_porcentaje" />
                            <x-input-error for="bateria_porcentaje" />
                        </div>
                        <div>
                            <x-label value="Color" />
                            <select wire:model="color" class="mt-1 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                <option value="">El mismo ({{ $reclamo->producto?->color }})</option>
                                @foreach ($colores as $c)
                                    <option value="{{ $c->value }}">{{ $c->value }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                @elseif ($resolucion === 'Aceptado')
                    <div class="m-2 text-sm">
                        <x-label value="Vuelve a" />
                        <select wire:model="destino" class="mt-1 block w-48 h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                            <option value="Inventario">Inventario</option>
                            <option value="Roto">Roto</option>
                        </select>
                        <x-input-error for="destino" />
                    </div>
                @endif

                <div class="m-2">
                    <x-label value="Nota (opcional)" />
                    <x-input type="text" class="mt-1 w-full" wire:model="nota" maxlength="255" />
                </div>
                <x-input-error for="detalles" class="m-2" />
            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">Volver</x-secondary-button>
                <x-button class="ml-2" wire:click="cerrar()" wire:loading.attr="disabled" wire:target="cerrar">Cerrar reclamo</x-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
