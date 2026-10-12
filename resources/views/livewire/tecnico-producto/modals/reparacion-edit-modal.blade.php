<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                Editar Reparacion Nro. {{ isset($reparacion['id']) ? $reparacion['id'] : '' }}
            </x-slot>

            <x-slot name="content">
                <hr>

                <div class="m-2">
                    <x-label>Producto:</x-label>
                    <x-input type="text" value="{{ $reparacionModel->producto->descripcion }}" class="w-full"
                        disabled="true"></x-input>
                </div>

                <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                    <div>
                        <x-label>Tecnico:</x-label>
                        <x-input type="text" value="{{ $reparacionModel->tecnico->nombre }}" disabled="true"
                            class="w-full"></x-input>
                    </div>

                    <div>
                        <x-label>Fecha de Entrega:</x-label>
                        <x-input type="date" wire:model="reparacion.fecha_entrega" class="w-full"></x-input>
                        <x-input-error for="reparacion.fecha_entrega"></x-input-error>
                    </div>
                </div>

                <div class="m-2">
                    <x-label>Repuestos Técnico:</x-label>
                    <textarea wire:model.defer="reparacion.repuestos_tecnico" rows="3"
                        class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                    <x-input-error for="reparacion.repuestos_tecnico"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Repuestos Propios:</x-label>
                    <textarea wire:model.defer="reparacion.repuestos_propios" rows="3"
                        class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm"></textarea>
                    <x-input-error for="reparacion.repuestos_propios"></x-input-error>
                </div>

                <div class="m-2">
                    <x-label>Repuestos a Devolver:</x-label>
                    <textarea wire:model.defer="reparacion.repuestos_devolver" rows="3"
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
                        @php $comisionLiquidada = $reparacionModel?->comision()->whereNotNull('liquidacion_id')->first(); @endphp
                        @if ($comisionLiquidada)
                            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">
                                Su comisión (Bs {{ number_format((float) $comisionLiquidada->monto, 2) }}) ya se pagó en la liquidación #{{ $comisionLiquidada->liquidacion_id }}: cambiar la mano de obra no la cambia.
                            </p>
                        @endif
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

                <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                    <div>
                        <x-label for="status" value="Estado:" />
                        <select wire:model.live="reparacion.estado" id="status"
                            class="block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200
                                       focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm transition-all duration-200 h-10">
                            <option value="Pendiente">Pendiente</option>
                            <option value="Terminado">Terminado</option>
                        </select>
                        <x-input-error for="reparacion.estado" class="mt-1" />
                    </div>

                    <div>
                        <x-label>Fecha de Recogida:</x-label>
                        <x-input type="date" wire:model="reparacion.fecha_recogida" class="w-full"></x-input>
                        <x-input-error for="reparacion.fecha_recogida"></x-input-error>
                    </div>
                </div>

            </x-slot>
            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>
                <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                    Actualizar
                </x-primary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
