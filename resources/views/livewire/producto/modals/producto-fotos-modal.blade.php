<div>
    @if ($openModal && $producto)
        {{-- z-[60]: encima de los modales de Jetstream (z-50), debajo del lector y la camara. --}}
        <div wire:key="visor-fotos-{{ $producto->id }}" x-data="visorFotos(@js($fotos))"
            x-on:keydown.escape.window="$wire.closeModal()"
            x-on:keydown.arrow-right.window="siguiente()"
            x-on:keydown.arrow-left.window="anterior()"
            class="fixed inset-0 z-[60] flex flex-col bg-black/95 text-white" role="dialog" aria-modal="true">

            <div class="flex items-center gap-3 px-4 py-3">
                <div class="min-w-0 flex-1">
                    <p class="truncate font-semibold">
                        {{ $producto->modelo?->nombre }} {{ $producto->almacenamiento }}
                    </p>
                    <p class="truncate text-xs text-gray-400">IMEI {{ $producto->imei }}</p>
                </div>
                @if (count($fotos) > 0)
                    <span class="text-sm tabular-nums text-gray-300" x-text="`${actual + 1} / ${fotos.length}`"></span>
                @endif
                <button type="button" wire:click="closeModal" title="Cerrar"
                    class="flex h-10 w-10 items-center justify-center rounded-full bg-white/10 hover:bg-white/20">
                    <i class="fa-solid fa-xmark text-lg"></i>
                </button>
            </div>

            @if (count($fotos) === 0)
                <div wire:click="closeModal" class="flex flex-1 items-center justify-center text-gray-400">
                    Este equipo no tiene fotos.
                </div>
            @else
                {{-- Tocar el fondo (no la foto) cierra. --}}
                <div class="relative min-h-0 flex-1" x-on:touchstart.passive="tocar($event)" x-on:touchend="soltar($event)"
                    x-on:click.self="$wire.closeModal()">
                    <template x-for="(foto, i) in fotos" :key="i">
                        <div x-show="actual === i" x-on:click.self="$wire.closeModal()"
                            class="absolute inset-0 flex items-center justify-center p-2">
                            <img :src="foto" alt="" class="max-h-full max-w-full select-none object-contain" draggable="false">
                        </div>
                    </template>

                    @if (count($fotos) > 1)
                        <button type="button" x-on:click="anterior()" title="Anterior"
                            class="absolute left-2 top-1/2 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 sm:flex">
                            <i class="fa-solid fa-chevron-left"></i>
                        </button>
                        <button type="button" x-on:click="siguiente()" title="Siguiente"
                            class="absolute right-2 top-1/2 hidden h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-white/10 hover:bg-white/20 sm:flex">
                            <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    @endif
                </div>

                @if (count($fotos) > 1)
                    <div x-ref="tira" class="flex justify-start gap-2 overflow-x-auto px-4 py-3 sm:justify-center">
                        <template x-for="(foto, i) in fotos" :key="'mini-' + i">
                            <button type="button" x-on:click="ir(i)"
                                class="h-14 w-14 flex-shrink-0 overflow-hidden rounded-md border-2"
                                :class="actual === i ? 'border-white' : 'border-transparent opacity-60'">
                                <img :src="foto" alt="" loading="lazy" class="h-full w-full object-cover">
                            </button>
                        </template>
                    </div>
                @endif
            @endif
        </div>
    @endif
</div>
