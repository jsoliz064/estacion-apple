{{--
    Filtros comunes de los reportes nuevos: periodo (con atajos de mes) y
    sucursal. $sinPeriodo oculta las fechas (un reporte que es una foto de hoy).
--}}
<div class="mb-6 bg-white dark:bg-gray-800 shadow rounded-lg p-4">
    <div class="flex flex-wrap items-end gap-3">
        @unless ($sinPeriodo ?? false)
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
        @endunless
        <div>
            <x-label value="Sucursal" />
            <select wire:model.live="sucursalId"
                class="mt-1 block border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                <option value="">Todas</option>
                @foreach ($this->sucursales() as $sucursal)
                    <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}{{ $sucursal->activa ? '' : ' (inactiva)' }}</option>
                @endforeach
            </select>
        </div>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
        {{ $this->nombreSucursal() }}@unless ($sinPeriodo ?? false) · {{ $this->periodoTexto() }}@endunless
    </p>
</div>
