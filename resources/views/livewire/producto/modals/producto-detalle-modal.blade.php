{{-- El detalle de un equipo (ProductoDetalleModal): lo que antes eran las
     columnas Compra, Venta, Baja, Garantia y Trabajo Externo de la tabla. --}}
<div>
    @if ($openModal)
        <x-dialog-modal wire:model="openModal">
            <x-slot name="title">Detalle del equipo</x-slot>

            <x-slot name="content">
                @if (!$producto)
                    <p class="text-sm text-gray-500 dark:text-gray-400">El equipo ya no existe.</p>
                @else
                    @php
                        $fila = 'flex justify-between gap-3 py-1.5 border-b border-gray-100 dark:border-gray-700 text-sm';
                        $rotulo = 'text-gray-500 dark:text-gray-400 shrink-0';
                        $valor = 'text-right text-gray-900 dark:text-gray-100 min-w-0 break-words';
                        $titulo = 'mt-5 mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
                        $compra = $producto->compraDetalle?->compra;
                        $linea = $producto->ventaDetalle;
                        $venta = $linea?->venta;
                    @endphp

                    {{-- El equipo --}}
                    <p class="text-base font-semibold text-gray-900 dark:text-gray-100">
                        {{ trim(($producto->modelo?->nombre ?? 'Equipo') . ' ' . $producto->almacenamiento . ' ' . $producto->color) }}
                    </p>
                    <div class="mt-2">
                        @if ($producto->imei)
                            <div class="{{ $fila }}"><span class="{{ $rotulo }}">IMEI</span><span class="{{ $valor }} font-mono">{{ $producto->imei }}</span></div>
                        @endif
                        @if ($producto->upc)
                            <div class="{{ $fila }}"><span class="{{ $rotulo }}">Serial</span><span class="{{ $valor }} font-mono break-all">{{ $producto->upc }}</span></div>
                        @endif
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Estado</span><span class="{{ $valor }}">{{ \App\Enums\ProductoEstado::labelDe($producto->estado) }}</span></div>
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Grado</span><span class="{{ $valor }}">{{ \App\Enums\ProductoGrado::labelDe($producto->estado_grado) }}</span></div>
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Batería</span><span class="{{ $valor }}">{{ $producto->bateria_porcentaje }}%</span></div>
                        @if ($producto->version)
                            <div class="{{ $fila }}"><span class="{{ $rotulo }}">Versión</span><span class="{{ $valor }}">{{ $producto->version }}</span></div>
                        @endif
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Sucursal</span><span class="{{ $valor }}">{{ $producto->sucursal?->nombre ?? '—' }}</span></div>
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Precio</span><span class="{{ $valor }}"><x-monto :valor="$producto->precio" class="items-end" /></span></div>
                    </div>

                    {{-- De donde vino --}}
                    <p class="{{ $titulo }}">Origen</p>
                    @if ($compra)
                        <div class="{{ $fila }}">
                            <span class="{{ $rotulo }}">Compra</span>
                            <span class="{{ $valor }}">
                                @can('compra.detalle')
                                    <a href="{{ route('compras.detalle', $compra->id) }}" class="text-brand-600 dark:text-brand-400 underline">#{{ $compra->id }}</a>
                                @else
                                    #{{ $compra->id }}
                                @endcan
                                · {{ $compra->fecha?->format('d/m/Y') }}
                            </span>
                        </div>
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Proveedor</span><span class="{{ $valor }}">{{ $compra->proveedor?->nombre ?? '—' }}</span></div>
                    @elseif ($producto->permuta)
                        <div class="{{ $fila }}">
                            <span class="{{ $rotulo }}">Permuta</span>
                            <span class="{{ $valor }}">
                                Lo entregó un cliente en la venta
                                @can('venta.detalle')
                                    <a href="{{ route('ventas.detalles', $producto->permuta->venta_id) }}" class="text-brand-600 dark:text-brand-400 underline">#{{ $producto->permuta->venta_id }}</a>
                                @else
                                    #{{ $producto->permuta->venta_id }}
                                @endcan
                            </span>
                        </div>
                    @else
                        <p class="py-1.5 text-sm text-gray-500 dark:text-gray-400">Sin compra registrada.</p>
                    @endif

                    {{-- A quien se vendio --}}
                    <p class="{{ $titulo }}">Venta</p>
                    @if ($linea && $venta)
                        <div class="{{ $fila }}">
                            <span class="{{ $rotulo }}">Venta</span>
                            <span class="{{ $valor }}">
                                @can('venta.detalle')
                                    <a href="{{ route('ventas.detalles', $venta->id) }}" class="text-brand-600 dark:text-brand-400 underline">#{{ $venta->id }}</a>
                                @else
                                    #{{ $venta->id }}
                                @endcan
                                · {{ $venta->created_at?->format('d/m/Y') }}
                            </span>
                        </div>
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Cliente</span><span class="{{ $valor }}">{{ $venta->nombreCliente() ?? 'Sin cliente' }}</span></div>
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Vendido en</span><span class="{{ $valor }}">{{ \App\Support\Dinero::usd($linea->subtotal) }}</span></div>
                        <div class="{{ $fila }}">
                            <span class="{{ $rotulo }}">Garantía</span>
                            <span class="{{ $valor }}">
                                @if ($linea->garantia_fecha_exp)
                                    {{ $linea->garantia_meses }} mes(es), hasta el {{ $linea->garantia_fecha_exp->format('d/m/Y') }}
                                    @if ($linea->garantia_fecha_exp->endOfDay()->isPast())
                                        <span class="ml-1 rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 dark:bg-gray-700 dark:text-gray-300">vencida</span>
                                    @else
                                        <span class="ml-1 rounded bg-green-100 px-1.5 py-0.5 text-xs text-green-800 dark:bg-green-900/40 dark:text-green-300">vigente</span>
                                    @endif
                                @else
                                    Sin garantía
                                @endif
                            </span>
                        </div>
                    @else
                        <p class="py-1.5 text-sm text-gray-500 dark:text-gray-400">No se ha vendido.</p>
                    @endif

                    {{-- Garantia o trabajo externo en curso --}}
                    <p class="{{ $titulo }}">Garantía y trabajo externo en curso</p>
                    @forelse ($pendientes as $rep)
                        <div class="{{ $fila }}">
                            <span class="{{ $rotulo }}">{{ \App\Enums\ReparacionTipo::from($rep->tipo)->label() }}</span>
                            <span class="{{ $valor }}">
                                {{ $rep->tecnico?->nombre ?? 'Sin técnico' }}
                                @if ($rep->fecha_entrega)
                                    · entrega {{ \Carbon\Carbon::parse($rep->fecha_entrega)->format('d/m/Y') }}
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="py-1.5 text-sm text-gray-500 dark:text-gray-400">Ninguno.</p>
                    @endforelse

                    {{-- Baja --}}
                    @if ($producto->estaDadoDeBaja())
                        <p class="{{ $titulo }}">Baja</p>
                        <div class="{{ $fila }}"><span class="{{ $rotulo }}">Motivo</span><span class="{{ $valor }}">{{ \App\Enums\BajaMotivo::labelDe($producto->motivo_baja) }}</span></div>
                        <div class="{{ $fila }}">
                            <span class="{{ $rotulo }}">Fecha</span>
                            <span class="{{ $valor }}">{{ $producto->dado_de_baja_at->format('d/m/Y H:i') }}@if ($producto->bajaUser) · {{ $producto->bajaUser->name }}@endif</span>
                        </div>
                        @if ($producto->nota_baja)
                            <div class="{{ $fila }}"><span class="{{ $rotulo }}">Nota</span><span class="{{ $valor }}">{{ $producto->nota_baja }}</span></div>
                        @endif
                    @endif
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">Cerrar</x-secondary-button>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
