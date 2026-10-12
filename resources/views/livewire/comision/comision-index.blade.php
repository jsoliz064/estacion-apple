<div>
    <h2 class="text-2xl font-bold text-gray-800 dark:text-white mb-4">Comisiones</h2>

    <x-comision-cifras :cifras="$cifras" class="mb-4" />

    {{-- El periodo del reporte --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <div class="flex flex-wrap items-end gap-3">
            <div>
                <x-label value="Desde" />
                <x-input type="date" class="mt-1" wire:model.live="desde" />
            </div>
            <div>
                <x-label value="Hasta" />
                <x-input type="date" class="mt-1" wire:model.live="hasta" />
            </div>
            <div class="flex gap-2">
                <x-secondary-button wire:click="mes(-1)" title="Mes anterior">&laquo;</x-secondary-button>
                <x-secondary-button wire:click="mes(0)">Este mes</x-secondary-button>
                <x-secondary-button wire:click="mes(1)" title="Mes siguiente">&raquo;</x-secondary-button>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-xl">
                <p class="text-sm text-gray-600 dark:text-gray-400">Ganado del {{ $periodoTexto }}</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-white">Bs {{ number_format($ganadoPeriodo, 2) }}</p>
            </div>
            <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-xl">
                <p class="text-sm text-gray-600 dark:text-gray-400">Pagado del {{ $periodoTexto }}</p>
                <p class="text-xl font-semibold text-gray-900 dark:text-white">Bs {{ number_format($pagadoPeriodo, 2) }}</p>
            </div>
        </div>
    </div>

    {{-- Por persona --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Por persona</h3>
        @if ($porPersona->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Todavía no hay comisiones.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                        <tr>
                            <th class="p-2 text-left">Persona</th>
                            <th class="p-2 text-right">Pendiente</th>
                            <th class="p-2 text-right">Por pagar</th>
                            <th class="p-2 text-right">Ganado en el período</th>
                            <th class="p-2 text-right">Pagado en el período</th>
                            <th class="p-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($porPersona as $p)
                            <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="com-persona-{{ $p->clave }}">
                                <td class="p-2">
                                    <span class="font-semibold text-gray-800 dark:text-gray-100">{{ $p->nombre }}</span>
                                    <span class="block text-xs text-gray-500">{{ $p->rol }}</span>
                                </td>
                                <td class="p-2 text-right">{{ number_format($p->pendiente, 2) }}</td>
                                <td class="p-2 text-right font-semibold text-blue-700 dark:text-blue-300">{{ number_format($p->por_pagar, 2) }}</td>
                                <td class="p-2 text-right">{{ number_format($p->ganado, 2) }}</td>
                                <td class="p-2 text-right">{{ number_format($p->pagado, 2) }}</td>
                                <td class="p-2 text-right whitespace-nowrap">
                                    <button type="button" wire:click="verDetalle('{{ $p->clave }}')"
                                        class="px-2 py-1 rounded-md bg-gray-200 dark:bg-gray-700 text-xs font-semibold hover:bg-gray-300">Detalle</button>
                                    @can('comision.liquidar')
                                        @if ($p->por_pagar > 0)
                                            <button type="button" wire:click="liquidar('{{ $p->clave }}')"
                                                class="ml-1 px-2 py-1 rounded-md bg-brand-600 text-white text-xs font-semibold hover:bg-brand-700">Liquidar</button>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Detalle: el reporte de comisiones --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-2">
        @livewire('comision.comision-table')
    </div>

    {{-- Liquidaciones del periodo --}}
    <div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
        <h3 class="font-semibold text-gray-800 dark:text-gray-100 mb-2">Liquidaciones del {{ $periodoTexto }}</h3>
        @include('livewire.comision.partials.liquidaciones', ['liquidaciones' => $liquidaciones])
    </div>

    @can('comision.liquidar')
        @livewire('comision.modals.comision-liquidar-modal')
    @endcan
    @livewire('comision.modals.liquidacion-ver-modal')
</div>
