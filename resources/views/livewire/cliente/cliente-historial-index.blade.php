<div>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <a href="{{ route('clientes') }}"
            class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full">
            Ir a Clientes</a>
        @if ($deuda > 0)
            @can('pago.create')
                <x-button wire:click="cobrar">Cobrar</x-button>
            @endcan
        @endif
    </div>

    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mt-4 mb-1">
        {{ $cliente->nombre }}
    </h2>

    {{-- Solo las partes que existen: un «Sin CI» inventado es peor que el hueco. --}}
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
        {{ implode(' · ', array_filter([
            $cliente->ci ? 'CI ' . $cliente->ci : null,
            $cliente->telefono ? 'Tel. ' . $cliente->telefono : null,
            $cliente->correo,
            $cliente->direccion,
        ])) }}
    </p>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div @class([
            'p-5 rounded-2xl shadow-lg border',
            'bg-amber-100 dark:bg-amber-900 border-amber-200 dark:border-amber-700' => $deuda > 0,
            'bg-gray-100 dark:bg-gray-900 border-gray-200 dark:border-gray-700' => $deuda <= 0,
        ])>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300">Deuda pendiente</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                Bs {{ number_format($deuda, 2) }}
            </p>
        </div>

        <div class="bg-brand-100 dark:bg-brand-900 p-5 rounded-2xl shadow-lg border border-brand-200 dark:border-brand-700">
            <h3 class="text-sm font-medium text-brand-700 dark:text-brand-300">Compras</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-brand-900 dark:text-brand-100">{{ $ordenes }}</p>
        </div>

        <div class="bg-green-100 dark:bg-green-900 p-5 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-sm font-medium text-green-700 dark:text-green-300">Total comprado</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                Bs {{ number_format($totalGastado, 2) }}
            </p>
        </div>

        <div class="bg-gray-100 dark:bg-gray-900 p-5 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-600 dark:text-gray-400">Última compra</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-white">
                {{ $ultimaCompra ? \Carbon\Carbon::parse($ultimaCompra)->format('d/m/Y') : '—' }}
            </p>
        </div>
    </div>

    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700">
        @foreach (['compras' => 'Compras', 'pagos' => 'Pagos', 'reservas' => 'Reservas' . ($reservasActivas ? ' (' . $reservasActivas . ')' : ''), 'garantias' => 'Garantías vigentes'] as $clave => $titulo)
            <button type="button" wire:click="verPestana('{{ $clave }}')" @class([
                'px-4 py-2 text-sm font-semibold border-b-2 -mb-px',
                'border-brand-600 text-brand-700 dark:text-brand-300' => $pestana === $clave,
                'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300' => $pestana !== $clave,
            ])>{{ $titulo }}</button>
        @endforeach
    </div>

    <div class="mt-4">
        @if ($pestana === 'compras')
            @livewire('cliente.cliente-ordenes-table', ['cliente_id' => $cliente->id], key('ordenes-table-' . $cliente->id))
        @elseif ($pestana === 'pagos')
            @livewire('cliente.cliente-pagos-table', ['cliente_id' => $cliente->id], key('pagos-table-' . $cliente->id))
        @elseif ($pestana === 'reservas')
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($reservas as $reserva)
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2" wire:key="reserva-{{ $reserva->id }}">
                            <span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">
                                    {{ trim(($reserva->producto?->modelo?->nombre ?? 'Equipo') . ' ' . $reserva->producto?->almacenamiento . ' ' . $reserva->producto?->color) }}
                                </span>
                                <span class="block text-xs text-gray-500 font-mono">IMEI {{ $reserva->producto?->imei }}</span>
                                <span class="block text-xs text-gray-500">
                                    {{ $reserva->created_at->format('d/m/Y') }} · seña Bs {{ number_format((float) $reserva->sena, 2) }} en {{ $reserva->metodo?->nombre }}
                                    @if ($reserva->sena_destino) · {{ $reserva->sena_destino->label() }} @endif
                                </span>
                            </span>
                            <span class="flex items-center gap-2">
                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold {{ $reserva->estado->badgeClasses() }}">{{ $reserva->estado->label() }}</span>
                                @if ($reserva->venta_id)
                                    <a href="{{ route('ventas.detalles', $reserva->venta_id) }}" class="text-xs text-brand-600 hover:underline">Venta #{{ $reserva->venta_id }}</a>
                                @elseif ($reserva->estaActiva())
                                    @can('venta.create')
                                        <a href="{{ route('ventas.crear', ['reserva' => $reserva->id]) }}" class="text-xs text-brand-600 hover:underline">Concretar</a>
                                    @endcan
                                @endif
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">No tiene reservas.</li>
                    @endforelse
                </ul>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4">
                <ul class="divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($garantias as $linea)
                        @php($dias = (int) now()->startOfDay()->diffInDays($linea->garantia_fecha_exp, false))
                        <li class="py-2 flex flex-wrap items-center justify-between gap-2" wire:key="garantia-{{ $linea->id }}">
                            <span>
                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $linea->descripcion() }}</span>
                                <span class="block text-xs text-gray-500 font-mono">IMEI {{ $linea->producto?->imei }}</span>
                                <a href="{{ route('ventas.detalles', $linea->venta_id) }}" class="text-xs text-brand-600 hover:underline">Venta #{{ $linea->venta_id }}</a>
                            </span>
                            <span class="text-right">
                                Vence el {{ \Carbon\Carbon::parse($linea->garantia_fecha_exp)->format('d/m/Y') }}
                                <span class="block text-xs {{ $dias <= 15 ? 'text-amber-700 dark:text-amber-300 font-semibold' : 'text-gray-500' }}">
                                    {{ $dias === 0 ? 'vence hoy' : 'faltan ' . $dias . ' día(s)' }}
                                </span>
                            </span>
                        </li>
                    @empty
                        <li class="py-2 text-gray-500">No tiene equipos con garantía vigente.</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>

    @livewire('cobranza.modals.cobro-modal')
    @livewire('cobranza.modals.pago-anular-modal')
</div>
