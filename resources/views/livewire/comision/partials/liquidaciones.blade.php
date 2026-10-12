{{-- Lista de liquidaciones. El componente que la incluye tiene verLiquidacion($id). --}}
@if ($liquidaciones->isEmpty())
    <p class="text-sm text-gray-500 dark:text-gray-400">No hay liquidaciones.</p>
@else
    <div class="overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200">
                <tr>
                    <th class="p-2 text-left">#</th>
                    <th class="p-2 text-left">Fecha</th>
                    <th class="p-2 text-left">Persona</th>
                    <th class="p-2 text-left">Período</th>
                    <th class="p-2 text-right">Comisiones</th>
                    <th class="p-2 text-right">Total</th>
                    <th class="p-2 text-left">Pagó</th>
                    <th class="p-2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach ($liquidaciones as $l)
                    <tr class="border-t border-gray-200 dark:border-gray-700" wire:key="liq-{{ $l->id }}">
                        <td class="p-2">{{ $l->id }}</td>
                        <td class="p-2 whitespace-nowrap">{{ $l->created_at->format('d/m/Y H:i') }}</td>
                        <td class="p-2">{{ $l->beneficiarioNombre() }}</td>
                        <td class="p-2 whitespace-nowrap">{{ $l->desde->format('d/m/Y') }} – {{ $l->hasta->format('d/m/Y') }}</td>
                        <td class="p-2 text-right">{{ $l->cantidad }}</td>
                        <td class="p-2 text-right font-semibold">Bs {{ number_format((float) $l->total, 2) }}</td>
                        <td class="p-2">{{ $l->pagadoPor?->name ?? '—' }}</td>
                        <td class="p-2 text-right">
                            <button type="button" wire:click="verLiquidacion({{ $l->id }})"
                                class="px-2 py-1 rounded-md bg-gray-200 dark:bg-gray-700 text-xs font-semibold hover:bg-gray-300">Ver</button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
