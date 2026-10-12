<div>
    <div class="flex items-center justify-between">
        <a href="{{ route('compras') }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium bg-gray-600 text-white rounded-lg shadow hover:bg-gray-700 transition">
            Volver
        </a>
        @can('compra.edit')
            <a href="{{ route('compras.editar', $compra->id) }}" class="inline-flex items-center px-3 py-1.5 text-sm font-medium bg-brand-600 text-white rounded-lg shadow hover:bg-brand-700 transition">
                Editar compra
            </a>
        @endcan
    </div>

    <div class="text-center my-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">Compra #{{ $compra->id }} {!! $compra->estado()->badge() !!} {!! $compra->badgePago() !!}</h2>
        <p class="text-sm text-gray-600 dark:text-gray-300">
            {{ $compra->proveedor?->nombre }} · {{ $compra->fecha->format('d/m/Y') }}
            · Entra a {{ $compra->sucursal?->nombre ?? '—' }}
            @if ($compra->finalizada_at)
                · Finalizada el {{ $compra->finalizada_at->format('d/m/Y H:i') }}
            @endif
        </p>
    </div>

    @if ($compra->esBorrador())
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100">
            <p>
                <strong>Borrador.</strong> Lo que cargas se guarda al momento, pero nada entra al stock ni se puede vender hasta finalizar la compra.
            </p>
            @can('compra.finalizar')
                <x-button wire:click="finalizar" wire:loading.attr="disabled">Finalizar compra</x-button>
            @endcan
        </div>
    @endif

    <div class="grid grid-cols-3 gap-3 mb-4">
        <div class="bg-brand-100 dark:bg-brand-900 p-3 rounded-xl border border-brand-200 dark:border-brand-700">
            <p class="text-xs text-brand-700 dark:text-brand-300">Equipos</p>
            <p class="text-xl font-semibold text-brand-900 dark:text-brand-100">{{ $equipos }}</p>
        </div>
        <div class="bg-blue-100 dark:bg-blue-900 p-3 rounded-xl border border-blue-200 dark:border-blue-700">
            <p class="text-xs text-blue-700 dark:text-blue-300">Artículos</p>
            <p class="text-xl font-semibold text-blue-900 dark:text-blue-100">{{ $articulos->sum('cantidad') }}</p>
        </div>
        <div class="bg-desert-100 dark:bg-desert-900 p-3 rounded-xl border border-desert-200 dark:border-desert-700">
            <p class="text-xs text-desert-700 dark:text-desert-200">Total</p>
            <p class="text-xl font-semibold text-desert-900 dark:text-desert-50">Bs {{ number_format((float) $compra->total, 2) }}</p>
        </div>
    </div>

    {{-- Cuentas por pagar: lo pagado al proveedor y lo que se le debe. --}}
    <div class="mb-4 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap gap-6 text-sm">
                <div><span class="text-gray-500">Pagado</span><br><span class="font-semibold">Bs {{ number_format((float) $compra->pagado, 2) }}</span></div>
                <div><span class="text-gray-500">Saldo</span><br>
                    <span class="font-semibold {{ $compra->saldoPendiente() > 0 ? 'text-amber-700 dark:text-amber-300' : '' }}">Bs {{ number_format($compra->saldoPendiente(), 2) }}</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                @if ($compra->saldoPendiente() > 0)
                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">Por pagar</span>
                    @can('pago-proveedor.create')
                        <x-button wire:click="registrarPago">Registrar pago</x-button>
                    @endcan
                @elseif ((float) $compra->total > 0)
                    <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800">Pagada{{ $compra->pagada_at ? ' el ' . $compra->pagada_at->format('d/m/Y') : '' }}</span>
                @endif
            </div>
        </div>
        @if ($compra->pagos->isNotEmpty())
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                @foreach ($compra->pagos as $pago)
                    <li class="py-2 flex flex-wrap items-center justify-between gap-2" wire:key="pago-{{ $pago->id }}">
                        <span class="text-gray-700 dark:text-gray-200">
                            {{ $pago->fecha->format('d/m/Y H:i') }} · {{ $pago->descripcion() }}
                            <span class="text-xs text-gray-500">{{ $pago->user ? '· ' . $pago->user->name : '' }}{{ $pago->nota ? ' · ' . $pago->nota : '' }}</span>
                        </span>
                        <span class="flex items-center gap-3">
                            <span class="font-semibold">Bs {{ number_format((float) $pago->monto, 2) }}</span>
                            @can('pago-proveedor.anular')
                                <button type="button" wire:click="anularPago({{ $pago->id }})" class="text-xs text-red-600 hover:underline">Anular</button>
                            @endcan
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    {{-- Reclamos al proveedor: los abiertos primero. --}}
    @if ($compra->reclamos->isNotEmpty())
        <div class="mb-4 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
            <h3 class="font-semibold text-gray-800 dark:text-gray-100">Reclamos al proveedor</h3>
            <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                @foreach ($compra->reclamos as $reclamo)
                    <li class="py-2 flex flex-wrap items-center justify-between gap-2" wire:key="reclamo-{{ $reclamo->id }}">
                        <span>
                            <span class="font-mono">IMEI {{ $reclamo->producto?->imei }}</span>
                            · {{ trim(($reclamo->producto?->modelo?->nombre ?? '') . ' ' . $reclamo->producto?->almacenamiento) }}
                            <span class="block text-xs text-gray-500">«{{ $reclamo->motivo }}» · {{ $reclamo->created_at->format('d/m/Y') }}
                                @if ($reclamo->resolucion) · {{ $reclamo->resolucion->label() }}@endif
                                @if ($reclamo->reemplazo) · reemplazo IMEI {{ $reclamo->reemplazo->imei }}@endif
                            </span>
                        </span>
                        <span class="flex items-center gap-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $reclamo->estado->badgeClasses() }}">{{ $reclamo->estado->value }}</span>
                            @if ($reclamo->estaAbierto())
                                @can('compra.reclamo')
                                    <button type="button" wire:click="cerrarReclamo({{ $reclamo->id }})" class="text-xs text-brand-600 hover:underline">Cerrar reclamo</button>
                                @endcan
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-collapse-card title="Agregar equipos por modelo" :open="false">
        @livewire('compra-lote.compra-model-selector', ['compraId' => $compra->id])
    </x-collapse-card>

    @can('producto.estado-masivo')
        <div class="flex justify-start mb-2">
            <button wire:click="openProductoEstadoMasivoModal()" wire:loading.attr="disabled"
                class="inline-flex items-center px-3 py-1.5 text-sm font-medium bg-yellow-600 text-white rounded-lg shadow hover:bg-yellow-700 disabled:opacity-50 transition"
                title="Cambiar estado o tipo de venta de los equipos de esta compra">
                Cambio masivo
            </button>
        </div>
    @endcan

    @livewire('compra-lote.compra-lote-table', ['compraId' => $compra->id])

    {{-- Abierta en borrador: es donde se esta cargando. --}}
    <x-collapse-card title="Repuestos y accesorios de la compra" :open="$compra->esBorrador() || $articulos->isNotEmpty()">
        @livewire('compra-lote.compra-articulos', ['compraId' => $compra->id], key('compra-articulos-' . $compra->id))
    </x-collapse-card>

    @livewire('compra-lote.modals.compra-lote-add-model-modal', ['compra_lote' => $compra])
    @livewire('compra-lote.modals.compra-lote-producto-edit-modal')
    @livewire('compra-lote.modals.compra-lote-producto-destroy-modal')
    @livewire('producto.modals.producto-estado-modal')
    @livewire('producto.modals.producto-fotos-modal')
    @livewire('producto.modals.producto-estado-masivo-modal')
    @livewire('compra.modals.reclamo-abrir-modal')
    @livewire('compra.modals.reclamo-cerrar-modal')
    @livewire('cuenta-pagar.modals.pago-proveedor-modal')
    @livewire('cuenta-pagar.modals.pago-proveedor-anular-modal')
    @can('compra.finalizar')
        @livewire('compra.modals.compra-finalizar-modal')
    @endcan
    @can('compra.edit')
        @livewire('articulo.modals.articulo-selector-modal')
        @livewire('articulo.modals.articulo-form-modal', ['tipo' => 'Repuesto', 'conStock' => false], key('alta-repuesto'))
        @livewire('articulo.modals.articulo-form-modal', ['tipo' => 'Accesorio', 'conStock' => false], key('alta-accesorio'))
    @endcan
    @livewire('reparacion.modals.repuestos-reparacion-modal')
</div>
