{{-- Buscador comun de venta y compra (CarritoBuscadorTrait).
     Un solo campo para IMEI, codigo de barras, SKU o nombre. La pistola USB
     "teclea" el codigo y manda Enter: agregarPorCodigo() agrega directo si hay
     una coincidencia exacta. En el celular, la camara (x-boton-escaner) hace lo
     mismo: escribe el codigo y manda Enter.

     El Enter manda $el.value y vacia el campo en el acto: el wire:model va con
     debounce, asi que la pistola aprieta Enter antes de que $busqueda tenga el
     codigo, y el servidor recibia el campo vacio o a medias.
     Variables: $placeholder, $conCatalogo (bool), $escanerContinuo (bool). --}}
@php($habilitado = $this->sucursalDelDocumento() !== null)

<div class="relative">
    <div class="flex gap-2" data-escaner>
        <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                <i class="fa-solid fa-barcode"></i>
            </span>
            <input type="text" wire:model.live.debounce.400ms="busqueda"
                x-on:keydown.enter.prevent="$wire.agregarPorCodigo($el.value); $el.value = ''"
                @disabled(!$habilitado) autocomplete="off" inputmode="search"
                placeholder="{{ $habilitado ? ($placeholder ?? 'Escanee o escriba IMEI, código, SKU o nombre...') : $this->motivoSinSucursal() }}"
                class="w-full pl-10 rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:ring-brand-500 focus:border-brand-500 disabled:bg-gray-100 disabled:cursor-not-allowed dark:disabled:bg-gray-800">
        </div>
        <x-boton-escaner :continuo="$escanerContinuo ?? false" :disabled="!$habilitado" />
        @if ($conCatalogo ?? true)
            <button type="button" wire:click="abrirSelectorArticulos" @disabled(!$habilitado)
                class="px-3 py-2 text-sm rounded-md border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 disabled:opacity-40 whitespace-nowrap">
                <i class="fa-solid fa-list"></i> Catálogo
            </button>
        @endif
    </div>

    @if (!empty($resultados))
        <ul class="absolute z-30 mt-1 w-full max-h-72 overflow-y-auto rounded-md border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-lg">
            @foreach ($resultados as $r)
                <li wire:key="res-{{ $r['tipo'] }}-{{ $r['id'] }}">
                    <button type="button" wire:click="seleccionarResultado('{{ $r['tipo'] }}', {{ $r['id'] }})"
                        class="w-full text-left px-3 py-2 hover:bg-brand-50 dark:hover:bg-gray-700 flex items-center justify-between gap-2">
                        <span class="min-w-0">
                            {!! \App\Enums\LineaTipo::badge($r['tipo']) !!}
                            <span class="font-medium text-gray-900 dark:text-gray-100">{{ $r['etiqueta'] }}</span>
                            <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ $r['detalle'] }}</span>
                        </span>
                        <span class="text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">Bs {{ number_format($r['precio'], 2) }}</span>
                    </button>
                </li>
            @endforeach
        </ul>
    @endif
</div>
