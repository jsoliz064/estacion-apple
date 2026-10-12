{{-- El reparto actual del stock: sin esto el usuario elige la sucursal a ciegas. --}}
<div class="mt-4">
    <x-label>Stock por sucursal</x-label>
    <div class="mt-1 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden text-sm">
        <table class="min-w-full">
            <thead class="bg-gray-100 dark:bg-gray-700">
                <tr>
                    <th class="p-2 text-left font-medium">Sucursal</th>
                    <th class="p-2 text-right font-medium">Unidades</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($reparto as $fila)
                    <tr class="border-t border-gray-200 dark:border-gray-700">
                        <td class="p-2">{{ $fila->sucursal?->nombre ?? 'Sin sucursal' }}</td>
                        <td class="p-2 text-right {{ (int) $fila->cantidad < 0 ? 'text-red-600 font-semibold' : '' }}">
                            {{ (int) $fila->cantidad }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="p-3 text-center text-gray-400">Sin stock en ninguna sucursal.</td>
                    </tr>
                @endforelse
                <tr class="border-t border-gray-200 dark:border-gray-700 font-semibold">
                    <td class="p-2">Total</td>
                    <td class="p-2 text-right">{{ (int) $total }}</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
