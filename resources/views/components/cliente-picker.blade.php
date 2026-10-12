@props([
    'search' => '',
    'sugerencias' => [],
    'nombre' => null,
    'clienteId' => null,
    'label' => 'Cliente',
])

{{--
    Elegir el cliente de una venta: teclear, mirar el catálogo, o crearlo al vuelo.

    Lo comparten las CINCO puertas de venta. Antes cada una pedía el cliente con
    un `<x-input type="text">` suelto —y el modal de estado, con un textarea de
    tres filas para un nombre—, con cinco reglas de validación distintas entre sí.

    Los `wire:click` llaman a los métodos de ClienteBuscadorTrait, que el
    componente Livewire que incluye esto tiene que usar. Funciona porque este
    parcial se renderiza dentro del DOM de ese componente.
--}}
<div class="relative" wire:ignore.self>
    <x-label>{{ $label }}</x-label>

    @if ($clienteId)
        {{-- Ya elegido: se muestra la ficha y se puede quitar. El cliente es
             opcional, así que quitarlo es una operación legítima y no un error. --}}
        <div
            class="mt-1 flex items-center justify-between gap-2 rounded-md border border-brand-200 bg-brand-50 px-3 py-2 dark:border-brand-700 dark:bg-brand-900">
            <x-cliente-etiqueta :nombre="$nombre" class="text-sm font-medium text-brand-900 dark:text-brand-100" />

            <button type="button" wire:click="quitarCliente" wire:loading.attr="disabled"
                class="text-xs text-brand-700 hover:underline dark:text-brand-300">
                Quitar
            </button>
        </div>
    @else
        <x-input type="text" class="w-full" placeholder="Nombre, CI o teléfono..."
            wire:model.live.debounce.500ms="searchCliente" wire:keydown.escape="$set('searchCliente', '')"
            wire:keydown.tab="$set('searchCliente', '')" autocomplete="off" />

        @if (!empty($search))
            <ul
                class="absolute z-20 mt-1 w-full overflow-auto rounded-md border border-gray-300 bg-white shadow-lg max-h-60 dark:border-gray-600 dark:bg-gray-700">
                @forelse ($sugerencias as $cliente)
                    <li class="cursor-pointer px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-gray-600"
                        wire:click="selectCliente({{ $cliente->id }})">
                        <x-cliente-etiqueta :nombre="$cliente->nombre" :ci="$cliente->ci" :telefono="$cliente->telefono" />
                    </li>
                @empty
                    {{-- El hueco donde el caso real pide crear: se teclea un nombre,
                         no aparece, y se da de alta sin perder lo escrito ni salir
                         de la venta. --}}
                    <li class="px-4 py-2 text-sm">
                        <span class="text-gray-400">Ningún cliente coincide.</span>
                        @can('cliente.create')
                            <button type="button" wire:click="openClienteCreateModal"
                                class="ml-1 font-medium text-brand-600 hover:underline dark:text-brand-400">
                                Crear «{{ $search }}»
                            </button>
                        @endcan
                    </li>
                @endforelse
            </ul>
        @endif

        <div class="mt-2 flex flex-wrap gap-2">
            <x-secondary-button type="button" wire:click="openClienteSelector">
                Buscar en clientes...
            </x-secondary-button>

            @can('cliente.create')
                <x-secondary-button type="button" wire:click="openClienteCreateModal">
                    Nuevo cliente
                </x-secondary-button>
            @endcan
        </div>

        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
            Opcional: una venta de mostrador puede quedarse sin cliente.
        </p>
    @endif
</div>
