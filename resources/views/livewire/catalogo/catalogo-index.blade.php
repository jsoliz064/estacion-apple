<div class="min-h-screen">
    <style>
        input[type="range"]::-webkit-slider-thumb {
            -webkit-appearance: none;
            pointer-events: all;
            width: 16px;
            height: 16px;
        }

        input[type="range"]::-moz-range-thumb {
            pointer-events: all;
            width: 16px;
            height: 16px;
        }
    </style>
    <style>
        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }

        .animate-fade-in-up {
            animation: fadeInUp 0.5s ease-out forwards;
        }

        .animate-slide-up {
            animation: slideUp 0.4s ease-out forwards;
        }

        .delay-100 {
            animation-delay: 0.1s;
        }

        .range-thumb {
            -webkit-appearance: none;
            height: 8px;
            border-radius: 4px;
            outline: none;
        }

        .range-thumb::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: #4a6a40;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .range-thumb::-webkit-slider-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 0 0 4px rgba(74,106,64, 0.2);
        }

        .snap-x {
            scroll-snap-type: x mandatory;
        }

        .snap-center {
            scroll-snap-align: center;
        }
    </style>
    <style>
        .fab-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            transition: transform 0.5s;
            z-index: 999999;
        }

        .fab-wrapper {
            position: relative;
        }

        .fab {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            color: white;
            font-size: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.3);
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
        }

        .fab i {
            display: flex;
            justify-content: center;
            align-items: center;
        }

        #fab1 {
            background-color: #25D366;
        }

        #fab1:hover {
            background-color: #1DA851;
        }


        .tooltip {
            position: absolute;
            bottom: 70px;
            left: 50%;
            transform: translateX(-50%);
            background-color: rgba(0, 0, 0, 0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 14px;
            white-space: nowrap;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .fab-wrapper:hover .tooltip {
            opacity: 1;
            visibility: visible;
        }
    </style>
    <header class="bg-gradient-to-r from-gray-900/90 via-gray-800/90 to-gray-900/90 shadow-2xl relative">
        <div class="absolute inset-0 overflow-hidden z-0">
            <img src="{{ asset('imgs/portada.webp') }}" alt="Portada"
                class="w-full h-full object-cover object-center">
            <div class="absolute inset-0 bg-black/75"></div> <!-- Dark overlay -->
        </div>
        <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 relative">
            <div class="flex flex-col items-center py-8 md:py-10 relative overflow-hidden">
                <div class="absolute inset-0 opacity-5">
                    <div
                        class="absolute inset-0 bg-grid-white/[0.05] [mask-image:linear-gradient(0deg,transparent,black)]">
                    </div>
                </div>

                <div class="flex items-center space-x-4 animate-fade-in-down">
                    <div
                        class="flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg ring-2 ring-gray-300">
                        <img src="{{ asset('imgs/logo-mark.png') }}" alt="Estación Apple" class="w-16 h-16 rounded-full">
                    </div>
                    <div>
                        <h1 class="text-xl md:text-2xl font-extrabold tracking-tight">
                            <span class="text-white">ESTACIÓN APPLE</span>
                        </h1>
                    </div>
                </div>

            </div>
        </div>
    </header>

    <main class="p-8">

        <div class="pb-20 transition-colors duration-300">
            <!-- Available Products Section -->
            <div class="mb-8">
                <div
                    class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-5 lg:grid-cols-7 xl:grid-cols-9 2xl:grid-cols-10 gap-1.5">
                    @foreach ($availableModels as $model)
                        <button wire:click="selectModel('{{ $model->id }}')"
                            class="relative rounded-lg p-1 shadow-sm border 
                          bg-white dark:bg-gray-800
                          border-gray-200 dark:border-gray-700
                          hover:border-brand-400 dark:hover:border-brand-500
                          hover:shadow-md hover:scale-[1.03] 
                          active:scale-[0.98] 
                          transition-all duration-200 ease-out cursor-pointer
                          group
                          {{ $filterModel == $model->id
                              ? '!border-brand-500 dark:!border-brand-500 !bg-brand-50 dark:!bg-brand-900/20 !shadow-md !scale-[1.03]'
                              : '' }}">
                            <div class="text-center">
                                <span
                                    class="text-[0.65rem] font-medium 
                                  text-gray-700 dark:text-gray-200
                                  group-hover:text-brand-600 dark:group-hover:text-brand-400
                                  {{ $filterModel == $model->id ? '!text-brand-600 dark:!text-brand-400' : '' }}
                                  transition-colors duration-200">
                                    {{ $model->nombre }}
                                </span>

                                <!-- Product count badge (only for available products) -->
                                <div class="mt-1">
                                    <span
                                        class="inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-medium rounded-full
                                      bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        {{ $model->productos_disponibles_count }}
                                        {{ $model->productos_disponibles_count == 1 ? 'disponible' : 'disponibles' }}
                                    </span>
                                </div>
                            </div>

                            <div
                                class="absolute top-0.5 right-0.5 opacity-0 group-hover:opacity-100 transition-opacity">
                                <div class="h-1 w-1 rounded-full bg-brand-500 dark:bg-brand-400 animate-ping"></div>
                            </div>

                            <div
                                class="absolute bottom-0 left-0 right-0 h-0.5 mx-auto w-8 bg-gray-100 dark:bg-gray-700 rounded-b-lg">
                            </div>

                            @if ($filterModel == $model->id)
                                <div class="absolute top-0.5 right-0.5">
                                    <div class="h-1.5 w-1.5 rounded-full bg-brand-500 dark:bg-brand-400"></div>
                                </div>
                            @endif

                            <div
                                class="absolute inset-0 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                <div
                                    class="absolute inset-0 bg-gradient-to-br from-transparent via-white/30 dark:via-gray-900/30 to-transparent">
                                </div>
                            </div>
                        </button>
                    @endforeach
                </div>
            </div>
            @if ($filterModel && count($storageOptionsByModel) > 0)
                <div class="mb-6 mt-8">
                    <div class="grid grid-cols-4 sm:grid-cols-5 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-10 gap-1.5">
                        @foreach ($storageOptionsByModel as $storage => $count)
                            <button wire:click="filterByStorage('{{ $storage }}')"
                                class="relative rounded-md p-0.5 shadow-xs border 
                        bg-brand-50 dark:bg-brand-900/30
                        border-brand-100 dark:border-brand-800
                        hover:border-brand-300 dark:hover:border-brand-500
                        hover:shadow-sm hover:scale-[1.02] 
                        active:scale-[0.98] 
                        transition-all duration-150 ease-out cursor-pointer
                        group
                        {{ $filterStorage == $storage
                            ? '!border-brand-400 dark:!border-brand-400 !bg-brand-100 dark:!bg-brand-900/40 !shadow-sm !scale-[1.02] ring-1 ring-brand-200 dark:ring-brand-700'
                            : '' }}">
                                <div class="text-center px-0.5">
                                    <!-- Storage text - smaller and blue-themed -->
                                    <span
                                        class="text-[0.6rem] font-semibold 
                                text-brand-600 dark:text-brand-200
                                group-hover:text-brand-700 dark:group-hover:text-brand-100
                                {{ $filterStorage == $storage ? '!text-brand-700 dark:!text-brand-100' : '' }}
                                transition-colors duration-150">
                                        {{ $storage }}
                                    </span>

                                    <!-- Count badge - more compact -->
                                    <div class="mt-0.5">
                                        <span
                                            class="inline-flex items-center justify-center px-1 py-0.5 text-[0.6rem] font-medium rounded-full
                                    bg-brand-100 text-brand-800 dark:bg-brand-800/80 dark:text-brand-100
                                    group-hover:bg-brand-200 group-hover:text-brand-900 dark:group-hover:bg-brand-700 dark:group-hover:text-brand-50
                                    {{ $filterStorage == $storage ? '!bg-brand-200 !text-brand-900 dark:!bg-brand-700 dark:!text-brand-50' : '' }}">
                                            {{ $count }}
                                        </span>
                                    </div>
                                </div>

                                <!-- Active indicator - subtle blue dot -->
                                @if ($filterStorage == $storage)
                                    <div class="absolute top-0 right-0 transform translate-x-1/2 -translate-y-1/2">
                                        <div
                                            class="h-1.5 w-1.5 rounded-full bg-brand-500 dark:bg-brand-400 ring-1 ring-white dark:ring-brand-900">
                                        </div>
                                    </div>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
            <!-- Sold Out Products Section -->
            @if ($soldOutModels->count() > 0)
                <div>
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200 mb-4">Agotados</h2>
                    <div class="grid grid-cols-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-1.5">
                        @foreach ($soldOutModels as $soldOutModel)
                            <div
                                class="relative rounded-lg p-1 shadow-sm border 
                              bg-gray-50 dark:bg-gray-800/50
                              border-gray-200 dark:border-gray-700
                              opacity-80">
                                <div class="text-center">
                                    <span class="text-[0.65rem] font-medium text-gray-500 dark:text-gray-400">
                                        {{ $soldOutModel->nombre }}
                                    </span>


                                </div>

                                <div
                                    class="absolute bottom-0 left-0 right-0 h-0.5 mx-auto w-8 bg-gray-100 dark:bg-gray-700 rounded-b-lg">
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div class="flex gap-2 mb-4 items-center">
            <div class="flex-1 relative">
                <div class="absolute inset-y-0 left-2 flex items-center pointer-events-none">
                    <svg class="h-3.5 w-3.5 text-gray-400 dark:text-gray-500" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                            clip-rule="evenodd" />
                    </svg>
                </div>
                <input wire:model.live="search" type="text"
                    class="w-full pl-8 pr-2 py-1.5 text-xs border border-gray-300 dark:border-gray-600 rounded focus:ring-1 focus:ring-brand-500 dark:focus:ring-brand-400 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 placeholder-gray-400 dark:placeholder-gray-500"
                    placeholder="Buscar...">
            </div>

            <button wire:click="resetFilters"
                class="shrink-0 p-1.5 text-xs text-brand-600 dark:text-brand-400 flex items-center justify-center rounded hover:bg-brand-50 dark:hover:bg-gray-700"
                x-data="{ expanded: window.innerWidth > 360 }" x-init="() => {
                    const update = () => expanded = window.innerWidth > 360;
                    window.addEventListener('resize', update);
                }">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span class="ml-1 underline underline-offset-2" x-show="expanded">Recomendados</span>
            </button>
        </div>



        @if (!$filtersActive && $products->count() > 0)
            <div class="mb-8 text-center animate-fade-in">
                <div class="inline-block relative">
                    <h2 class="text-3xl font-bold text-brand-700 mb-2 relative z-10">
                        ¡Productos Destacados!
                    </h2>
                    <div class="absolute -bottom-1 left-0 right-0 h-2 bg-brand-100 rounded-full z-0"></div>
                </div>
                <p class="text-lg text-gray-600 mt-2">
                    Los productos más vendidos y recomendados para ti
                </p>
            </div>
        @endif

        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 p-2">
            @forelse ($products as $index => $product)
                <div wire:click="selectProduct({{ $product->id }})"
                    class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition-all duration-200 cursor-pointer group animate-fade-in-up relative overflow-hidden border border-gray-200 dark:border-gray-700"
                    style="animation-delay: {{ $index * 50 }}ms">


                    @if ($product->estado === App\Enums\ProductoEstado::Reparacion->value)
                        <div class="absolute top-2 left-2 z-10">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gradient-to-r from-yellow-400 to-yellow-500 text-gray-900 shadow-md animate-pulse">
                                ¡EN TRÁNSITO!
                            </span>
                        </div>
                    @elseif($product->created_at->diffInDays(now()) <= 3)
                        <div class="absolute top-1 left-1 z-10 w-24">
                            <div
                                class="absolute transform -translate-x-6 translate-y-2 -rotate-45 bg-brand-500 text-white text-xs font-bold px-8 py-0.5 text-center whitespace-nowrap">
                                NUEVO
                            </div>
                        </div>
                    @endif



                    @if ($product->venta_rapida && !$filtersActive)
                        <div class="absolute top-2 right-2 z-10">
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold bg-gradient-to-r from-yellow-400 to-yellow-500 text-gray-900 shadow-md animate-pulse">
                                ¡OFERTA!
                            </span>
                        </div>
                    @endif

                    <div class="relative h-40 bg-gray-100 dark:bg-gray-700 overflow-hidden rounded-t-lg">
                        @if ($product->imagenes->count() > 0)
                            <div class="relative h-full w-full" x-data="{ currentIndex: 0 }">
                                <div class="absolute inset-0 flex transition-transform duration-300 ease-in-out"
                                    :style="`transform: translateX(-${currentIndex * 100}%)`">
                                    @foreach ($product->imagenes as $image)
                                        <div class="w-full h-full flex-shrink-0">
                                            {{-- Archivo del disco public, no base64: el navegador lo cachea y el HTML no carga las fotos. --}}
                                            <img src="{{ $image->url() }}" alt="{{ $product->modelo?->nombre }}" loading="lazy"
                                                class="w-full h-full object-contain transition-transform duration-300 group-hover:scale-105">
                                        </div>
                                    @endforeach
                                </div>

                                @if ($product->imagenes->count() > 1)
                                    <div class="absolute bottom-2 left-0 right-0 flex justify-center space-x-1">
                                        @foreach ($product->imagenes as $key => $image)
                                            <button @click.stop="currentIndex = {{ $key }}"
                                                class="w-1.5 h-1.5 rounded-full transition-all duration-200"
                                                :class="{
                                                    'bg-brand-600 dark:bg-brand-400 w-3': currentIndex ===
                                                        {{ $key }},
                                                    'bg-gray-300 dark:bg-gray-500': currentIndex !==
                                                        {{ $key }}
                                                }">
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <svg class="h-10 w-10 text-gray-300 dark:text-gray-500" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                        @endif
                    </div>

                    <div class="p-3">
                        <h3 class="text-sm font-bold text-gray-900 dark:text-white truncate">
                            {{ $product->modelo->nombre }}
                        </h3>

                        <div class="flex flex-wrap items-center gap-x-1 text-xs text-gray-600 dark:text-gray-300 my-1">
                            <span class="truncate">{{ $product->almacenamiento }}</span>
                            <span class="text-gray-400 dark:text-gray-500">•</span>
                            <span class="truncate">{{ $product->color }}</span>
                            <span class="text-gray-400 dark:text-gray-500">•</span>
                            <span>{{ $product->bateria_porcentaje }}%</span>
                        </div>

                        @if ($product->detalles)
                            @php
                                $details = explode(',', $product->detalles);
                                $displayDetails = array_slice($details, 0, 2);
                            @endphp

                            <div class="mb-2">
                                <ul class="space-y-0.5 text-xs text-gray-600 dark:text-gray-300">
                                    @foreach ($displayDetails as $detail)
                                        <li class="flex items-start">
                                            <svg class="w-3 h-3 mt-0.5 mr-1 text-brand-500 dark:text-brand-400 flex-shrink-0"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M5 13l4 4L19 7" />
                                            </svg>
                                            <span class="truncate">{{ trim($detail) }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <div class="flex items-end justify-between mt-2">
                            @role('Vendedor')
                                <div>
                                    <span class="text-sm font-bold text-brand-600 dark:text-brand-400">
                                        Bs {{ number_format($product->precio_vendedor, 0) }}
                                    </span>
                                    @if ($product->precio_vendedor_ant > $product->precio_vendedor)
                                        <span class="block text-xs line-through text-gray-400 dark:text-gray-500">
                                            Bs {{ number_format($product->precio_vendedor_ant, 0) }}
                                        </span>
                                    @endif
                                </div>
                            @else
                                <div>
                                    <span class="text-sm font-bold text-brand-600 dark:text-brand-400">
                                        Bs {{ number_format($product->precio_cliente, 0) }}
                                    </span>
                                    @if ($product->precio_cliente_ant > $product->precio_cliente)
                                        <span class="block text-xs line-through text-gray-400 dark:text-gray-500">
                                            Bs {{ number_format($product->precio_cliente_ant, 0) }}
                                        </span>
                                    @endif
                                </div>
                            @endrole

                            @if ($product->tipo_venta === App\Enums\ProductoTipoVenta::Oferta->value)
                                <span
                                    class="text-[10px] px-1.5 py-0.5 bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-200 rounded-full border border-purple-200 dark:border-purple-700 font-semibold">
                                    Oferta
                                </span>
                            @endif
                        </div>

                        <div
                            class="flex items-center justify-between mt-2 pt-2 border-t border-gray-200 dark:border-gray-700">

                            @if (
                                (($product->venta_rapida && !$filtersActive) ||
                                    in_array($product->estado, App\Enums\ProductoEstado::disponibles(), true) ||
                                    $product->disponible_catalogo) &&
                                    $product->estado !== App\Enums\ProductoEstado::Reparacion->value)
                                <span
                                    class="text-[10px] px-2 py-0.5 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded-full font-medium flex items-center">
                                    <span
                                        class="w-1.5 h-1.5 bg-green-500 dark:bg-green-400 rounded-full mr-1 animate-pulse"></span>
                                    Disponible
                                </span>
                            @endif


                            <button
                                class="text-xs font-medium text-brand-600 dark:text-brand-400 hover:text-brand-800 dark:hover:text-brand-300 transition-colors flex items-center">
                                Ver
                                <svg class="w-3 h-3 ml-0.5" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full py-8 text-center animate-fade-in" x-data>
                    <div
                        class="inline-flex items-center px-3 py-2 bg-brand-50 dark:bg-gray-700 border border-brand-100 dark:border-gray-600 rounded-lg shadow-sm animate-pulse">
                        <svg class="w-5 h-5 mr-2 text-brand-500 dark:text-brand-400 animate-bounce" fill="none"
                            stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-brand-800 dark:text-gray-300 text-sm font-medium">No hay productos con estos
                            filtros</span>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($products->count() > 0)
            <div class="mt-8 animate-fade-in">
                {{ $products->links() }}
            </div>
        @endif
    </main>

    @if ($selectedProduct)
        <div class="fixed inset-0 z-50 overflow-y-auto bg-black/50 dark:bg-black/70 backdrop-blur-sm flex items-center justify-center p-4"
            wire:click="closeProductDetail" x-data="{ show: true }" x-show="show"
            @keydown.escape.window="show = false; $wire.closeProductDetail()"
            x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100" x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl w-full max-w-5xl mx-4 max-h-[90vh] overflow-hidden transform transition-all duration-300 relative"
                x-on:click.stop x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95" style="min-width: 80vw; max-width: 90vw; width: auto;">

                <!-- Close Button -->
                <button
                    class="absolute top-4 right-4 z-20 bg-white dark:bg-gray-700 rounded-full p-2 shadow-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition-all hover:scale-110"
                    wire:click="closeProductDetail">
                    <svg class="h-6 w-6 text-gray-500 dark:text-gray-300" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>

                <!-- Image Gallery -->
                <div class="relative h-[400px] bg-gray-100 dark:bg-gray-700 overflow-hidden">
                    @if ($selectedProduct->imagenes->count() > 0)
                        <div class="relative h-full w-full" x-data="{ currentIndex: 0 }">
                            <div class="absolute inset-0 flex transition-transform duration-300 ease-in-out"
                                :style="`transform: translateX(-${currentIndex * 100}%)`">
                                @foreach ($selectedProduct->imagenes as $image)
                                    <div class="w-full h-full flex-shrink-0">
                                        <img src="{{ $image->url() }}" alt="{{ $selectedProduct->modelo?->nombre }}"
                                            class="w-full h-full object-contain">
                                    </div>
                                @endforeach
                            </div>

                            @if ($selectedProduct->imagenes->count() > 1)
                                <!-- Image Dots -->
                                <div class="absolute bottom-4 left-0 right-0 flex justify-center space-x-2">
                                    @foreach ($selectedProduct->imagenes as $key => $image)
                                        <button @click.stop="currentIndex = {{ $key }}"
                                            class="w-2 h-2 rounded-full transition-all duration-300"
                                            :class="{
                                                'bg-brand-600 dark:bg-brand-400 w-4': currentIndex ===
                                                    {{ $key }},
                                                'bg-gray-300 dark:bg-gray-500': currentIndex !== {{ $key }}
                                            }">
                                        </button>
                                    @endforeach
                                </div>

                                <!-- Navigation Arrows -->
                                <div
                                    class="absolute inset-0 flex items-center justify-between opacity-0 hover:opacity-100 transition-opacity duration-300 px-4">
                                    <button
                                        @click.stop="currentIndex = (currentIndex - 1 + {{ $selectedProduct->imagenes->count() }}) % {{ $selectedProduct->imagenes->count() }}"
                                        class="bg-white/80 dark:bg-gray-600/80 hover:bg-white dark:hover:bg-gray-600 p-2 rounded-full shadow-md transition-all duration-300">
                                        <svg class="w-5 h-5 text-gray-700 dark:text-gray-200" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M15 19l-7-7 7-7" />
                                        </svg>
                                    </button>
                                    <button
                                        @click.stop="currentIndex = (currentIndex + 1) % {{ $selectedProduct->imagenes->count() }}"
                                        class="bg-white/80 dark:bg-gray-600/80 hover:bg-white dark:hover:bg-gray-600 p-2 rounded-full shadow-md transition-all duration-300">
                                        <svg class="w-5 h-5 text-gray-700 dark:text-gray-200" fill="none"
                                            stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M9 5l7 7-7 7" />
                                        </svg>
                                    </button>
                                </div>
                            @endif
                        </div>
                    @else
                        <!-- Placeholder when no images -->
                        <div class="w-full h-full flex items-center justify-center">
                            <svg class="h-32 w-32 text-gray-300 dark:text-gray-500" fill="none"
                                viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                    @endif
                </div>

                <!-- Product Details -->
                <div class="p-8 overflow-y-auto" style="max-height: calc(90vh - 400px)">
                    <div class="flex justify-between items-start mb-6">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 dark:text-white">
                                {{ $selectedProduct->modelo->nombre }}</h2>
                            <p class="text-gray-500 dark:text-gray-400 text-lg">{{ $selectedProduct->almacenamiento }}
                                •
                                {{ $selectedProduct->color }}</p>
                        </div>

                        @role('Vendedor')
                            <div class="text-right">
                                <span class="text-2xl font-bold text-brand-600 dark:text-brand-400 whitespace-nowrap">
                                    Bs {{ number_format($selectedProduct->precio_vendedor, 2) }}
                                </span>
                                @if ($selectedProduct->precio_vendedor_ant > $selectedProduct->precio_vendedor)
                                    <span
                                        class="block text-sm line-through text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                        Bs {{ number_format($selectedProduct->precio_vendedor_ant, 2) }}
                                    </span>
                                @endif
                            </div>
                        @else
                            <div class="text-right">
                                <span class="text-2xl font-bold text-brand-600 dark:text-brand-400 whitespace-nowrap">
                                    Bs {{ number_format($selectedProduct->precio_cliente, 2) }}
                                </span>
                                @if ($selectedProduct->precio_cliente_ant > $selectedProduct->precio_cliente)
                                    <span
                                        class="block text-sm line-through text-gray-400 dark:text-gray-500 whitespace-nowrap">
                                        Bs {{ number_format($selectedProduct->precio_cliente_ant, 2) }}
                                    </span>
                                @endif
                            </div>
                        @endrole
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <!-- Specifications -->
                        <div>
                            <h3 class="text-xl font-medium text-gray-900 dark:text-white mb-4">Especificaciones</h3>
                            <div class="flex justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Almacenamiento</span>
                                <span
                                    class="font-medium dark:text-gray-200">{{ $selectedProduct->almacenamiento }}</span>
                            </div>
                            <div class="flex justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Color</span>
                                <span class="font-medium dark:text-gray-200">{{ $selectedProduct->color }}</span>
                            </div>
                            <div class="flex justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">Batería</span>
                                <span
                                    class="font-medium dark:text-gray-200">{{ $selectedProduct->bateria_porcentaje }}%</span>
                            </div>
                            <div class="flex justify-between py-3 border-b border-gray-100 dark:border-gray-700">
                                <span class="text-gray-500 dark:text-gray-400">IMEI</span>
                                <span class="font-medium dark:text-gray-200">{{ $selectedProduct->imei }}</span>
                            </div>
                        </div>

                        <!-- Product Details -->
                        <div>
                            @if ($selectedProduct->detalles)
                                <h3 class="text-xl font-medium text-gray-900 dark:text-white mb-4">Detalles del
                                    Producto</h3>
                                <ul class="space-y-3">
                                    @foreach (explode(',', $selectedProduct->detalles) as $detail)
                                        @if (trim($detail))
                                            <li class="flex items-start">
                                                <svg class="w-5 h-5 text-green-500 dark:text-green-400 mt-0.5 mr-3 flex-shrink-0"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M5 13l4 4L19 7" />
                                                </svg>
                                                <span
                                                    class="text-gray-600 dark:text-gray-300">{{ trim($detail) }}</span>
                                            </li>
                                        @endif
                                    @endforeach
                                </ul>
                            @endif

                            <!-- WhatsApp Button -->
                            <div class="mt-8 flex justify-center">
                                <a href="https://wa.me/59177357375?text={{ urlencode('Hola, estoy interesado en el : ' . $selectedProduct->modelo->nombre . ' - ' . $selectedProduct->descripcion) }}"
                                    target="_blank"
                                    class="bg-green-500 hover:bg-green-600 text-white font-bold py-3 px-8 rounded-full flex items-center gap-3 transition-all duration-300 text-lg">
                                    <i class="fa-brands fa-whatsapp text-xl"></i>
                                    Contáctanos
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
    <div class="fab-container">
        <div class="fab-wrapper">
            <a href="https://wa.me/59177357375" style="display: flex; align-items: center;" class="fab"
                id="fab1">
                <i class="fa-brands fa-whatsapp"></i>
            </a>
            <span class="tooltip">Contactar</span>
        </div>
    </div>

    @push('js')
        <script>
            document.addEventListener('livewire:load', function() {
                Livewire.on('product-selected', () => {
                    const modal = document.querySelector('[x-show="show"]');
                    modal.scrollIntoView({
                        behavior: 'smooth'
                    });

                    setTimeout(() => {
                        modal.classList.add('animate-pulse');
                        setTimeout(() => modal.classList.remove('animate-pulse'), 1000);
                    }, 300);
                });

                document.addEventListener('keydown', function(e) {
                    if (@this.selectedProduct) {
                        if (e.key === 'ArrowRight') {
                            @this.nextImage();
                            const modal = document.querySelector('[x-show="show"]');
                            modal.classList.add('animate-pulse');
                            setTimeout(() => modal.classList.remove('animate-pulse'), 300);
                        } else if (e.key === 'ArrowLeft') {
                            @this.prevImage();
                            const modal = document.querySelector('[x-show="show"]');
                            modal.classList.add('animate-pulse');
                            setTimeout(() => modal.classList.remove('animate-pulse'), 300);
                        }
                    }
                });

                document.querySelectorAll('[x-data*="currentIndex"]').forEach(carousel => {
                    const imagesCount = carousel.querySelectorAll('img').length;
                    if (imagesCount > 1) {
                        let interval = setInterval(() => {
                            const currentIndex = parseInt(carousel._x_dataStack[0].currentIndex);
                            carousel._x_dataStack[0].currentIndex = (currentIndex + 1) % imagesCount;
                        }, 5000);

                        carousel.addEventListener('mouseenter', () => clearInterval(interval));
                        carousel.addEventListener('mouseleave', () => {
                            interval = setInterval(() => {
                                const currentIndex = parseInt(carousel._x_dataStack[0]
                                    .currentIndex);
                                carousel._x_dataStack[0].currentIndex = (currentIndex + 1) %
                                    imagesCount;
                            }, 5000);
                        });
                    }
                });
            });
        </script>
        <script>
            // Handle scroll events to show/hide the FAB
            document.addEventListener('DOMContentLoaded', () => {
                let lastScrollTop = 0;
                let isScrolling;
                const fabContainer = document.querySelector('.fab-container');

                if (!fabContainer) {
                    console.error('Element with class fab-container not found');
                    return;
                }

                window.addEventListener('scroll', () => {
                    let scrollTop = window.pageYOffset || document.documentElement.scrollTop;

                    // Clear any existing timeout
                    clearTimeout(isScrolling);

                    // Check if the user is scrolling up or down
                    if (scrollTop > lastScrollTop) {
                        // Scrolling Down
                        fabContainer.style.transform = 'translateY(-50px)';
                    } else {
                        // Scrolling Up
                        fabContainer.style.transform = 'translateY(50px)';
                    }

                    // Update lastScrollTop to current scroll position
                    lastScrollTop = scrollTop;

                    // Set a timeout to reset the transform after scrolling stops
                    isScrolling = setTimeout(() => {
                        fabContainer.style.transform = 'translateY(0)';
                    }, 500); // Adjust the delay to your preference
                });
            });
        </script>
    @endpush
</div>
