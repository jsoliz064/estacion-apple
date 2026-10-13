@php
    $thead = 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200';
    $fila = 'border-t border-gray-200 dark:border-gray-700';
    $boton = 'inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs font-semibold border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';
@endphp
{{-- Tras generar, la hoja de stickers se abre sola en otra pestaña. Si el
     navegador bloquea la ventana emergente, el lote queda desplegado con su
     boton de imprimir. --}}
<div x-on:abrir-impresion.window="window.open($event.detail.url, '_blank')">
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-2">Códigos de barras</h2>
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
        Stickers con un código único para pegar en la mercadería. Al cargar el equipo (en su UPC) o el
        artículo (en su SKU o UPC), se escanea el sticker y el código queda asignado.
    </p>

    <div class="flex flex-wrap items-end justify-between gap-3">
        @can('codigo.generar')
            <x-primary-button wire:click="openCodigoGenerarModal()">
                <i class="fa-solid fa-barcode mr-2"></i> Generar códigos
            </x-primary-button>
        @endcan

        <div>
            <x-label>Tamaño de etiqueta:</x-label>
            <x-select wire:model.live="tamano" class="w-full sm:w-80">
                @foreach ($tamanos as $clave => $t)
                    <option value="{{ $clave }}">{{ $t['nombre'] }}</option>
                @endforeach
            </x-select>
        </div>
    </div>

    <div class="mt-4 bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="{{ $thead }}">
                <tr>
                    <th class="p-2 text-left">Lote</th>
                    <th class="p-2 text-left">Fecha</th>
                    <th class="p-2 text-left">Generó</th>
                    <th class="p-2 text-right">Códigos</th>
                    <th class="p-2 text-right">Copias</th>
                    <th class="p-2 text-right">Asignados</th>
                    <th class="p-2 text-left">Nota</th>
                    <th class="p-2 text-right">Imprimir</th>
                </tr>
            </thead>
            <tbody class="text-gray-800 dark:text-gray-200">
                @forelse ($lotes as $lote)
                    @php($usados = (int) ($asignados[$lote->id] ?? 0))
                    <tr class="{{ $fila }}" wire:key="lote-{{ $lote->id }}">
                        <td class="p-2">
                            <button type="button" wire:click="alternar({{ $lote->id }})" class="inline-flex items-center gap-1 font-semibold hover:underline">
                                <i @class(['fa-solid text-xs', 'fa-chevron-down' => $abierto === $lote->id, 'fa-chevron-right' => $abierto !== $lote->id])></i>
                                #{{ $lote->id }}
                            </button>
                        </td>
                        <td class="p-2 whitespace-nowrap">{{ $lote->created_at->format('d/m/Y H:i') }}</td>
                        <td class="p-2">{{ $lote->user?->name ?? '—' }}</td>
                        <td class="p-2 text-right">{{ $lote->cantidad }}</td>
                        <td class="p-2 text-right">{{ $lote->copias }}</td>
                        <td class="p-2 text-right whitespace-nowrap">
                            {{ $usados }} / {{ $lote->cantidad }}
                        </td>
                        <td class="p-2">{{ $lote->nota }}</td>
                        <td class="p-2 text-right whitespace-nowrap">
                            <a href="{{ route('codigos.imprimir', ['id' => $lote->id, 'tam' => $tamano]) }}" target="_blank" class="{{ $boton }}">
                                <i class="fa-solid fa-print"></i> Todos
                            </a>
                            @if ($usados > 0 && $usados < $lote->cantidad)
                                <a href="{{ route('codigos.imprimir', ['id' => $lote->id, 'tam' => $tamano, 'libres' => 1]) }}" target="_blank" class="{{ $boton }} ml-1">
                                    Libres
                                </a>
                            @endif
                        </td>
                    </tr>

                    @if ($abierto === $lote->id)
                        <tr wire:key="lote-{{ $lote->id }}-codigos">
                            <td colspan="8" class="p-0">
                                <div class="bg-gray-50 dark:bg-gray-900 px-4 py-3">
                                    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach ($codigos as $codigo)
                                            <li class="py-1.5 flex flex-wrap items-center gap-x-3 gap-y-1" wire:key="codigo-{{ $codigo }}">
                                                <span class="font-mono tracking-wider">{{ $codigo }}</span>
                                                @if ($duenos->has($codigo))
                                                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-200 text-gray-800 dark:bg-gray-700 dark:text-gray-100">Asignado</span>
                                                    <span class="text-xs text-gray-600 dark:text-gray-300">{{ implode(' · ', $duenos[$codigo]) }}</span>
                                                @else
                                                    <span class="px-2 py-0.5 rounded-full text-xs bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-100">Libre</span>
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr>
                        <td colspan="8" class="p-6 text-center text-gray-500 dark:text-gray-400">Todavía no se generó ningún código.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-2">{{ $lotes->links() }}</div>

    @livewire('codigo.modals.codigo-generar-modal')
</div>
