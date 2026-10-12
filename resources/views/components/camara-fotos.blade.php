{{-- La camara de fotos del equipo: una sola, en el layout (resources/js/app.js,
     Alpine "camaraFotos"), igual que el lector de codigos. Fuera de Livewire a
     proposito: dentro de un modal, cada respuesta del servidor la volvia a
     ocultar. Comparte con el lector abrirCamara(), asi que nunca hay dos
     camaras encendidas.
     z-[80]: por encima de los modales (z-50) y del lector (z-[70]). --}}
<div x-data="camaraFotos" x-on:abrir-camara-fotos.window="abrir($event.detail)" x-show="abierto" x-cloak
    class="fixed inset-0 z-[80] flex flex-col bg-black" style="display: none;" role="dialog" aria-modal="true"
    aria-label="Tomar fotos">

    <div class="relative flex-1 overflow-hidden">
        <video x-ref="video" class="absolute inset-0 h-full w-full object-cover" playsinline muted autoplay></video>
        <canvas x-ref="canvas" class="hidden"></canvas>

        {{-- El destello confirma el disparo. --}}
        <div x-show="destello" class="pointer-events-none absolute inset-0 bg-white/70"></div>

        <div x-show="cargando" class="absolute inset-0 flex items-center justify-center text-white">
            <i class="fa-solid fa-spinner fa-spin mr-2"></i> Abriendo la cámara...
        </div>

        <div x-show="error" class="absolute inset-0 flex items-center justify-center p-6">
            <div class="max-w-sm rounded-lg bg-white p-5 text-center text-gray-800 dark:bg-gray-800 dark:text-gray-100">
                <i class="fa-solid fa-video-slash mb-2 text-3xl text-gray-400"></i>
                <p x-text="error"></p>
            </div>
        </div>

        <div x-show="!error" class="absolute inset-x-0 top-0 p-4 text-center text-sm text-white">
            <p x-text="tomadas > 0 ? tomadas + ' foto(s) tomada(s)' : 'Toque el botón para sacar una foto'"></p>
        </div>
    </div>

    <div class="grid grid-cols-3 items-center bg-gray-900 px-4 py-4 text-white">
        <div class="flex items-center gap-2">
            <template x-if="ultima">
                <img :src="ultima" alt="Última foto" class="h-12 w-12 rounded-md border border-white/40 object-cover">
            </template>
            <span x-show="tomadas > 0" class="text-sm" x-text="tomadas"></span>
        </div>

        <div class="flex justify-center">
            <button type="button" x-on:click="disparar()" :disabled="cargando || !!error"
                class="h-16 w-16 rounded-full border-4 border-white bg-white/20 transition active:scale-90 active:bg-white/60 disabled:opacity-40"
                title="Sacar foto" aria-label="Sacar foto"></button>
        </div>

        <div class="flex justify-end">
            <button type="button" x-on:click="cerrar()"
                class="rounded-md bg-white px-4 py-2 text-sm font-semibold text-gray-900 hover:bg-gray-200">
                <span x-text="tomadas > 0 ? 'Listo' : 'Cancelar'"></span>
            </button>
        </div>
    </div>
</div>
