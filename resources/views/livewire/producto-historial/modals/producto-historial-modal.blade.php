<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">
                <p class="text-center">
                    Detalle del evento Nro.{{ $productoHistorial->id }} · {{ $productoHistorial->created_at?->format('d/m/Y H:i') }}
                </p>
            </x-slot>

            <x-slot name="content">
                <hr>
                <div class="m-2">
                    <x-label>Descripcion:</x-label>
                    <x-input type="text" value="{{ $productoHistorial->descripcion }}" class="w-full"
                        disabled="true"></x-input>
                </div>

                <div class="m-2">
                    <x-label>Evento:</x-label>
                    <div class="mt-1">{!! \App\Enums\BitacoraEvento::badge($productoHistorial->evento) !!}</div>
                </div>

                {{-- Lo que cambio, con su antes y su despues. No existia: el
                     historial viejo solo guardaba el estado. --}}
                @if ($productoHistorial->cambios)
                    <div class="m-2">
                        <x-label>Cambios:</x-label>
                        <x-bitacora-cambios :cambios="$productoHistorial->cambios" />
                    </div>
                @endif

                @if ($productoHistorial->producto_reparacion_id)
                    <div class="m-2">
                        <x-label>ID de Reparacion:</x-label>
                        <x-input type="text" value="{{ $productoHistorial->producto_reparacion_id }}" class="w-full"
                            disabled="true"></x-input>
                    </div>
                @endif

                @if ($productoHistorial->venta_id)
                    <div class="m-2">
                        <x-label>ID de Venta:</x-label>
                        <x-input type="text" value="{{ $productoHistorial->venta_id }}" class="w-full"
                            disabled="true"></x-input>
                    </div>
                @endif

                <hr>

                {{-- Se condiciona por la RELACION y no por estado == 'Reparacion'.
                     finalizarReparacion() escribe la fila de historial con el
                     estado del producto, que para entonces ya es 'Inventario',
                     conservando el producto_reparacion_id: con la comprobacion
                     anterior esas filas mostraban solo el ID pelado. --}}
                {{-- Una fila de repuesto cobrado lleva producto_reparacion_id,
                     venta_id Y estado 'Vendido', asi que disparaba las cuatro
                     condiciones de abajo: pintaba la reparacion entera y el
                     cliente, el total y la tabla de productos DEL TELEFONO como
                     si fueran del repuesto. Con tres piezas cobradas salian
                     tres modales identicos. Esta fila habla de SU repuesto
                     (eventos cobro / cobro-anulado). --}}
                @if (in_array($productoHistorial->evento, ['cobro', 'cobro-anulado'], true))
                    <x-repuesto-cobrado-detalle :historial="$productoHistorial" />
                @else
                    @if ($productoHistorial->reparacion)
                        <x-reparacion-detalle :reparacion="$productoHistorial->reparacion" />
                    @endif

                    @if ($productoHistorial->venta && in_array($productoHistorial->evento, \App\Enums\ProductoEstado::vendidos(), true))
                    @php
                        $venta = $productoHistorial->venta;
                    @endphp

                    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
                        <div>
                            <x-label>Cliente:</x-label>
                            <x-input type="text" value="{{ $venta->nombreCliente() ?? 'Sin cliente' }}" class="w-full" disabled />
                        </div>

                        <div>
                            <x-label>Vendido por:</x-label>
                            <x-input type="text" value="{{ $venta->user->name ?? 'Sin usuario' }}" class="w-full"
                                disabled />
                        </div>

                        <div>
                            <x-label>Subtotal (Bs):</x-label>
                            <x-input type="text" value="{{ number_format($venta->subtotal, 2) }}" class="w-full"
                                disabled />
                        </div>

                        <div>
                            <x-label>Descuento (Bs):</x-label>
                            <x-input type="text" value="{{ number_format($venta->descuento, 2) }}" class="w-full"
                                disabled />
                        </div>

                        <div>
                            <x-label>Total (Bs):</x-label>
                            <x-input type="text" value="{{ number_format($venta->total, 2) }}" class="w-full"
                                disabled />
                        </div>

                    </div>

                    <div class="m-4">
                        <h2 class="text-lg font-semibold text-gray-800">Detalles de la venta</h2>
                        <div class="overflow-x-auto mt-2">
                            <table class="min-w-full bg-white border border-gray-300 shadow-sm">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-2 text-left border-b">Artículo</th>
                                        <th class="px-4 py-2 text-left border-b">Cant.</th>
                                        <th class="px-4 py-2 text-left border-b">Costo</th>
                                        <th class="px-4 py-2 text-left border-b">Precio</th>
                                        <th class="px-4 py-2 text-left border-b">Descuento</th>
                                        <th class="px-4 py-2 text-left border-b">Subtotal</th>
                                        <th class="px-4 py-2 text-left border-b">Garantía (meses)</th>
                                        <th class="px-4 py-2 text-left border-b">Fecha Expiración</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($venta->detalles as $detalle)
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-2 border-b">{{ $detalle->descripcion() }}</td>
                                            <td class="px-4 py-2 border-b">{{ (int) $detalle->cantidad }}</td>
                                            <td class="px-4 py-2 border-b">{{ number_format($detalle->costo, 2) }}
                                            </td>
                                            <td class="px-4 py-2 border-b">{{ number_format($detalle->precio, 2) }}
                                            </td>
                                            <td class="px-4 py-2 border-b">{{ number_format($detalle->descuento, 2) }}
                                            </td>
                                            <td class="px-4 py-2 border-b">{{ number_format($detalle->subtotal, 2) }}
                                            </td>
                                            <td class="px-4 py-2 border-b">{{ $detalle->garantia_meses }}</td>
                                            <td class="px-4 py-2 border-b">{{ $detalle->garantia_fecha_exp ? \Carbon\Carbon::parse($detalle->garantia_fecha_exp)->format('d/m/Y') : '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    @endif
                @endif

            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal()" wire:loading.attr="disabled">
                    Cerrar
                </x-secondary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
