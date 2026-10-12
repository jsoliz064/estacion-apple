<button wire:click="changeVentaRapida({{ $id }})" type="button" class="flex items-center justify-center">
    @if ($active)
        <span class="text-green-500 hover:text-green-700 transition-colors" title="En venta rápida">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </span>
    @else
        <span class="text-gray-400 hover:text-gray-600 transition-colors" title="No en venta rápida">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
            </svg>
        </span>
    @endif
</button>
