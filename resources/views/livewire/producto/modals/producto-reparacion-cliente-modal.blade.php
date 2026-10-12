<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            @php
                $esExterno = $tipo === \App\Enums\ReparacionTipo::Externo->value;
            @endphp

            <x-slot name="title">
                <p class="text-center">
                    {{ $esExterno ? 'Trabajo Externo' : 'Garantia' }} de Producto: {{ $producto->imei ?? '' }}
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Descripcion:</x-label>
                    <x-input type="text" value="{{ $producto->descripcion }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                    <div>
                        <x-label>Nro de Venta:</x-label>
                        <x-input type="text" value="{{ $producto->ventaDetalle?->venta_id }}" class="w-full"
                            disabled="true"></x-input>
                    </div>

                    <div>
                        <x-label>Fecha de Venta:</x-label>
                        <x-input type="text" value="{{ $producto->ventaDetalle?->created_at }}" class="w-full"
                            disabled="true"></x-input>
                    </div>

                    <div>
                        <x-label>Vendido a:</x-label>
                        {{-- El cliente vive en la cabecera de la venta, no en la linea. --}}
                        <x-input type="text" class="w-full" disabled="true"
                            value="{{ $producto->ventaDetalle?->venta?->nombreCliente() ?? 'Sin cliente' }}"></x-input>
                    </div>
                </div>

                <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                    <div>
                        <x-label>Garantia Meses:</x-label>
                        <x-input type="text" value="{{ $detalle?->garantia_meses }}" class="w-full"
                            disabled="true"></x-input>
                    </div>

                    <div>
                        <x-label>Fecha de vencimiento:</x-label>
                        <x-input type="text" value="{{ $detalle?->garantia_fecha_exp }}" class="w-full"
                            disabled="true"></x-input>
                    </div>
                </div>

                <hr>

                <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                    <div>
                        <x-label>
                            Tecnico:
                            <span class="ml-2 font-normal">{{ $tecnico?->nombre }}</span>
                        </x-label>
                        <x-select-tecnicos wire:model="reparacion.tecnico_id" :options="$tecnicos"
                            placeholder="Seleccione una tecnico" />
                        <x-input-error for="reparacion.tecnico_id"></x-input-error>
                    </div>

                    <div>
                        <x-label>Fecha de Entrega:</x-label>
                        <x-input type="date" wire:model="reparacion.fecha_entrega" class="w-full"></x-input>
                        <x-input-error for="reparacion.fecha_entrega"></x-input-error>
                    </div>

                </div>

                <div class="m-2">
                    <x-label>Repuestos Técnico:</x-label>
                    <textarea wire:model.defer="reparacion.repuestos_tecnico" rows="2"
                        class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                    <x-input-error for="reparacion.repuestos_tecnico"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Repuestos Propios:</x-label>
                    <textarea wire:model.defer="reparacion.repuestos_propios" rows="2"
                        class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                    <x-input-error for="reparacion.repuestos_propios"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Repuestos a Devolver:</x-label>
                    <textarea wire:model.defer="reparacion.repuestos_devolver" rows="2"
                        class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                    <x-input-error for="reparacion.repuestos_devolver"></x-input-error>
                </div>

                @include('livewire.reparacion.partials.repuestos-reparacion', ['titulo' => 'Repuestos propios'])

                <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                    <div>
                        <x-label>Mano de obra del técnico (Bs):</x-label>
                        <x-input type="number" wire:model.lazy="reparacion.costo" class="w-full"
                            onfocus="this.select()"></x-input>
                        <x-input-error for="reparacion.costo"></x-input-error>
                    </div>

                    <div>
                        <x-label>Costo Repuestos (Bs):</x-label>
                        <x-input type="number" wire:model.lazy="reparacion.costo_repuestos" class="w-full"
                            onfocus="this.select()"></x-input>
                        <x-input-error for="reparacion.costo_repuestos"></x-input-error>
                    </div>
                    <div>
                        <x-label>Total reparación (Bs):</x-label>
                        <x-input type="number" :value="$reparacion['costo_total'] ?? 0" class="w-full" disabled="true"></x-input>
                    </div>
                </div>

                {{-- Solo el trabajo externo se le cobra al cliente: una garantia
                     la asumimos nosotros. --}}
                @if ($esExterno)
                    @php
                        $cobro = (float) ($reparacion['cobro_cliente'] ?? 0);
                        // Con el campo recien abierto el cobro vale 0, y restarle
                        // el costo mostraba un negativo en rojo que parecia un
                        // error sin serlo. Hasta que se escriba una cifra no hay
                        // ganancia que calcular.
                        $hayCobro = $cobro > 0;
                        $ganancia = $cobro - (float) ($reparacion['costo_total'] ?? 0);
                    @endphp
                    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <div>
                            <x-label>Cobrado al Cliente (Bs):</x-label>
                            <x-input type="number" wire:model.lazy="reparacion.cobro_cliente" class="w-full"
                                onfocus="this.select()"></x-input>
                            <x-input-error for="reparacion.cobro_cliente"></x-input-error>
                        </div>

                        <div>
                            <x-label>Ganancia del Trabajo (Bs):</x-label>
                            <x-input type="text" value="{{ $hayCobro ? number_format($ganancia, 2) : '—' }}"
                                class="w-full" disabled="true"></x-input>
                            @if ($hayCobro)
                                <p class="mt-1 text-xs {{ $ganancia >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    Cobro ({{ number_format($cobro, 2) }}) menos el costo del trabajo
                                    ({{ number_format($reparacion['costo_total'] ?? 0, 2) }}).
                                </p>
                            @else
                                <p class="mt-1 text-xs text-gray-500">
                                    Indica cuánto le cobras al cliente para ver la ganancia.
                                </p>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="mt-4">
                    <div class="animate-fade-in m-2">
                        <label class="flex items-center">
                            <input type="checkbox" wire:model="reparacion.garantia_tecnico"
                                class="form-checkbox h-5 w-5 text-brand-600 transition duration-150 ease-in-out rounded dark:bg-gray-700 dark:border-gray-600">
                            <span class="ml-2 text-gray-700 dark:text-gray-200">Garantia del Tecnico</span>
                        </label>
                        <x-input-error for="reparacion.garantia_tecnico" class="mt-1" />
                    </div>
                </div>

                @if (isset($reparacion['id']))
                    <x-primary-button class="ml-2" wire:click="finalizarReparacion()" wire:loading.attr="disabled">
                        Marcar Como Terminado
                    </x-primary-button>
                @endif

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cancelar
                </x-secondary-button>

                <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                    {{ isset($reparacion['id']) ? 'Actualizar' : 'Guardar' }}
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
