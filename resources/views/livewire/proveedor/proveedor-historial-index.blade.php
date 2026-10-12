<div>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('proveedores') }}" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full">Ir a Proveedores</a>
        @if ($deuda > 0)
            @can('pago-proveedor.create')
                <x-button wire:click="pagar">Pagar</x-button>
            @endcan
        @endif
    </div>

    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mt-4 mb-6">{{ $proveedor->nombre }}</h2>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div @class([
            'p-5 rounded-2xl shadow-lg border',
            'bg-amber-100 dark:bg-amber-900 border-amber-200 dark:border-amber-700' => $deuda > 0,
            'bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700' => $deuda <= 0,
        ])>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Se le debe</h3>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">Bs {{ number_format($deuda, 2) }}</p>
        </div>
        <div class="bg-brand-100 dark:bg-brand-900 p-5 rounded-2xl shadow-lg border border-brand-200 dark:border-brand-700">
            <h3 class="text-sm font-medium text-brand-700 dark:text-brand-300">Compras</h3>
            <p class="mt-1 text-2xl font-semibold text-brand-900 dark:text-brand-100">{{ $compras }}</p>
        </div>
        <div class="bg-green-100 dark:bg-green-900 p-5 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-sm font-medium text-green-700 dark:text-green-300">Total comprado</h3>
            <p class="mt-1 text-2xl font-semibold text-green-900 dark:text-green-100">Bs {{ number_format($totalComprado, 2) }}</p>
        </div>
        <div @class([
            'p-5 rounded-2xl shadow-lg border',
            'bg-rose-100 dark:bg-rose-900 border-rose-200 dark:border-rose-700' => $reclamosAbiertos > 0,
            'bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700' => $reclamosAbiertos === 0,
        ])>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Reclamos abiertos</h3>
            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">{{ $reclamosAbiertos }}</p>
        </div>
    </div>

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        @foreach (['compras' => 'Compras', 'pagos' => 'Pagos', 'reclamos' => 'Reclamos' . ($reclamosAbiertos ? ' (' . $reclamosAbiertos . ')' : '')] as $clave => $titulo)
            <button type="button" wire:click="verPestana('{{ $clave }}')" @class([
                'px-4 py-2 text-sm font-semibold border-b-2 -mb-px',
                'border-brand-600 text-brand-700 dark:text-brand-300' => $pestana === $clave,
                'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $pestana !== $clave,
            ])>{{ $titulo }}</button>
        @endforeach
    </div>

    <div class="mt-4">
        @if ($pestana === 'compras')
            @livewire('proveedor.proveedor-compras-table', ['proveedor_id' => $proveedor->id], key('compras-prov-' . $proveedor->id))
        @elseif ($pestana === 'pagos')
            @livewire('proveedor.proveedor-pagos-table', ['proveedor_id' => $proveedor->id], key('pagos-prov-' . $proveedor->id))
        @else
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($reclamos as $reclamo)
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2" wire:key="reclamo-prov-{{ $reclamo->id }}">
                            <span>
                                <span class="font-semibold">{{ trim(($reclamo->producto?->modelo?->nombre ?? 'Equipo') . ' ' . $reclamo->producto?->almacenamiento) }}</span>
                                <span class="font-mono text-xs text-gray-500">IMEI {{ $reclamo->producto?->imei }}</span>
                                <span class="block text-xs text-gray-500">«{{ $reclamo->motivo }}» · {{ $reclamo->created_at->format('d/m/Y') }}
                                    @if ($reclamo->resolucion) · {{ $reclamo->resolucion->label() }}@endif
                                </span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $reclamo->estado->badgeClasses() }}">{{ $reclamo->estado->value }}</span>
                                <a href="{{ route('compras.detalle', $reclamo->compra_id) }}" class="text-xs text-brand-600 hover:underline">Compra #{{ $reclamo->compra_id }}</a>
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">No hay reclamos con este proveedor.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>

    @livewire('cuenta-pagar.modals.pago-proveedor-modal')
    @livewire('cuenta-pagar.modals.pago-proveedor-anular-modal')
</div>
