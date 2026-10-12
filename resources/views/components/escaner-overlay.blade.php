{{-- El lector de codigos por camara: uno solo, en el layout (resources/js/app.js,
     Alpine "escaner"). Lo abre cualquier <x-boton-escaner>.
     z-[70]: por encima de los modales (z-50). El x-trap.inert de un modal solo
     pone aria-hidden fuera de el, asi que los toques llegan igual. --}}
<div x-data="escaner" x-on:abrir-escaner.window="abrir($event.detail)" x-show="abierto" x-cloak
    class="fixed inset-0 z-[70] flex flex-col bg-black" style="display: none;" role="dialog" aria-modal="true"
    aria-label="Escanear código de barras">

    <div class="relative flex-1 overflow-hidden">
        <video x-ref="video" class="absolute inset-0 h-full w-full object-cover" playsinline muted autoplay></video>

        {{-- Recuadro guia: horizontal, como un codigo de barras. --}}
        <div x-show="!error" class="pointer-events-none absolute inset-0 flex items-center justify-center">
            <div class="h-40 w-11/12 max-w-md rounded-lg border-4 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.45)]"></div>
        </div>

        <div x-show="cargando" class="absolute inset-0 flex items-center justify-center text-white">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Abriendo la cámara...
        </div>

        <div x-show="error" class="absolute inset-0 flex items-center justify-center p-6">
            <div class="max-w-sm rounded-lg bg-white p-5 text-center text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                <i class="fa-solid fa-video-slash mb-2 text-3xl text-gray-400"></i>
                <p x-text="error"></p>
            </div>
        </div>

        <div class="absolute inset-x-0 top-0 p-4 text-center text-sm text-white">
            <p x-show="!error">Apunte al código de barras</p>
            <p x-show="continuo && lecturas > 0" class="mt-1 font-mono">
                <i class="fa-solid fa-check text-brand-300"></i>
                <span x-text="ultimo"></span> · <span x-text="lecturas"></span> leído(s)
            </p>
        </div>
    </div>

    <div class="flex items-center justify-between gap-3 bg-gray-900 px-4 py-4 text-white">
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" x-model="continuo" class="rounded border-gray-500 text-brand-600 focus:ring-brand-500">
            Seguir escaneando
        </label>

        <div class="flex items-center gap-2">
            <button type="button" x-show="hayLinterna" x-on:click="alternarLinterna()"
                class="rounded-full px-3 py-2 hover:bg-white/10"
                :class="linternaEncendida ? 'text-yellow-300' : 'text-white'" title="Linterna">
                <i class="fa-solid fa-lightbulb"></i>
            </button>
            <button type="button" x-on:click="cerrar()"
                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-200">
                <span x-text="continuo && lecturas > 0 ? 'Listo' : 'Cancelar'"></span>
            </button>
        </div>
    </div>
</div>
