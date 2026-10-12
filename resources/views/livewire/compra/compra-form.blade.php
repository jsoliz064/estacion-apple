<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            {{ $this->esEdicion() ? 'Editar compra #' . $compraId : 'Nueva compra' }}
        </h2>
        <a href="{{ $this->esEdicion() ? route('compras.detalle', $compraId) : route('compras') }}"
            class="text-sm text-brand-600 hover:underline">Volver</a>
    </div>

    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div>
            <x-label>Proveedor</x-label>
            <x-select wire:model="compra.proveedor_id" :options="$proveedores->pluck('nombre', 'id')" placeholder="Seleccione el proveedor" />
            <x-input-error for="compra.proveedor_id" />
        </div>
        <div>
            <x-label>Fecha</x-label>
            <x-input type="date" wire:model="compra.fecha" class="w-full" />
            <x-input-error for="compra.fecha" />
        </div>
        <div>
            <x-label>Sucursal (a donde entra)</x-label>
            @if ($this->esEdicion())
                <x-input type="text" class="w-full" disabled="true"
                    value="{{ $sucursales->firstWhere('id', $compra['sucursal_id'])?->nombre ?? '—' }}" />
                <p class="mt-1 text-xs text-gray-500">Cada línea ya congeló esta sucursal: no se cambia.</p>
            @else
                <x-select wire:model="compra.sucursal_id" :options="$sucursales->pluck('nombre', 'id')" placeholder="Seleccione la sucursal" />
                <x-input-error for="compra.sucursal_id" />
            @endif
        </div>
    </div>

    @if (!$this->esEdicion())
        <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">
            La compra se crea en <strong>borrador</strong>. En su detalle cargas los equipos, repuestos y accesorios:
            cada uno se guarda al momento, así que un corte de internet no hace perder lo cargado.
            Al terminar, <strong>Finalizar compra</strong> mete el stock, libera los equipos para la venta y registra lo pagado al recibir.
        </p>
    @endif

    <div class="mt-4 flex justify-end gap-2">
        <a href="{{ $this->esEdicion() ? route('compras.detalle', $compraId) : route('compras') }}">
            <x-secondary-button>Cancelar</x-secondary-button>
        </a>
        <x-primary-button wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
            {{ $this->esEdicion() ? 'Guardar cambios' : 'Crear compra' }}
        </x-primary-button>
    </div>
</div>
