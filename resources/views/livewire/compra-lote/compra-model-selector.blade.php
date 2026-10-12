<div class="pb-20 transition-colors duration-300">
    <div
        class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-3 max-w-6xl mx-auto px-2">
        @foreach ($models as $model)
            <button wire:click="openModalSelector({{ $model->id }})"
                class="relative rounded-xl p-2 shadow-sm border 
                        bg-white dark:bg-gray-800
                        border-gray-200 dark:border-gray-700
                        hover:border-brand-400 dark:hover:border-brand-500
                        hover:shadow-md hover:scale-[1.02] 
                        active:scale-[0.98] active:bg-brand-50 dark:active:bg-brand-900/20
                        transition-all duration-200 ease-out cursor-pointer
                        group flex flex-col items-center">

                <div class="text-center mb-1 px-1 w-full truncate">
                    <span
                        class="text-xs font-semibold 
                                text-gray-800 dark:text-gray-100
                                group-hover:text-brand-600 dark:group-hover:text-brand-400
                                transition-colors duration-200">
                        {{ $model->nombre }}
                    </span>
                </div>

                @if ($model->productos_count > 0)
                    <div class="absolute -top-2 -right-2 z-10">
                        <span
                            class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-brand-600 text-xs font-semibold text-white shadow-sm">
                            {{ $model->productos_count }}
                        </span>
                    </div>
                @endif

                <div class="w-full mt-2 space-y-2 text-left">
                    @foreach ($model->sucursales_summary as $summary)
                        {{-- Solo mostrar la sección de la sucursal si tiene productos --}}
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

                                    @if ($summary['reserva'] > 0)
                                        <span
                                            class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-900/60 text-gray-800 dark:text-gray-200 font-medium"
                                            title="Reserva: {{ $summary['reserva'] }}">
                                            <i class="fa-solid fa-truck text-[0.7rem] mr-1 w-3 text-center"></i>
                                            <span class="ml-0.5">{{ $summary['reserva'] }}</span>
                                        </span>
                                    @endif

                                    @if ($summary['oferta'] > 0)
                                        <span
                                            class="inline-flex items-center text-[0.6rem] px-2 py-0.5 rounded-full bg-purple-100 dark:bg-purple-900/60 text-purple-800 dark:text-purple-200 font-medium"
                                            title="Productos en Oferta: {{ $summary['oferta'] }}">
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

                <div class="absolute top-0.5 right-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                    <div class="h-1.5 w-1.5 rounded-full bg-brand-500 dark:bg-brand-400 animate-ping"></div>
                </div>
            </button>
        @endforeach
    </div>
</div>
