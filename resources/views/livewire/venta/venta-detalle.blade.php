<div>
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
        <a href="{{ route('ventas') }}" class="text-sm text-brand-600 hover:underline">Volver a ventas</a>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('ventas.nota', $venta->id) }}" target="_blank"
                class="inline-flex items-center px-4 py-2 bg-white border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest shadow-sm hover:bg-gray-50 dark:bg-gray-800 dark:border-gray-500 dark:text-gray-300">
                <i class="fa-solid fa-print mr-1"></i> Imprimir nota
            </a>
            {{-- El recibo en PDF (carta), adjunto por el menu de compartir del celular
                 (Alpine compartirRecibo, en app.js). --}}
            {{-- @php en bloque: mezclar @php(...) con un @php ... @endphp mas abajo
                 hace que Blade se trague todo lo de en medio. --}}
            @php $numeroRecibo = str_pad($venta->id, 6, '0', STR_PAD_LEFT); @endphp
            <button type="button"
                x-data="compartirRecibo({
                    url: @js(route('ventas.pdf', $venta->id)),
                    archivo: @js('Recibo-' . $numeroRecibo . '.pdf'),
                    telefono: @js($venta->fichaCliente?->telefonoWhatsapp()),
                    texto: @js('Estación Apple - Recibo Nº ' . $numeroRecibo . '. Total Bs ' . number_format((float) $venta->total, 2, ',', '.')),
                })"
                x-on:click="compartir()" :disabled="generando"
                class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest shadow-sm hover:bg-green-700 disabled:opacity-60">
                <i class="fa-brands fa-whatsapp mr-1 text-sm"></i>
                <span x-text="generando ? 'Generando...' : 'WhatsApp'">WhatsApp</span>
            </button>
            @if ($venta->aCredito())
                @can('pago.create')
                    <x-button wire:click="cobrar">Cobrar</x-button>
                @endcan
            @endif
            @can('venta.edit')
                <x-secondary-button wire:click="ventaEditar">Editar</x-secondary-button>
            @endcan
            @can('venta.delete')
                <x-danger-button wire:click="anularVenta">Anular venta</x-danger-button>
            @endcan
        </div>
    </div>

    <div class="text-center mb-4">
        <h1 class="text-2xl font-bold text-gray-800 dark:text-white">Venta Nº {{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }}</h1>
        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $venta->created_at->format('d/m/Y H:i') }} · {{ $venta->sucursal?->nombre }}</p>
    </div>

    <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Cliente</h3>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $venta->nombreCliente() ?? 'Sin cliente' }}</p>
        </div>
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Vendedor</h3>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $venta->user?->name ?? '—' }}</p>
        </div>
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Líneas</h3>
            <p class="font-semibold text-gray-900 dark:text-white">{{ $venta->detalles_count }}</p>
        </div>
        <div>
            <h3 class="text-xs font-medium text-gray-500 dark:text-gray-400">Total</h3>
            <p class="text-xl font-bold text-brand-700 dark:text-brand-300">Bs {{ number_format((float) $venta->total, 2) }}</p>
        </div>
    </div>

    <div class="mt-3 bg-white dark:bg-gray-800 shadow rounded-lg p-4 grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
        <div><span class="text-gray-500">Subtotal</span><br>Bs {{ number_format((float) $venta->subtotal, 2) }}</div>
        <div><span class="text-gray-500">Descuento</span><br>Bs {{ number_format((float) $venta->descuento, 2) }}</div>
        <div><span class="text-gray-500">Mano de obra</span><br>Bs {{ number_format((float) $venta->mano_obra, 2) }}</div>
        @can('venta.reporte')
            <div><span class="text-gray-500">Ganancia</span><br><span class="font-semibold text-green-700 dark:text-green-400">Bs {{ number_format($venta->ganancia(), 2) }}</span></div>
        @endcan
    </div>

    {{-- Cobro --}}
    <div class="mt-3 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-6 text-sm">
                <div><span class="text-gray-500">Cobrado</span><br><span class="font-semibold">Bs {{ number_format((float) $venta->pagado, 2) }}</span></div>
                <div><span class="text-gray-500">Saldo</span><br>
                    <span class="font-semibold {{ $venta->aCredito() ? 'text-amber-700 dark:text-amber-300' : '' }}">Bs {{ number_format($venta->saldoPendiente(), 2) }}</span>
                </div>
            </div>
            @if ($venta->aCredito())
                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">A crédito</span>
            @else
                <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">
                    Pagada{{ $venta->pagada_at ? ' el ' . $venta->pagada_at->format('d/m/Y') : '' }}
                </span>
            @endif
        </div>

        <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
            @forelse ($venta->pagos as $pago)
                <li class="py-2 flex flex-wrap items-center justify-between gap-2" wire:key="pago-{{ $pago->id }}">
                    <span class="text-gray-700 dark:text-gray-200">
                        {{ $pago->fecha->format('d/m/Y H:i') }} · {{ $pago->descripcion() }}
                        <span class="text-xs text-gray-500">· {{ $pago->momento->label() }}{{ $pago->user ? ' · ' . $pago->user->name : '' }}{{ $pago->nota ? ' · ' . $pago->nota : '' }}</span>
                    </span>
                    <span class="flex items-center gap-3">
                        <span class="font-semibold">Bs {{ number_format((float) $pago->monto, 2) }}</span>
                        @can('pago.anular')
                            @unless ($pago->esPermuta() || $pago->esSena())
                                <button type="button" wire:click="anularPago({{ $pago->id }})" class="text-xs text-red-600 hover:underline">Anular</button>
                            @endunless
                        @endcan
                    </span>
                </li>
            @empty
                <li class="py-2 text-gray-500">Sin pagos registrados.</li>
            @endforelse
        </ul>

        {{-- La comision del vendedor (docs/05): la ve quien ve todas, o el propio vendedor. --}}
        @php $comision = $venta->comision; @endphp
        @if ($comision && (auth()->user()->can('comision.index') || (auth()->id() === $venta->user_id && auth()->user()->can('comision.propias'))))
            <div class="mt-3 pt-3 border-t border-gray-100 dark:border-gray-700 flex flex-wrap items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                <span>
                    Comisión de {{ $comision->user?->name ?? $venta->user?->name }}
                    ({{ rtrim(rtrim(number_format((float) $comision->porcentaje, 2), '0'), '.') }} % de la ganancia):
                    <span class="font-semibold">Bs {{ number_format((float) $comision->monto, 2) }}</span>
                </span>
                {!! $comision->estado()->badge() !!}
                @if ($comision->liquidacion_id)
                    <span class="text-xs text-gray-500">liquidación #{{ $comision->liquidacion_id }} · ya no cambia si se edita la venta</span>
                @endif
            </div>
        @endif
    </div>

    <div class="mt-6">
        <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-2">Lo que se vendió</h3>
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-hidden py-2 px-2">
            @livewire('venta.venta-detalle-table', ['ventaId' => $venta->id], key('detalle-table-' . $venta->id))
        </div>
    </div>

    @livewire('venta.modals.venta-detalle-destroy-modal')
    @livewire('venta.modals.venta-destroy-modal')
    @livewire('cobranza.modals.cobro-modal')
    @livewire('cobranza.modals.pago-anular-modal')
</div>
