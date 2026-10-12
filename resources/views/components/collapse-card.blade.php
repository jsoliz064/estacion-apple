{{--
    Props con default: sin ellos, omitir `title` u `open` lanza "Undefined
    variable" en tiempo de render.

    `openOnDesktop` arranca abierto en pantallas grandes y plegado en movil.
    Es opt-in: los usos que no lo pasan se comportan exactamente como antes.
--}}
@props(['title' => '', 'open' => false, 'openOnDesktop' => false])

<div x-data="{ open: {{ $openOnDesktop ? "window.matchMedia('(min-width: 768px)').matches" : ($open ? 'true' : 'false') }} }"
    class="bg-white dark:bg-gray-800 shadow-lg rounded-xl mb-6 border border-gray-200 dark:border-gray-700">
    <!-- Header -->
    <div @click="open = !open"
        class="flex justify-between items-center cursor-pointer p-4 border-b dark:border-gray-700">
        <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
            {{ $title }}
        </h2>

        <div class="flex items-center gap-2">
            {{-- Acciones opcionales en la cabecera. El @click.stop evita que
                 pulsar un boton de aqui pliegue la tarjeta. --}}
            @isset($actions)
                <div @click.stop>
                    {{ $actions }}
                </div>
            @endisset

            <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600 dark:text-gray-300 shrink-0"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m7-7H5" />
            </svg>

            <svg x-show="open" xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-600 dark:text-gray-300 shrink-0"
                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 12H5" />
            </svg>
        </div>
    </div>

    <!-- Contenido -->
    <div x-show="open" x-transition x-cloak class="p-2">
        {{ $slot }}
    </div>
</div>
