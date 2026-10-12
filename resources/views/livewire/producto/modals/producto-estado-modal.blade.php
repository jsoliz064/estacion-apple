<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">Estado del equipo: {{ $producto->imei ?? '' }}</p>
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Descripción:</x-label>
                    <x-input type="text" value="{{ $producto->descripcion }}" class="w-full" disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Sucursal:</x-label>
                    <x-input type="text" value="{{ $producto->sucursal?->nombre }}" class="w-full" disabled="true"></x-input>
                </div>

                @if ($this->esVendido())
                    {{-- Vendido o a credito: lo escribe la venta. Aqui solo se ve y se anula. --}}
                    <div class="m-2 rounded-lg border border-yellow-300 bg-yellow-50 p-3 text-sm text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                        <p class="font-semibold">{{ \App\Enums\ProductoEstado::labelDe($producto->estado) }}
                            @if ($lineaVenta)
                                en la venta
                                <a href="{{ route('ventas.detalles', $lineaVenta->venta_id) }}" class="underline">#{{ $lineaVenta->venta_id }}</a>
                            @endif
                        </p>
                        @if ($lineaVenta)
                            <p class="mt-1">
                                {{ $lineaVenta->venta->nombreCliente() ?? 'Sin cliente' }} · {{ $lineaVenta->created_at->format('d/m/Y') }}
                                · Bs {{ number_format((float) $lineaVenta->subtotal, 2) }}
                                @if ($lineaVenta->garantia_fecha_exp)
                                    · garantía hasta {{ $lineaVenta->garantia_fecha_exp->format('d/m/Y') }}
                                @endif
                            </p>
                        @endif
                    </div>
                    @can('venta.detalle.delete')
                        <div class="m-2">
                            <x-danger-button wire:click="cancelarVenta()" wire:loading.attr="disabled">Anular la venta de este equipo</x-danger-button>
                        </div>
                    @endcan
                @elseif ($this->enReclamo())
                    {{-- Reclamo: lo escribe ReclamoService. Se cierra desde la compra. --}}
                    <div class="m-2 rounded-lg border border-rose-300 bg-rose-50 p-3 text-sm text-rose-900 dark:border-rose-700 dark:bg-rose-900/40 dark:text-rose-100">
                        @if ($reclamo)
                            <p class="font-semibold">En reclamo a {{ $reclamo->compra?->proveedor?->nombre }}</p>
                            <p class="mt-1">«{{ $reclamo->motivo }}» · desde el {{ $reclamo->created_at->format('d/m/Y') }}</p>
                            @can('compra.detalle')
                                <a href="{{ route('compras.detalle', $reclamo->compra_id) }}" class="mt-1 inline-block underline">Ir a la compra #{{ $reclamo->compra_id }} para cerrar el reclamo</a>
                            @endcan
                        @else
                            <p class="font-semibold">En reclamo, pero sin reclamo abierto registrado.</p>
                        @endif
                    </div>
                @elseif ($this->esReservado())
                    {{-- Reserva: la escribe ReservaService. Aqui se ve, se concreta o se cancela. --}}
                    <div class="m-2 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100">
                        @if ($reserva)
                            <p class="font-semibold">Reservado para {{ $reserva->cliente?->nombre }}</p>
                            <p class="mt-1">
                                Seña Bs {{ number_format((float) $reserva->sena, 2) }} en {{ $reserva->metodo?->nombre }}
                                · desde el {{ $reserva->created_at->format('d/m/Y') }}
                                @if ($reserva->nota) · {{ $reserva->nota }} @endif
                            </p>
                        @else
                            <p class="font-semibold">En reserva, pero sin reserva activa registrada.</p>
                        @endif
                    </div>
                    @if ($reserva)
                        <div class="m-2 flex flex-wrap gap-2">
                            @can('venta.create')
                                <x-primary-button wire:click="concretarReserva()" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-cash-register mr-1"></i> Concretar la venta
                                </x-primary-button>
                            @endcan
                            @can('reserva.cancelar')
                                <x-danger-button wire:click="cancelarReserva()" wire:loading.attr="disabled">Cancelar la reserva</x-danger-button>
                            @endcan
                        </div>
                    @endif
                @else
                    <div class="m-2">
                        <x-label>Estado:</x-label>
                        <x-select wire:model.live="estado" :options="$productoEstados" placeholder="Seleccione un estado" />
                        <x-input-error for="estado"></x-input-error>
                    </div>

                    @if ($producto->estaDisponible())
                        <div class="m-2 flex flex-wrap gap-2">
                            @can('venta.create')
                                <x-primary-button wire:click="vender()" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-cash-register mr-1"></i> Vender este equipo
                                </x-primary-button>
                            @endcan
                            @can('reserva.create')
                                <x-secondary-button wire:click="reservar()" wire:loading.attr="disabled">
                                    <i class="fa-solid fa-bookmark mr-1"></i> Reservar
                                </x-secondary-button>
                            @endcan
                        </div>
                    @endif

                    <hr>

                    @if (in_array($estado, ['Fuera', 'Roto'], true))
                        <div class="m-2">
                            <x-label>
                                @if ($estado === 'Fuera') ¿A quién se le dio o dónde está? @else ¿Qué tiene? @endif
                            </x-label>
                            <x-input type="text" wire:model="nota" class="w-full"></x-input>
                            <x-input-error for="nota"></x-input-error>
                        </div>
                        @if ($estado === 'Fuera' && $estadoOrigen === 'Fuera')
                            <div class="m-2">
                                <x-primary-button wire:click="finalizarFuera()" wire:loading.attr="disabled">El equipo volvió al local</x-primary-button>
                            </div>
                        @endif
                    @endif

                    @if ($estado == 'Reparacion')
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

                        {{-- Las piezas se cargan al crear la reparacion; las de una
                             reparacion abierta se editan en «Editar reparación». --}}
                        @if (empty($reparacion['id']))
                            @include('livewire.reparacion.partials.repuestos-reparacion', ['titulo' => 'Piezas del negocio que se montan'])
                        @else
                            <p class="m-2 text-xs text-gray-500 dark:text-gray-400">Las piezas de esta reparación se editan desde «Editar reparación».</p>
                        @endif

                        <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
                            <div>
                                <x-label>Mano de obra del técnico (Bs):</x-label>
                                <x-input type="number" wire:model.lazy="reparacion.costo" class="w-full" onfocus="this.select()"></x-input>
                                <x-input-error for="reparacion.costo"></x-input-error>
                            </div>
                            <div>
                                <x-label>Repuestos (Bs):</x-label>
                                <x-input type="number" wire:model.lazy="reparacion.costo_repuestos" class="w-full" onfocus="this.select()"></x-input>
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

                        @if (isset($reparacion['id']))
                            <x-primary-button class="ml-2" wire:click="finalizarReparacion()"
                                wire:loading.attr="disabled">
                                Marcar Como Terminado
                            </x-primary-button>
                        @endif

                    @endif
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>

                @unless ($this->esVendido() || $this->esReservado() || $this->enReclamo())
                    <x-primary-button class="ml-2" wire:click="update()" wire:loading.attr="disabled">
                        Actualizar
                    </x-primary-button>
                @endunless
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
