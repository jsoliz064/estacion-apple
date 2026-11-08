{{-- El catalogo publico, pensado primero para el celular: cabecera corta,
     buscador y chips fijos arriba, grilla de 2 columnas, y el detalle como hoja
     inferior con el boton de WhatsApp siempre a mano. Lo que se ve lo decide
     Producto::scopeEnCatalogo(); nunca se muestra el IMEI. --}}
@php
    $bateriaIcono = fn($b) => match (true) {
        $b >= 85 => 'fa-battery-full',
        $b >= 60 => 'fa-battery-three-quarters',
        $b >= 40 => 'fa-battery-half',
        default => 'fa-battery-quarter',
    };
    $chip = 'shrink-0 snap-start rounded-full border px-3.5 py-1.5 text-sm font-medium transition active:scale-95';
    $chipOn = 'border-gray-900 bg-gray-900 text-white dark:border-white dark:bg-white dark:text-gray-900';
    $chipOff = 'border-gray-300 bg-white text-gray-700 hover:border-gray-400 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200';
@endphp
<div class="pb-24">
    <style>
        .sin-barra { scrollbar-width: none; }
        .sin-barra::-webkit-scrollbar { display: none; }
        @keyframes catalogo-subir { from { transform: translateY(100%); } to { transform: translateY(0); } }
        @keyframes catalogo-aparecer { from { opacity: 0; } to { opacity: 1; } }
        .catalogo-hoja { animation: catalogo-subir .28s cubic-bezier(.2, .8, .2, 1); }
        .catalogo-fondo { animation: catalogo-aparecer .2s ease-out; }
        @media (min-width: 640px) { .catalogo-hoja { animation: catalogo-aparecer .2s ease-out; } }
    </style>

    {{-- Portada corta: en el celular lo primero que se ve tienen que ser los equipos. --}}
    <section class="relative h-36 overflow-hidden sm:h-44">
        <img src="{{ asset('imgs/portada.webp') }}" alt="" class="absolute inset-0 h-full w-full object-cover">
        <div class="absolute inset-0 bg-gradient-to-t from-black/90 via-black/65 to-black/40"></div>
        <div class="relative mx-auto flex h-full max-w-7xl items-end justify-between gap-3 px-4 pb-4">
            <div class="text-white">
                <h1 class="text-2xl font-bold leading-tight sm:text-3xl">Equipos disponibles</h1>
                <p class="mt-0.5 text-sm text-white/80">
                    {{ $publicados }} {{ $publicados === 1 ? 'equipo listo' : 'equipos listos' }} para entregar
                </p>
            </div>
            <a href="{{ $this->whatsapp() }}" target="_blank" rel="noopener"
                class="flex shrink-0 items-center gap-2 rounded-full bg-green-500 px-4 py-2 text-sm font-semibold text-white shadow-lg hover:bg-green-600">
                <i class="fa-brands fa-whatsapp text-lg"></i><span class="hidden sm:inline">Escríbenos</span>
            </a>
        </div>
    </section>

    {{-- Buscador y filtros, fijos bajo la barra del layout (h-14). --}}
    <div class="sticky top-14 z-20 border-b border-gray-200 bg-gray-50/95 backdrop-blur dark:border-gray-800 dark:bg-gray-900/95">
        <div class="mx-auto max-w-7xl space-y-2 px-4 py-3">
            <div class="flex gap-2">
                <label class="relative flex-1">
                    <span class="sr-only">Buscar</span>
                    <i class="fa-solid fa-magnifying-glass pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    {{-- text-base: con menos de 16px el iPhone agranda la pagina al tocarlo. --}}
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Buscar equipo"
                        enterkeyhint="search" autocomplete="off"
                        class="h-11 w-full rounded-full border-gray-300 bg-white pl-10 pr-4 text-base focus:border-gray-900 focus:ring-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:focus:border-white dark:focus:ring-white">
                </label>
                <label class="relative shrink-0">
                    <span class="sr-only">Ordenar</span>
                    <select wire:model.live="orden"
                        class="h-11 rounded-full border-gray-300 bg-white pl-4 pr-9 text-sm focus:border-gray-900 focus:ring-gray-900 dark:border-gray-700 dark:bg-gray-800 dark:focus:border-white dark:focus:ring-white">
                        @foreach (\App\Livewire\Catalogo\CatalogoIndex::ORDENES as $valor => $texto)
                            <option value="{{ $valor }}">{{ $texto }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($modelos->isNotEmpty())
                <div class="sin-barra -mx-4 flex snap-x gap-2 overflow-x-auto px-4">
                    @foreach ($modelos as $m)
                        <button type="button" wire:key="chip-modelo-{{ $m->id }}" wire:click="elegirModelo({{ $m->id }})"
                            class="{{ $chip }} {{ $modeloId == $m->id ? $chipOn : $chipOff }}">
                            {{ $m->nombre }}
                            <span class="ml-1 text-xs opacity-60">{{ $m->cantidad }}</span>
                        </button>
                    @endforeach
                </div>
            @endif

            @if (count($almacenamientos) > 0 || count($grados) > 1 || $grado !== '')
                <div class="sin-barra -mx-4 flex snap-x items-center gap-2 overflow-x-auto px-4">
                    @foreach ($almacenamientos as $valor => $cantidad)
                        <button type="button" wire:key="chip-gb-{{ $valor }}" wire:click="elegirAlmacenamiento('{{ $valor }}')"
                            class="{{ $chip }} {{ $almacenamiento === $valor ? $chipOn : $chipOff }} !py-1 !text-xs">
                            {{ $valor }} <span class="ml-0.5 opacity-60">{{ $cantidad }}</span>
                        </button>
                    @endforeach
                    @if (count($almacenamientos) > 0 && (count($grados) > 1 || $grado !== ''))
                        <span class="h-5 w-px shrink-0 bg-gray-300 dark:bg-gray-700"></span>
                    @endif
                    @if (count($grados) > 1 || $grado !== '')
                        @foreach ($grados as $valor => $cantidad)
                            <button type="button" wire:key="chip-grado-{{ $valor }}" wire:click="elegirGrado('{{ $valor }}')"
                                class="{{ $chip }} {{ $grado === $valor ? $chipOn : $chipOff }} !py-1 !text-xs">
                                {{ $valor }} <span class="ml-0.5 opacity-60">{{ $cantidad }}</span>
                            </button>
                        @endforeach
                    @endif
                </div>
            @endif
        </div>
    </div>

    <div class="mx-auto max-w-7xl px-4">
        <div class="flex h-11 items-center justify-between text-sm text-gray-500 dark:text-gray-400">
            <span>{{ $total }} {{ $total === 1 ? 'equipo' : 'equipos' }}</span>
            @if ($filtrosActivos)
                <button type="button" wire:click="limpiar" class="font-medium text-gray-900 underline underline-offset-2 dark:text-white">
                    Limpiar filtros
                </button>
            @endif
        </div>

        @if ($equipos->isEmpty())
            <div class="flex flex-col items-center rounded-2xl bg-white px-6 py-12 text-center ring-1 ring-gray-200 dark:bg-gray-800 dark:ring-gray-700">
                <i class="fa-solid fa-mobile-screen-button text-4xl text-gray-300 dark:text-gray-600"></i>
                <p class="mt-4 font-semibold">
                    {{ $filtrosActivos ? 'No encontramos equipos con esos filtros' : 'Por ahora no hay equipos publicados' }}
                </p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">¿Buscas un modelo en particular? Escríbenos y te avisamos.</p>
                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    @if ($filtrosActivos)
                        <button type="button" wire:click="limpiar"
                            class="h-11 rounded-full border border-gray-300 px-5 text-sm font-medium dark:border-gray-600">Limpiar filtros</button>
                    @endif
                    <a href="{{ $this->whatsapp() }}" target="_blank" rel="noopener"
                        class="flex h-11 items-center gap-2 rounded-full bg-green-500 px-5 text-sm font-semibold text-white hover:bg-green-600">
                        <i class="fa-brands fa-whatsapp text-lg"></i> Escríbenos
                    </a>
                </div>
            </div>
        @else
            <div class="grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5"
                wire:loading.class.delay="opacity-50" wire:target="search,orden,elegirModelo,elegirAlmacenamiento,elegirGrado,limpiar">
                @foreach ($equipos as $e)
                    @php($mini = \App\Livewire\Catalogo\CatalogoIndex::miniatura($e))
                    <button type="button" wire:key="equipo-{{ $e->id }}" wire:click="abrir({{ $e->id }})"
                        class="group flex flex-col overflow-hidden rounded-2xl bg-white text-left shadow-sm ring-1 ring-gray-200 transition hover:shadow-md active:scale-[0.98] dark:bg-gray-800 dark:ring-gray-700">
                        <div class="relative aspect-square w-full bg-gray-100 dark:bg-gray-700">
                            @if ($mini)
                                <img src="{{ $mini }}" alt="{{ $e->modelo?->nombre }}" loading="lazy" decoding="async"
                                    class="h-full w-full object-cover transition duration-300 group-hover:scale-105">
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <i class="fa-solid fa-mobile-screen-button text-4xl text-gray-300 dark:text-gray-500"></i>
                                </div>
                            @endif
                            <div class="absolute left-2 top-2 flex flex-col items-start gap-1">
                                @if (\App\Livewire\Catalogo\CatalogoIndex::esOferta($e))
                                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-bold text-white shadow">Oferta</span>
                                @endif
                                @if ($e->venta_rapida)
                                    <span class="rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-bold text-gray-900 shadow">Destacado</span>
                                @endif
                                @if (\App\Livewire\Catalogo\CatalogoIndex::recienLlegado($e))
                                    <span class="rounded-full bg-white/90 px-2 py-0.5 text-[11px] font-semibold text-gray-900 shadow">Recién llegado</span>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col p-3">
                            <h3 class="line-clamp-2 text-sm font-semibold leading-snug">{{ $e->modelo?->nombre ?? 'Equipo' }}</h3>
                            <p class="mt-1 flex min-w-0 items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <span class="shrink-0">{{ $e->almacenamiento }}</span>
                                <span aria-hidden="true">·</span>
                                <span class="h-2.5 w-2.5 shrink-0 rounded-full ring-1 ring-black/10 dark:ring-white/20"
                                    style="background-color: {{ $hexColores[$e->color] ?? '#9CA3AF' }}"></span>
                                <span class="truncate">{{ $e->color }}</span>
                            </p>
                            <p class="mt-1 flex items-center gap-1.5 text-xs text-gray-500 dark:text-gray-400">
                                <i class="fa-solid {{ $bateriaIcono($e->bateria_porcentaje) }}"></i>
                                <span>{{ $e->bateria_porcentaje }}%</span>
                                @if ($e->estado_grado)
                                    <span aria-hidden="true">·</span><span class="truncate">{{ $e->estado_grado }}</span>
                                @endif
                            </p>
                            <div class="mt-auto pt-2">
                                <x-monto :valor="$e->precio" class="text-base font-bold text-gray-900 dark:text-white" />
                            </div>
                        </div>
                    </button>
                @endforeach
            </div>

            @if ($equipos->count() < $total)
                <div class="flex flex-col items-center gap-2 py-8">
                    <p class="text-xs text-gray-500 dark:text-gray-400">Mostrando {{ $equipos->count() }} de {{ $total }}</p>
                    <button type="button" wire:click="verMas" wire:loading.attr="disabled" wire:target="verMas"
                        class="h-11 rounded-full bg-gray-900 px-8 text-sm font-semibold text-white hover:bg-gray-800 disabled:opacity-60 dark:bg-white dark:text-gray-900">
                        <span wire:loading.remove wire:target="verMas">Ver más equipos</span>
                        <span wire:loading wire:target="verMas">Cargando…</span>
                    </button>
                </div>
            @endif
        @endif

        <footer class="mt-10 border-t border-gray-200 pt-6 text-center text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
            Estación Apple · <a href="{{ route('garantia') }}" class="underline underline-offset-2">Términos de garantía</a>
        </footer>
    </div>

    {{-- WhatsApp flotante; con el detalle abierto se oculta: el detalle tiene el suyo. --}}
    @unless ($equipo)
        <a href="{{ $this->whatsapp() }}" target="_blank" rel="noopener" aria-label="Escríbenos por WhatsApp"
            class="fixed bottom-5 right-5 z-30 flex h-14 w-14 items-center justify-center rounded-full bg-green-500 text-3xl text-white shadow-xl hover:bg-green-600"
            style="margin-bottom: env(safe-area-inset-bottom)">
            <i class="fa-brands fa-whatsapp"></i>
        </a>
    @endunless

    {{-- Detalle: hoja inferior en el celular, modal en pantallas grandes. --}}
    @if ($equipo)
        @php($fotos = $equipo->imagenes->sortBy('id')->values())
        <div wire:key="detalle-{{ $equipo->id }}" class="fixed inset-0 z-40 flex items-end justify-center sm:items-center sm:p-6"
            role="dialog" aria-modal="true" aria-label="{{ $equipo->modelo?->nombre }}"
            x-data="{
                init() { document.body.style.overflow = 'hidden' },
                destroy() { document.body.style.overflow = '' },
            }"
            x-on:keydown.escape.window="$wire.cerrar()">
            <div class="catalogo-fondo absolute inset-0 bg-black/60" wire:click="cerrar"></div>

            <div class="catalogo-hoja relative flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-t-3xl bg-white shadow-2xl dark:bg-gray-900 sm:max-h-[90vh] sm:max-w-4xl sm:rounded-3xl">
                <div class="flex items-center gap-3 border-b border-gray-100 px-4 py-3 dark:border-gray-800">
                    <span class="absolute left-1/2 top-1.5 h-1 w-10 -translate-x-1/2 rounded-full bg-gray-300 dark:bg-gray-700 sm:hidden"></span>
                    <h2 class="min-w-0 flex-1 truncate font-semibold">{{ $equipo->modelo?->nombre ?? 'Equipo' }}</h2>
                    <button type="button" wire:click="cerrar" aria-label="Cerrar"
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto overscroll-contain sm:grid sm:grid-cols-2">
                    {{-- Galeria: se desliza con el dedo (scroll nativo con snap), sin JS de carrusel. --}}
                    <div class="relative bg-gray-100 dark:bg-gray-800" x-data="{ i: 0, n: {{ max(1, $fotos->count()) }} }">
                        @if ($fotos->isNotEmpty())
                            <div x-ref="tira" class="sin-barra flex snap-x snap-mandatory overflow-x-auto"
                                x-on:scroll.debounce.60ms="i = Math.round($refs.tira.scrollLeft / $refs.tira.clientWidth)">
                                @foreach ($fotos as $foto)
                                    <img src="{{ $foto->url() }}" alt="{{ $equipo->modelo?->nombre }} — foto {{ $loop->iteration }}"
                                        @if (!$loop->first) loading="lazy" @endif
                                        class="aspect-square w-full shrink-0 snap-center object-contain">
                                @endforeach
                            </div>
                            @if ($fotos->count() > 1)
                                <div class="absolute bottom-3 left-0 right-0 flex justify-center gap-1.5">
                                    @foreach ($fotos as $foto)
                                        <button type="button" aria-label="Foto {{ $loop->iteration }}"
                                            x-on:click="$refs.tira.scrollTo({ left: {{ $loop->index }} * $refs.tira.clientWidth, behavior: 'smooth' })"
                                            class="h-1.5 rounded-full transition-all"
                                            :class="i === {{ $loop->index }} ? 'w-5 bg-gray-900 dark:bg-white' : 'w-1.5 bg-gray-400/70'"></button>
                                    @endforeach
                                </div>
                                <button type="button" aria-label="Anterior" x-show="i > 0"
                                    x-on:click="$refs.tira.scrollBy({ left: -$refs.tira.clientWidth, behavior: 'smooth' })"
                                    class="absolute left-2 top-1/2 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow sm:flex dark:bg-gray-700/90">
                                    <i class="fa-solid fa-chevron-left text-sm"></i>
                                </button>
                                <button type="button" aria-label="Siguiente" x-show="i < n - 1"
                                    x-on:click="$refs.tira.scrollBy({ left: $refs.tira.clientWidth, behavior: 'smooth' })"
                                    class="absolute right-2 top-1/2 hidden h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 shadow sm:flex dark:bg-gray-700/90">
                                    <i class="fa-solid fa-chevron-right text-sm"></i>
                                </button>
                            @endif
                        @else
                            <div class="flex aspect-square w-full items-center justify-center">
                                <i class="fa-solid fa-mobile-screen-button text-6xl text-gray-300 dark:text-gray-600"></i>
                            </div>
                        @endif
                    </div>

                    <div class="space-y-5 p-5">
                        <div>
                            <div class="flex flex-wrap gap-1.5">
                                @if (\App\Livewire\Catalogo\CatalogoIndex::esOferta($equipo))
                                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">Oferta</span>
                                @endif
                                @if ($equipo->estado_grado)
                                    <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold dark:bg-gray-800">{{ $equipo->estado_grado }}</span>
                                @endif
                            </div>
                            <h3 class="mt-2 text-xl font-bold leading-tight">{{ $equipo->modelo?->nombre ?? 'Equipo' }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $equipo->almacenamiento }} · {{ $equipo->color }}</p>
                            <x-monto :valor="$equipo->precio" class="mt-3 text-3xl font-bold" />
                        </div>

                        <dl class="grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Almacenamiento</dt>
                                <dd class="mt-0.5 font-semibold">{{ $equipo->almacenamiento }}</dd>
                            </div>
                            <div class="rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Color</dt>
                                <dd class="mt-0.5 flex items-center gap-1.5 font-semibold">
                                    <span class="h-3 w-3 shrink-0 rounded-full ring-1 ring-black/10 dark:ring-white/20"
                                        style="background-color: {{ $hexColores[$equipo->color] ?? '#9CA3AF' }}"></span>
                                    <span class="truncate">{{ $equipo->color }}</span>
                                </dd>
                            </div>
                            <div class="rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Batería</dt>
                                <dd class="mt-0.5 font-semibold">{{ $equipo->bateria_porcentaje }}%</dd>
                                <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                    <div class="h-full rounded-full {{ $equipo->bateria_porcentaje >= 80 ? 'bg-green-500' : ($equipo->bateria_porcentaje >= 60 ? 'bg-amber-400' : 'bg-red-500') }}"
                                        style="width: {{ max(0, min(100, (int) $equipo->bateria_porcentaje)) }}%"></div>
                                </div>
                            </div>
                            <div class="rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                                <dt class="text-xs text-gray-500 dark:text-gray-400">Condición</dt>
                                <dd class="mt-0.5 font-semibold">{{ $equipo->estado_grado ?: '—' }}</dd>
                            </div>
                            @if ($equipo->version)
                                <div class="col-span-2 rounded-2xl bg-gray-50 p-3 dark:bg-gray-800">
                                    <dt class="text-xs text-gray-500 dark:text-gray-400">Versión</dt>
                                    <dd class="mt-0.5 font-semibold">{{ $equipo->version }}</dd>
                                </div>
                            @endif
                        </dl>

                        @php($detalles = collect(explode(',', (string) $equipo->detalles))->map(fn($d) => trim($d))->filter())
                        @if ($detalles->isNotEmpty())
                            <div>
                                <h4 class="text-sm font-semibold">Detalles</h4>
                                <ul class="mt-2 space-y-1.5 text-sm text-gray-600 dark:text-gray-300">
                                    @foreach ($detalles as $detalle)
                                        <li class="flex gap-2"><i class="fa-solid fa-check mt-1 text-xs text-green-500"></i>{{ $detalle }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <a href="{{ route('garantia') }}" class="flex items-center gap-2 text-sm text-gray-600 underline-offset-2 hover:underline dark:text-gray-300">
                            <i class="fa-solid fa-shield-halved"></i> Ver los términos de garantía
                        </a>
                    </div>
                </div>

                {{-- Siempre a la vista, aunque se desplace el detalle. --}}
                <div class="flex gap-2 border-t border-gray-100 bg-white p-3 dark:border-gray-800 dark:bg-gray-900"
                    style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom))"
                    x-data="{
                        copiado: false,
                        async compartir() {
                            const url = @js(route('catalogo', ['equipo' => $equipo->id]));
                            if (navigator.share) {
                                try { await navigator.share({ title: @js($equipo->modelo?->nombre ?? 'Equipo'), url }); } catch (e) {}
                                return;
                            }
                            try { await navigator.clipboard.writeText(url); this.copiado = true; setTimeout(() => this.copiado = false, 2000); } catch (e) {}
                        },
                    }">
                    <a href="{{ $this->whatsapp($equipo) }}" target="_blank" rel="noopener"
                        class="flex h-12 flex-1 items-center justify-center gap-2 rounded-full bg-green-500 text-base font-semibold text-white hover:bg-green-600">
                        <i class="fa-brands fa-whatsapp text-xl"></i> Consultar por WhatsApp
                    </a>
                    <button type="button" x-on:click="compartir()" aria-label="Compartir"
                        class="relative flex h-12 w-12 shrink-0 items-center justify-center rounded-full border border-gray-300 text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-gray-800">
                        <i class="fa-solid fa-share-nodes" x-show="!copiado"></i>
                        <i class="fa-solid fa-check text-green-500" x-show="copiado" x-cloak></i>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
