<div>
    <div>
        <a href="{{ route($tipoEnum->ruta()) }}"
            class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full">
            Ir a {{ $tipoEnum->plural() }}</a>
    </div>

    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mt-4 mb-1">
        Historial de {{ $articulo->nombre }}
    </h2>
    <p class="text-center text-sm text-gray-500 dark:text-gray-400 mb-6">
        {{ $articulo->sku ? 'SKU ' . $articulo->sku . ' · ' : '' }}
        @if ($tipoEnum === \App\Enums\ArticuloTipo::Repuesto)
            {{ $articulo->fabricante ?: 'Sin fabricante' }}
            · {{ $articulo->categoria?->nombre ?? 'Sin categoría' }}
            · {{ $articulo->modelo?->nombre ?? 'Sin modelo' }}
        @else
            {{ $articulo->marca ?: 'Sin marca' }}
            · {{ $articulo->categoria?->nombre ?? 'Sin categoría' }}
            @if ($articulo->modelosCompatibles->isNotEmpty())
                · Compatible con {{ $articulo->modelosCompatibles->pluck('nombre')->implode(', ') }}
            @endif
        @endif
    </p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
        <div
            class="bg-brand-100 dark:bg-brand-800 p-6 rounded-2xl shadow-lg border border-brand-200 dark:border-brand-700">
            <h3 class="text-sm font-medium text-brand-700 dark:text-brand-300 truncate">Stock actual</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-brand-900 dark:text-brand-100">
                {{ $articulo->cantidad }}
            </p>
            {{-- El total es la suma de la subtabla; el desglose dice dónde están
                 esas unidades, que es lo que no se podía ver en ninguna parte. --}}
            <div class="mt-2 text-brand-800 dark:text-brand-200">
                {!! $articulo->desgloseStock() !!}
            </div>
        </div>

        <div
            class="bg-gray-100 dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">Balance calculado</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                {{ $balanceCalculado }}
            </p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Entradas − Salidas de este historial</p>
        </div>

        <div
            class="bg-amber-100 dark:bg-amber-800 p-6 rounded-2xl shadow-lg border border-amber-200 dark:border-amber-700">
            <h3 class="text-sm font-medium text-amber-700 dark:text-amber-300 truncate">Costo (código)</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-amber-900 dark:text-amber-100">
                Bs {{ number_format((float) $articulo->costo, 2) }}
            </p>
        </div>

        <div
            class="bg-green-100 dark:bg-green-800 p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
            <h3 class="text-sm font-medium text-green-700 dark:text-green-300 truncate">Precio actual</h3>
            <p class="mt-1 text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                Bs {{ number_format((float) $articulo->precio, 2) }}
            </p>
        </div>
    </div>

    @if ($balanceCalculado !== (int) $articulo->cantidad)
        <div
            class="mb-6 p-4 rounded-2xl border border-yellow-300 bg-yellow-50 text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
            <p class="text-sm">
                <span class="font-semibold">El balance calculado no coincide con el stock actual
                    (diferencia: {{ $balanceCalculado - (int) $articulo->cantidad }}).</span>
                Los ajustes de stock hechos a mano desde la ficha no son documentos: están en la pestaña
                <strong>Cambios</strong>. Si tampoco aparecen ahí, algo movió el stock por fuera del sistema.
            </p>
        </div>
    @endif

    {{-- Dos preguntas distintas, dos pestañas:
         Movimientos = a donde fue el stock (el UNION de los documentos).
         Cambios     = lo que una persona hizo sobre la ficha (la bitacora).
         Mismo patron de tablist que el historial del telefono. --}}
    <div x-data="{
            tabs: ['movimientos', 'cambios'],
            tab: 'movimientos',
            init() {
                // Whitelist: un hash inventado no debe dejar los paneles ocultos.
                const hash = window.location.hash.slice(1);
                if (this.tabs.includes(hash)) this.tab = hash;
            },
            select(nombre) {
                this.tab = nombre;
                // Conserva el query string donde rappasoft guarda orden y filtros.
                history.replaceState(null, '', '#' + nombre);
            },
            move(offset) {
                const i = (this.tabs.indexOf(this.tab) + offset + this.tabs.length) % this.tabs.length;
                this.select(this.tabs[i]);
                this.$refs['tab-' + this.tabs[i]].focus();
            },
         }" class="mt-4">

        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-1 sm:gap-8 overflow-x-auto" role="tablist"
                aria-label="Secciones del historial del artículo">

                <button type="button" role="tab" id="tab-movimientos" aria-controls="panel-movimientos"
                    x-ref="tab-movimientos"
                    :aria-selected="tab === 'movimientos' ? 'true' : 'false'"
                    :tabindex="tab === 'movimientos' ? 0 : -1"
                    @click="select('movimientos')"
                    @keydown.arrow-right.prevent="move(1)"
                    @keydown.arrow-left.prevent="move(-1)"
                    :class="tab === 'movimientos'
                        ? 'border-brand-500 text-brand-600 dark:text-brand-400 dark:border-brand-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="shrink-0 whitespace-nowrap border-b-2 px-4 sm:px-1 py-3 text-sm font-medium rounded-t
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                           transition duration-150 ease-in-out">
                    Movimientos
                </button>

                <button type="button" role="tab" id="tab-cambios" aria-controls="panel-cambios"
                    x-ref="tab-cambios"
                    :aria-selected="tab === 'cambios' ? 'true' : 'false'"
                    :tabindex="tab === 'cambios' ? 0 : -1"
                    @click="select('cambios')"
                    @keydown.arrow-right.prevent="move(1)"
                    @keydown.arrow-left.prevent="move(-1)"
                    :class="tab === 'cambios'
                        ? 'border-brand-500 text-brand-600 dark:text-brand-400 dark:border-brand-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="shrink-0 whitespace-nowrap border-b-2 px-4 sm:px-1 py-3 text-sm font-medium rounded-t
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                           transition duration-150 ease-in-out">
                    Cambios
                </button>
            </nav>
        </div>

        <div id="panel-movimientos" role="tabpanel" aria-labelledby="tab-movimientos" tabindex="0"
            x-show="tab === 'movimientos'" x-cloak class="mt-4 focus:outline-none">
            @livewire('articulo-historial.movimientos-stock-table', ['tipo' => $tipoEnum->value, 'articulo_id' => $articulo->id], key('movimientos-table-' . $tipoEnum->value . '-' . $articulo->id))
        </div>

        <div id="panel-cambios" role="tabpanel" aria-labelledby="tab-cambios" tabindex="0"
            x-show="tab === 'cambios'" x-cloak class="mt-4 focus:outline-none">
            @livewire('bitacora.bitacora-table', ['tipo' => $tipoEnum->modelo(), 'sujetoId' => $articulo->id], key('cambios-table-' . $tipoEnum->value . '-' . $articulo->id))
        </div>
    </div>
</div>
