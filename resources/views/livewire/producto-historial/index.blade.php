<div>
    <div>
        <a href="{{ route('productos') }}" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-full">Ir a Productos</a>
    </div>
    <h2 class="text-center text-2xl font-bold text-gray-800 dark:text-white mb-6">Historial del Producto con Imei
        #{{ $producto->imei }}</h2>

    @php
        $bs = fn($v) => 'Bs ' . number_format((float) $v, 2);
        $compra = $producto->compraDetalle?->compra;
        $lineaVenta = $producto->ventaDetalle;
    @endphp

    {{-- La ficha del equipo: de donde vino, a donde fue y lo que le cambio el
         costo. La compra y la venta salen de sus LINEAS (no hay compra_id). --}}
    <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Equipo</h3>
            <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $producto->modelo?->nombre }} {{ $producto->almacenamiento }}</p>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                {{ \App\Enums\ProductoEstado::labelDe($producto->estado) }} · {{ $producto->sucursal?->nombre }}
            </p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Grado {{ \App\Enums\ProductoGrado::labelDe($producto->estado_grado) }} · {{ $producto->tipo_venta }}
                @if ($producto->sku) · SKU {{ $producto->sku }} @endif
            </p>
            @if ($producto->estaDadoDeBaja())
                <p class="mt-2 text-sm font-semibold text-red-600 dark:text-red-400">
                    Dado de baja: {{ \App\Enums\BajaMotivo::labelDe($producto->motivo_baja) }},
                    {{ $producto->dado_de_baja_at->format('d/m/Y') }}
                    @if ($producto->bajaUser) ({{ $producto->bajaUser->name }}) @endif
                </p>
                @if ($producto->nota_baja)
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $producto->nota_baja }}</p>
                @endif
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Compra</h3>
            @if ($compra)
                <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                    @can('compra.detalle')
                        <a href="{{ route('compras.detalle', $compra->id) }}" class="underline hover:text-brand-700">Compra #{{ $compra->id }}</a>
                    @else
                        Compra #{{ $compra->id }}
                    @endcan
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $compra->proveedor?->nombre }} · {{ $compra->fecha?->format('d/m/Y') }}</p>
                <p class="text-xs text-gray-500 dark:text-gray-400">Costo de la línea: {{ $bs($producto->compraDetalle->costo) }}</p>
            @elseif ($producto->permuta)
                {{-- No vino de una compra: lo entrego un cliente como parte de pago. --}}
                <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                    Recibido en permuta ·
                    <a href="{{ route('ventas.detalles', $producto->permuta->venta_id) }}" class="underline hover:text-brand-700">Venta #{{ $producto->permuta->venta_id }}</a>
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ $producto->permuta->fecha->format('d/m/Y') }} · valor reconocido {{ $bs($producto->permuta->monto) }}</p>
            @else
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sin compra registrada.</p>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Costo</h3>
            <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">{{ $bs($producto->costo_total) }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Unidad {{ $bs($producto->costo_unidad) }} · Regalos {{ $bs($producto->costo_regalos) }} · Reparaciones {{ $bs($producto->costo_reparacion) }}
            </p>
            @if ($producto->regalos->isNotEmpty())
                <ul class="mt-2 text-xs text-gray-600 dark:text-gray-300">
                    @foreach ($producto->regalos as $regalo)
                        <li>{{ (int) $regalo->cantidad }} × {{ $regalo->accesorio?->nombre }} ({{ $bs($regalo->subtotal_costo) }})</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white dark:bg-gray-800 p-4 rounded-2xl shadow border border-gray-200 dark:border-gray-700">
            <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Venta</h3>
            @if ($lineaVenta)
                <p class="mt-1 font-semibold text-gray-900 dark:text-gray-100">
                    @can('venta.detalle')
                        <a href="{{ route('ventas.detalles', $lineaVenta->venta_id) }}" class="underline hover:text-brand-700">Venta #{{ $lineaVenta->venta_id }}</a>
                    @else
                        Venta #{{ $lineaVenta->venta_id }}
                    @endcan
                </p>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    {{ $bs($lineaVenta->subtotal) }} · {{ $lineaVenta->created_at?->format('d/m/Y') }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $lineaVenta->venta?->nombreCliente() ?: 'Sin cliente' }} · {{ $lineaVenta->venta?->user?->name }}
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Sin vender.</p>
            @endif
        </div>
    </div>

    {{-- El estado de las pestañas es una lista y no un ternario: con tres, un
         `hash === '#x' ? 'x' : 'todo'` dejaba sin abrir cualquier pestaña nueva,
         y el ciclo de flechas tiene que ir al vecino real. --}}
    <div x-data="{
            tabs: ['todo', 'repuestos', 'reparaciones'],
            tab: 'todo',
            init() {
                // Whitelist: un hash inventado no debe dejar los tres paneles ocultos.
                const hash = window.location.hash.slice(1);
                if (this.tabs.includes(hash)) this.tab = hash;
            },
            select(nombre) {
                this.tab = nombre;
                // '#x' se resuelve contra la URL actual, así que conserva el query
                // string donde rappasoft guarda orden, filtros y página.
                history.replaceState(null, '', '#' + nombre);
            },
            // Con dos pestañas izquierda y derecha hacían lo mismo. Con tres hay
            // que ir al vecino y envolver por los extremos (patrón tablist de ARIA).
            move(offset) {
                const i = (this.tabs.indexOf(this.tab) + offset + this.tabs.length) % this.tabs.length;
                this.select(this.tabs[i]);
                this.$refs['tab-' + this.tabs[i]].focus();
            },
         }" class="mt-4">

        {{-- Pestañas --}}
        <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex gap-1 sm:gap-8 overflow-x-auto" role="tablist"
                aria-label="Secciones del historial">

                <button type="button" role="tab" id="tab-todo" aria-controls="panel-todo" x-ref="tab-todo"
                    :aria-selected="tab === 'todo' ? 'true' : 'false'"
                    :tabindex="tab === 'todo' ? 0 : -1"
                    @click="select('todo')"
                    @keydown.arrow-right.prevent="move(1)"
                    @keydown.arrow-left.prevent="move(-1)"
                    @keydown.home.prevent="select(tabs[0]); $refs['tab-' + tabs[0]].focus()"
                    @keydown.end.prevent="select(tabs.at(-1)); $refs['tab-' + tabs.at(-1)].focus()"
                    :class="tab === 'todo'
                        ? 'border-brand-500 text-brand-600 dark:text-brand-400 dark:border-brand-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="shrink-0 whitespace-nowrap border-b-2 px-4 sm:px-1 py-3 text-sm font-medium rounded-t
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                           transition duration-150 ease-in-out">
                    Todo
                </button>

                <button type="button" role="tab" id="tab-repuestos" aria-controls="panel-repuestos" x-ref="tab-repuestos"
                    :aria-selected="tab === 'repuestos' ? 'true' : 'false'"
                    :tabindex="tab === 'repuestos' ? 0 : -1"
                    @click="select('repuestos')"
                    @keydown.arrow-right.prevent="move(1)"
                    @keydown.arrow-left.prevent="move(-1)"
                    @keydown.home.prevent="select(tabs[0]); $refs['tab-' + tabs[0]].focus()"
                    @keydown.end.prevent="select(tabs.at(-1)); $refs['tab-' + tabs.at(-1)].focus()"
                    :class="tab === 'repuestos'
                        ? 'border-brand-500 text-brand-600 dark:text-brand-400 dark:border-brand-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="shrink-0 whitespace-nowrap border-b-2 px-4 sm:px-1 py-3 text-sm font-medium rounded-t
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                           transition duration-150 ease-in-out">
                    Repuestos
                </button>

                <button type="button" role="tab" id="tab-reparaciones" aria-controls="panel-reparaciones"
                    x-ref="tab-reparaciones"
                    :aria-selected="tab === 'reparaciones' ? 'true' : 'false'"
                    :tabindex="tab === 'reparaciones' ? 0 : -1"
                    @click="select('reparaciones')"
                    @keydown.arrow-right.prevent="move(1)"
                    @keydown.arrow-left.prevent="move(-1)"
                    @keydown.home.prevent="select(tabs[0]); $refs['tab-' + tabs[0]].focus()"
                    @keydown.end.prevent="select(tabs.at(-1)); $refs['tab-' + tabs.at(-1)].focus()"
                    :class="tab === 'reparaciones'
                        ? 'border-brand-500 text-brand-600 dark:text-brand-400 dark:border-brand-400'
                        : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200'"
                    class="shrink-0 whitespace-nowrap border-b-2 px-4 sm:px-1 py-3 text-sm font-medium rounded-t
                           focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500
                           transition duration-150 ease-in-out">
                    Reparaciones
                </button>
            </nav>
        </div>

        <div id="panel-todo" role="tabpanel" aria-labelledby="tab-todo" tabindex="0" x-show="tab === 'todo'" x-cloak
            class="mt-4 focus:outline-none">
            @livewire('producto-historial.producto-historial-table', ['producto_id' => $producto->id], key('historial-table-' . $producto->id))
        </div>

        <div id="panel-repuestos" role="tabpanel" aria-labelledby="tab-repuestos" tabindex="0"
            x-show="tab === 'repuestos'" x-cloak class="mt-4 focus:outline-none">
            @livewire('producto-historial.producto-repuestos-table', ['producto_id' => $producto->id], key('repuestos-table-' . $producto->id))
        </div>

        <div id="panel-reparaciones" role="tabpanel" aria-labelledby="tab-reparaciones" tabindex="0"
            x-show="tab === 'reparaciones'" x-cloak class="mt-4 focus:outline-none">

            {{-- Estas cifras son FIJAS: no se mueven con los filtros de la tabla.
                 El pie dice "lo que estoy mirando"; esto, "lo que el teléfono
                 lleva gastado". --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-6">
                <div
                    class="bg-brand-100 dark:bg-brand-800 p-6 rounded-2xl shadow-lg border border-brand-200 dark:border-brand-700">
                    <h3 class="text-sm font-medium text-brand-700 dark:text-brand-300 truncate">Total gastado (Bs)</h3>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-brand-900 dark:text-brand-100">
                        Bs. {{ number_format((float) $resumenReparaciones->total, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-brand-600 dark:text-brand-300">Mano de obra + repuestos, todas las
                        reparaciones</p>
                </div>

                <div
                    class="bg-amber-100 dark:bg-amber-800 p-6 rounded-2xl shadow-lg border border-amber-200 dark:border-amber-700">
                    <h3 class="text-sm font-medium text-amber-700 dark:text-amber-300 truncate">Costo del teléfono (Bs)
                    </h3>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-amber-900 dark:text-amber-100">
                        Bs. {{ number_format((float) $resumenReparaciones->inventario, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-300">Sin el trabajo externo, que lo paga el
                        cliente</p>
                </div>

                <div
                    class="bg-gray-100 dark:bg-gray-800 p-6 rounded-2xl shadow-lg border border-gray-200 dark:border-gray-700">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 truncate">Reparaciones</h3>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-gray-900 dark:text-gray-100">
                        {{ $resumenReparaciones->reparaciones }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $resumenReparaciones->pendientes }} sin terminar
                    </p>
                </div>

                <div
                    class="bg-green-100 dark:bg-green-800 p-6 rounded-2xl shadow-lg border border-green-200 dark:border-green-700">
                    <h3 class="text-sm font-medium text-green-700 dark:text-green-300 truncate">Cobrado al cliente (Bs)
                    </h3>
                    <p class="mt-1 text-2xl font-semibold tracking-tight text-green-900 dark:text-green-100">
                        Bs. {{ number_format((float) $resumenReparaciones->cobrado_externo, 2) }}
                    </p>
                    <p class="mt-1 text-xs text-green-600 dark:text-green-300">Solo del trabajo externo</p>
                </div>
            </div>


            @if (abs((float) $resumenReparaciones->inventario - (float) $producto->costo_reparacion) > 0.01)
                <div
                    class="mb-6 p-4 rounded-2xl border border-yellow-300 bg-yellow-50 text-yellow-900 dark:border-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-100">
                    <p class="text-sm">
                        <span class="font-semibold">El costo de reparación guardado en el producto
                            (Bs. {{ number_format((float) $producto->costo_reparacion, 2) }}) no coincide con la suma de
                            estas reparaciones
                            (Bs. {{ number_format((float) $resumenReparaciones->inventario, 2) }}).</span>
                        El campo del producto solo se recalcula desde ciertos caminos, así que puede haber quedado viejo.
                        La cifra de arriba es la suma real de lo registrado.
                    </p>
                </div>
            @endif

            @livewire('producto-historial.producto-reparaciones-table', ['producto_id' => $producto->id], key('reparaciones-table-' . $producto->id))
        </div>
    </div>

    @livewire('producto-historial.modals.producto-historial-modal')

    {{-- Reusado tal cual de la pantalla de técnicos: ya hace find($id) y el
         load() de las relaciones que x-reparacion-detalle necesita.

         Fuera de los paneles a propósito: x-modal es `fixed` pero no usa
         x-teleport, y position:fixed no escapa de un ancestro con display:none
         por x-show. Dentro del panel no aparecería nunca. --}}
    @livewire('tecnico-producto.modals.reparacion-show-modal')
</div>
