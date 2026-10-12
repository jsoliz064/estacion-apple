<div class="transition-colors duration-300">
    @if ($selectedModelId)
        <div class="text-center mb-4">
            <button wire:click="clearSelection"
                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
                <i class="fas fa-times mr-1"></i> Borrar filtro
            </button>
        </div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 gap-3">
        @foreach ($models as $model)
            @if ($model->productos_count > 0)
                <div wire:click="selectModel({{ $model->id }})"
                    class="relative rounded-xl p-2 shadow-sm border 
                        @if ($selectedModelId == $model->id) border-brand-500 dark:border-brand-400 bg-brand-50/30 dark:bg-brand-900/20
                        @else border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 @endif
                        hover:border-brand-400 dark:hover:border-brand-500
                        hover:shadow-md hover:scale-[1.02] 
                        active:scale-[0.98] transition-all duration-200 ease-out cursor-pointer
                        group flex flex-col items-center">

                    {{-- Botón para ver detalles --}}
                    <div class="absolute -top-2 -left-2 z-10">
                        <button wire:click.stop="showModelDetails({{ $model->id }})"
                            class="p-1 rounded-full text-gray-400 hover:text-brand-600 hover:bg-brand-50 dark:hover:text-brand-400 dark:hover:bg-gray-700 transition-colors"
                            title="Ver Detalle">
                            <i class="fa-solid fa-info-circle"></i>
                        </button>
                    </div>

                    <div class="text-center mb-1 px-1 w-full truncate">
                        <span
                            class="text-sm font-semibold 
                                @if ($selectedModelId == $model->id) text-brand-600 dark:text-brand-400
                                @else text-gray-800 dark:text-gray-100 @endif
                                group-hover:text-brand-600 dark:group-hover:text-brand-400
                                transition-colors duration-200">
                            {{ $model->nombre }}
                        </span>
                    </div>

                    @if ($model->productos_count > 0)
                        <div class="absolute -top-2 -right-2">
                            <span
                                class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-brand-600 text-xs font-semibold text-white shadow-sm">
                                {{ $model->productos_count }}
                            </span>
                        </div>
                    @endif

                    <div class="w-full mt-2 space-y-2 text-left">
                        @foreach ($model->sucursales_summary as $summary)
                            {{-- Solo mostrar la sección de la sucursal si tiene productos para este modelo --}}
                            @if ($summary['total'] > 0)
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-1.5">
                                    <h4 class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-1 text-center">
                                        {{ $summary['nombre'] }}</h4>
                                    <div class="w-full flex justify-center gap-1 flex-wrap">
                                        @if ($summary['inventario'] > 0)
                                            <span
                                                class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/60 text-green-800 dark:text-green-200 font-medium"
                                                title="Inventario: {{ $summary['inventario'] }}">
                                                <i class="fa-solid fa-box-open text-[0.7rem] mr-1 w-3 text-center"></i>
                                                <span class="ml-0.5">{{ $summary['inventario'] }}</span>
                                            </span>
                                        @endif

                                        @if ($summary['oferta'] > 0)
                                            <span
                                                class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-800 dark:text-purple-200 font-medium"
                                                title="De ellos, en oferta: {{ $summary['oferta'] }}">
                                                <i class="fa-solid fa-tags text-[0.7rem] mr-1 w-3 text-center"></i>
                                                <span class="ml-0.5">{{ $summary['oferta'] }}</span>
                                            </span>
                                        @endif

                                        @if ($summary['reparacion'] > 0)
                                            <span
                                                class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-red-100 dark:bg-red-900/60 text-red-800 dark:text-red-200 font-medium"
                                                title="Reparación: {{ $summary['reparacion'] }}">
                                                <i class="fa-solid fa-wrench text-[0.7rem] mr-1 w-3 text-center"></i>
                                                <span class="ml-0.5">{{ $summary['reparacion'] }}</span>
                                            </span>
                                        @endif
                                        @if ($summary['fuera'] > 0)
                                            <span
                                                class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 font-medium"
                                                title="Fuera: {{ $summary['fuera'] }}">
                                                <i class="fa-solid fa-ban text-[0.7rem] mr-1 w-3 text-center"></i>
                                                <span class="ml-0.5">{{ $summary['fuera'] }}</span>
                                            </span>
                                        @endif
                                        @if ($summary['reserva'] > 0)
                                            <span
                                                class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-purple-50 dark:bg-purple-900/40 text-purple-900 dark:text-purple-100 font-medium"
                                                title="Reserva: {{ $summary['reserva'] }}">
                                                <i class="fa-solid fa-lock text-[0.7rem] mr-1 w-3 text-center"></i>
                                                <span class="ml-0.5">{{ $summary['reserva'] }}</span>
                                            </span>
                                        @endif
                                        @if ($summary['roto'] > 0)
                                            <span
                                                class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-brand-100 dark:bg-brand-900/60 text-brand-800 dark:text-brand-200 font-medium"
                                                title="Roto: {{ $summary['roto'] }}">
                                                <i class="fa-solid fa-bug text-[0.7rem] mr-1 w-3 text-center"></i>
                                                <span class="ml-0.5">{{ $summary['roto'] }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif
        @endforeach
    </div>

    {{-- Modal de Detalles --}}
    @if ($showDetailsModal && $modelDetailsData)
        <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm"
            wire:click="closeDetailsModal">
            <div class="relative w-full max-w-md bg-white dark:bg-gray-800 rounded-xl shadow-xl overflow-hidden"
                wire:click.stop>
                <div class="p-5">
                    <div class="flex justify-between items-start mb-4 border-b border-gray-100 dark:border-gray-700 pb-3">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2">
                            <i class="fa-solid fa-box-open text-brand-500"></i>
                            {{ $modelDetailsData['nombre'] }}
                        </h3>
                        <button wire:click="closeDetailsModal" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                            <i class="fas fa-times text-lg"></i>
                        </button>
                    </div>
                    
                    <div class="space-y-4 text-sm text-gray-700 dark:text-gray-300 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                        <div class="bg-brand-50 dark:bg-brand-900/20 p-3 rounded-lg border border-brand-100 dark:border-brand-800">
                            <span class="font-semibold text-brand-800 dark:text-brand-300">Total de productos:</span> 
                            <span class="font-bold text-lg text-brand-600 dark:text-brand-400 ml-1">{{ $modelDetailsData['total'] }}</span>
                        </div>
                        
                        @if($modelDetailsData['colors']->count() > 0)
                        <div>
                            <span class="font-semibold text-gray-800 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-1 mb-2 block">Por Color (General):</span>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($modelDetailsData['colors'] as $color)
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-2 rounded">
                                        <span>{{ $color->color ?: 'Sin definir' }}</span>
                                        <span class="font-semibold badge bg-gray-200 dark:bg-gray-600 px-2 py-0.5 rounded-full text-xs">{{ $color->total }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if(isset($modelDetailsData['colorsByEstado']) && $modelDetailsData['colorsByEstado']->count() > 0)
                        <div>
                            <span class="font-semibold text-gray-800 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-1 mb-2 block">Por Color según Grado/Estado:</span>
                            @php
                                $groupedByEstado = $modelDetailsData['colorsByEstado']->groupBy('estado_grado');
                            @endphp
                            @foreach($groupedByEstado as $estado => $items)
                                <div class="mb-3">
                                    <span class="text-xs font-bold text-gray-500 uppercase block mb-1">Grado: {{ $estado ?: 'Sin definir' }}</span>
                                    <div class="grid grid-cols-2 gap-2 mt-1">
                                        @foreach($items as $item)
                                            <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-2 rounded">
                                                <span>{{ $item->color ?: 'Sin definir' }}</span>
                                                <span class="font-semibold badge bg-gray-200 dark:bg-gray-600 px-2 py-0.5 rounded-full text-xs">{{ $item->total }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @endif

                        @if($modelDetailsData['estados']->count() > 0)
                        <div>
                            <span class="font-semibold text-gray-800 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-1 mb-2 block">Por Estado/Grado (General):</span>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($modelDetailsData['estados'] as $estado)
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-2 rounded">
                                        <span>{{ $estado->estado_grado ?: 'Sin definir' }}</span>
                                        <span class="font-semibold badge bg-gray-200 dark:bg-gray-600 px-2 py-0.5 rounded-full text-xs">{{ $estado->total }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        @if($modelDetailsData['almacenamientos']->count() > 0)
                        <div>
                            <span class="font-semibold text-gray-800 dark:text-gray-200 border-b border-gray-200 dark:border-gray-700 pb-1 mb-2 block">Por Almacenamiento:</span>
                            <div class="grid grid-cols-2 gap-2">
                                @foreach($modelDetailsData['almacenamientos'] as $almac)
                                    <div class="flex justify-between items-center bg-gray-50 dark:bg-gray-700/50 p-2 rounded">
                                        <span>{{ $almac->almacenamiento ?: 'Sin definir' }}</span>
                                        <span class="font-semibold badge bg-gray-200 dark:bg-gray-600 px-2 py-0.5 rounded-full text-xs">{{ $almac->total }}</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="mt-6 flex justify-end gap-3 pt-3 border-t border-gray-100 dark:border-gray-700">
                        <button onclick="navigator.clipboard.writeText(this.dataset.texto).then(() => alert('¡Detalles copiados al portapapeles!'))"
                            data-texto="{{ $textToCopy }}"
                            class="px-4 py-2 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 text-gray-800 dark:text-white rounded-lg transition-colors text-sm font-medium flex items-center gap-2">
                            <i class="far fa-copy"></i> Copiar
                        </button>
                        <button wire:click="closeDetailsModal"
                            class="px-4 py-2 bg-brand-600 hover:bg-brand-700 text-white rounded-lg transition-colors text-sm font-medium">
                            Cerrar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>
