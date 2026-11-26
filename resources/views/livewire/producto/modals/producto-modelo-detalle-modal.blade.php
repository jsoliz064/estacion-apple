{{-- El detalle de un modelo (ProductoModeloDetalleModal): filtros que se
     combinan, fichas con su numero (cada grupo contado sin su propio filtro) y
     la lista de los equipos que coinciden. --}}
<div>
    @if ($openModal && $modelo)
        <x-dialog-modal wire:model="openModal" maxWidth="lg">
            <x-slot name="title">
                <span class="flex items-center gap-2">
                    <i class="fa-solid fa-box-open text-brand-500"></i>
                    {{ $modelo->nombre }}
                </span>
            </x-slot>

            <x-slot name="content">
                @php
                    $select = 'block w-full h-10 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm';
                    $rotulo = 'block mb-1 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400';
                    $ficha = 'inline-flex items-center gap-1.5 min-h-[2.25rem] px-3 py-1 rounded-full text-sm border transition-colors';
                    $fichaActiva = 'bg-brand-600 border-brand-600 text-white';
                    $fichaNormal = 'bg-white dark:bg-gray-700 border-gray-200 dark:border-gray-600 text-gray-800 dark:text-gray-100 hover:border-brand-400';
                    $sumaEstados = array_sum(array_column($estados, 'total'));
                @endphp

                {{-- Sucursal y estado --}}
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="detalle-modelo-sucursal" class="{{ $rotulo }}">Sucursal</label>
                        <select id="detalle-modelo-sucursal" wire:model.live="sucursalId" class="{{ $select }}">
                            <option value="">Todas las sucursales</option>
                            @foreach ($sucursales as $s)
                                <option value="{{ $s['id'] }}">{{ $s['nombre'] }} ({{ $s['total'] }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="detalle-modelo-estado" class="{{ $rotulo }}">Estado</label>
                        <select id="detalle-modelo-estado" wire:model.live="estado" class="{{ $select }}">
                            @foreach ($estados as $e)
                                <option value="{{ $e['valor'] }}">{{ $e['etiqueta'] }} ({{ $e['total'] }})</option>
                            @endforeach
                            <option value="{{ \App\Livewire\Producto\Modals\ProductoModeloDetalleModal::TODOS }}">Todos ({{ $sumaEstados }})</option>
                        </select>
                    </div>
                </div>

                {{-- El resultado --}}
                <div class="mt-3 flex items-center justify-between gap-3 p-3 rounded-lg bg-brand-50 dark:bg-brand-900/20 border border-brand-100 dark:border-brand-800">
                    <div>
                        <span class="text-sm font-semibold text-brand-800 dark:text-brand-300">Coinciden</span>
                        <span class="ml-1 text-2xl font-bold text-brand-600 dark:text-brand-400" wire:loading.class="opacity-50">{{ $total }}</span>
                        @if ($totalModelo !== null)
                            <span class="block text-xs text-gray-500 dark:text-gray-400">de {{ $totalModelo }} en stock del modelo</span>
                        @endif
                    </div>
                    @if ($hayFiltros)
                        <button type="button" wire:click="limpiarFiltros"
                            class="shrink-0 inline-flex items-center gap-1 px-3 py-2 rounded-full text-xs font-medium bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 border border-gray-200 dark:border-gray-600 hover:bg-gray-100 dark:hover:bg-gray-600">
                            <i class="fas fa-times"></i> Limpiar filtros
                        </button>
                    @endif
                </div>

                <div class="mt-3 max-h-[55vh] overflow-y-auto pr-1 space-y-4">
                    {{-- Las fichas: tocar una filtra, tocarla otra vez la suelta --}}
                    @foreach ($grupos as $faceta => $grupo)
                        @if ($grupo['fichas'])
                            <div wire:key="grupo-{{ $faceta }}">
                                <span class="{{ $rotulo }}">{{ $grupo['titulo'] }}</span>
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach ($grupo['fichas'] as $f)
                                        <button type="button" wire:key="ficha-{{ $faceta }}-{{ $f['valor'] }}"
                                            wire:click="elegir(@js($faceta), @js($f['valor']))"
                                            aria-pressed="{{ $f['activa'] ? 'true' : 'false' }}"
                                            @class([$ficha, $fichaActiva => $f['activa'], $fichaNormal => !$f['activa'], 'opacity-50' => !$f['activa'] && $f['total'] === 0])>
                                            <span>{{ $f['etiqueta'] }}</span>
                                            <span @class([
                                                'px-1.5 rounded-full text-xs font-semibold',
                                                'bg-white/25' => $f['activa'],
                                                'bg-gray-200 dark:bg-gray-600' => !$f['activa'],
                                            ])>{{ $f['total'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endforeach

                    {{-- Los equipos que coinciden --}}
                    <div>
                        <span class="{{ $rotulo }}">Equipos</span>
                        @forelse ($equipos as $p)
                            <div wire:key="equipo-{{ $p->id }}" class="flex items-start justify-between gap-3 py-2 border-b border-gray-100 dark:border-gray-700">
                                <div class="min-w-0">
                                    <p class="font-mono text-sm text-gray-900 dark:text-gray-100 break-all">{{ $p->identificador() }}</p>
                                    <p class="text-xs text-gray-600 dark:text-gray-300">
                                        {{ collect([$p->almacenamiento, $p->color, $p->version ? \App\Enums\ProductoVersion::labelDe($p->version) : null, $p->estado_grado])->filter()->implode(' · ') }}
                                    </p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $p->sucursal?->nombre ?? 'Sin sucursal' }}
                                        @if ($p->estado !== \App\Enums\ProductoEstado::Inventario->value)
                                            · <span class="font-semibold">{{ \App\Enums\ProductoEstado::labelDe($p->estado) }}</span>
                                        @endif
                                    </p>
                                </div>
                                <div class="shrink-0 flex flex-col items-end gap-1 text-sm text-gray-900 dark:text-gray-100">
                                    <x-monto :valor="$p->precio" class="items-end" />
                                    @can('producto.detalle')
                                        <button type="button" wire:click="$dispatch('openProductoDetalleModal', { id: {{ $p->id }} })"
                                            class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:underline">
                                            Ver detalle
                                        </button>
                                    @endcan
                                </div>
                            </div>
                        @empty
                            <p class="py-2 text-sm text-gray-500 dark:text-gray-400">No hay equipos con estos filtros.</p>
                        @endforelse

                        @if ($equipos->count() < $total)
                            <div class="mt-3 text-center">
                                <button type="button" wire:click="mostrarMas"
                                    class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600">
                                    Mostrar más ({{ $equipos->count() }} de {{ $total }})
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            </x-slot>

            <x-slot name="footer">
                <div class="flex gap-3" x-data="{ copiado: false }">
                    <button type="button" data-texto="{{ $texto }}"
                        x-on:click="navigator.clipboard.writeText($el.dataset.texto).then(() => { copiado = true; setTimeout(() => copiado = false, 1500) })"
                        class="px-4 py-2 bg-white dark:bg-gray-600 hover:bg-gray-200 dark:hover:bg-gray-500 text-gray-800 dark:text-white rounded-lg border border-gray-200 dark:border-gray-500 text-sm font-medium inline-flex items-center gap-2">
                        <i class="far fa-copy"></i>
                        <span x-text="copiado ? '¡Copiado!' : 'Copiar'">Copiar</span>
                    </button>
                    <x-secondary-button wire:click="closeModal" wire:loading.attr="disabled">Cerrar</x-secondary-button>
                </div>
            </x-slot>
        </x-dialog-modal>
    @endif
</div>
