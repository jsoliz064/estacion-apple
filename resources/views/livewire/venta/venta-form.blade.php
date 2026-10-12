<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-bold text-gray-800 dark:text-white">
            {{ $this->esEdicion() ? 'Editar venta #' . $ventaId : 'Nueva venta' }}
        </h2>
        <a href="{{ $this->esEdicion() ? route('ventas.detalles', $ventaId) : route('ventas') }}"
            class="text-sm text-brand-600 hover:underline">Volver</a>
    </div>

    @if ($reservaId)
        <div class="mb-4 rounded-lg border border-amber-300 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-900/40 dark:text-amber-100">
            <i class="fa-solid fa-bookmark"></i>
            Venta de la <strong>reserva #{{ $reservaId }}</strong> de {{ $venta['cliente'] }}:
            la seña de Bs {{ number_format((float) ($sena['monto'] ?? 0), 2) }} ({{ $sena['metodo'] ?? '' }}) se descuenta del total.
        </div>
        <x-input-error for="reserva" class="mb-2" />
    @endif

    {{-- Cabecera --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <x-label value="Sucursal" />
            {{-- Se bloquea en cuanto hay lineas (o en edicion): el stock sale de ella. --}}
            @if (!$this->esEdicion() && count($lineas) === 0)
                <select wire:model.live="venta.sucursal_id"
                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                    <option value="">Seleccione una sucursal</option>
                    @foreach ($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
                <x-input-error for="venta.sucursal_id" class="mt-1" />
            @else
                <x-input type="text" disabled="true" class="mt-1 w-full"
                    value="{{ $sucursales->firstWhere('id', $venta['sucursal_id'])?->nombre ?? '—' }}" />
            @endif
        </div>
        <div>
            <x-label value="Precio de lista (equipos)" />
            <select wire:model.live="tipo_precio"
                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm h-10">
                <option value="Vendedor">Vendedor</option>
                <option value="Cliente">Cliente</option>
            </select>
        </div>
        <div>
            <x-cliente-picker :search="$searchCliente" :sugerencias="$filteredClientes"
                :nombre="$venta['cliente'] ?? null" :cliente-id="$venta['cliente_id'] ?? null" label="Cliente" />
            <x-input-error for="cliente" class="mt-1" />
            {{-- Avisa, no bloquea (docs/04): venderle a quien ya debe es decision del vendedor. --}}
            @if ($deudaCliente > 0)
                <p class="mt-1 text-xs font-semibold text-amber-700 dark:text-amber-300">
                    <i class="fa-solid fa-triangle-exclamation"></i> Ya debe Bs {{ number_format($deudaCliente, 2) }} de otras ventas.
                </p>
            @endif
        </div>
    </div>

    {{-- Buscador --}}
    <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
        @include('livewire.partials.buscador-articulos', ['placeholder' => 'Escanee o escriba IMEI, código, SKU o nombre...'])
        <div class="mt-2">
            <button type="button" wire:click="abrirSelectorEquipos" @disabled($this->sucursalDelDocumento() === null)
                class="text-sm text-brand-600 hover:underline disabled:opacity-40">
                <i class="fa-solid fa-mobile-screen-button"></i> Ver equipos disponibles
            </button>
        </div>
    </div>

    {{-- Lineas --}}
    @if (count($lineas) > 0 || count($cobrosExistentes) > 0)
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            {{-- Grid y no <table>: en el celular cada linea es una tarjeta con los
                 inputs a media pantalla (en la tabla de 7 columnas un precio de
                 1000 mostraba un solo digito). En pantalla ancha vuelven a ser
                 columnas. Un solo juego de inputs: dos wire:model iguales se pisan. --}}
            <div class="hidden md:grid md:grid-cols-[minmax(0,1fr)_5rem_8rem_7rem_5rem_7rem_2rem] gap-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-200 text-sm font-semibold rounded-t-md">
                <div class="p-2 text-left">Detalle</div>
                <div class="p-2 text-right">Cant.</div>
                <div class="p-2 text-right">Precio Bs</div>
                <div class="p-2 text-right">Desc. Bs</div>
                <div class="p-2 text-right">Garantía</div>
                <div class="p-2 text-right">Subtotal</div>
                <div class="p-2"></div>
            </div>
            <div class="text-sm space-y-3 md:space-y-0">
                @foreach ($lineas as $index => $linea)
                    @php($esEquipo = $linea['tipo'] === 'Producto')
                    {{-- wire:key con el indice: los inputs se enlazan por posicion. --}}
                    <div class="relative grid grid-cols-2 gap-2 rounded-md border border-gray-200 p-2 md:grid-cols-[minmax(0,1fr)_5rem_8rem_7rem_5rem_7rem_2rem] md:rounded-none md:border-0 md:border-t md:p-0 dark:border-gray-700"
                        wire:key="linea-{{ $linea['tipo'] }}-{{ $linea['id'] }}-{{ $index }}">
                        <div class="col-span-2 pr-8 md:col-span-1 md:p-2">
                            {!! \App\Enums\LineaTipo::badge($linea['tipo']) !!}
                            <span class="text-gray-900 dark:text-gray-100">{{ $linea['descripcion'] }}</span>
                            @if ($linea['codigo'])
                                <span class="block text-xs text-gray-500 font-mono">{{ $esEquipo ? 'IMEI ' : 'SKU ' }}{{ $linea['codigo'] }}</span>
                            @endif
                            @if (!$esEquipo)
                                <span class="block text-xs text-gray-500">Stock aquí: {{ $linea['stock'] }}</span>
                                {{-- Con que equipo se vende: se agrupa bajo el en el detalle y en la nota. --}}
                                @if (count($equiposVenta) > 0)
                                    <select wire:model.live="lineas.{{ $index }}.con_producto_id"
                                        class="mt-1 block w-full max-w-xs text-xs border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                        <option value="">Suelto (sin equipo)</option>
                                        @foreach ($equiposVenta as $pid => $etiqueta)
                                            <option value="{{ $pid }}">Con {{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                @endif
                            @endif
                            @if ($esEquipo && ($linea['repuestos_elegibles'] ?? 0) > 0)
                                @php($elegidos = count($repuestosVenta[$linea['id']] ?? []))
                                <button type="button" wire:click="abrirRepuestosDe({{ $linea['id'] }})"
                                    class="block text-xs underline {{ $elegidos ? 'text-green-600 dark:text-green-400 font-semibold' : 'text-brand-600 dark:text-brand-400' }}">
                                    {{ $elegidos ? $elegidos . ' repuesto(s) de taller a cobrar' : $linea['repuestos_elegibles'] . ' repuesto(s) de taller cobrable(s)' }}
                                </button>
                            @endif
                        </div>
                        @if ($esEquipo)
                            <div class="hidden md:block md:p-2 text-right">1</div>
                        @else
                            <label class="block md:p-2">
                                <span class="md:hidden text-xs text-gray-500">Cant.</span>
                                <x-input type="number" min="1" max="{{ $linea['stock'] }}" inputmode="numeric" class="w-full text-right"
                                    wire:model.live.debounce.400ms="lineas.{{ $index }}.cantidad" onfocus="this.select()" />
                            </label>
                        @endif
                        <label class="block md:p-2">
                            <span class="md:hidden text-xs text-gray-500">Precio Bs</span>
                            <x-input type="number" min="0" step="0.01" inputmode="decimal" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.precio" onfocus="this.select()" />
                        </label>
                        <label class="block md:p-2">
                            <span class="md:hidden text-xs text-gray-500">Desc. Bs</span>
                            <x-input type="number" min="0" step="0.01" inputmode="decimal" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.descuento" onfocus="this.select()" />
                        </label>
                        @if ($esEquipo)
                            <label class="block md:p-2">
                                <span class="md:hidden text-xs text-gray-500">Garantía (meses)</span>
                                <x-input type="number" min="0" max="60" inputmode="numeric" class="w-full text-right" wire:model.live.debounce.400ms="lineas.{{ $index }}.garantia_meses" title="Meses de garantía" />
                            </label>
                        @else
                            <div class="hidden md:block md:p-2 text-right text-gray-400">—</div>
                        @endif
                        <div class="col-span-2 flex items-center justify-between md:col-span-1 md:block md:p-2 md:text-right whitespace-nowrap">
                            <span class="md:hidden text-xs text-gray-500">Subtotal Bs</span>
                            <span class="font-semibold md:font-normal">{{ number_format($this->subtotalLinea($linea), 2) }}</span>
                        </div>
                        <div class="absolute right-1 top-1 md:static md:p-2 md:text-center">
                            <button type="button" wire:click="quitarLinea({{ $index }})" class="px-2 text-red-600 hover:text-red-800 text-lg" title="Quitar">&times;</button>
                        </div>
                    </div>
                    {{-- Los regalos del equipo: van con el a precio 0, sin quitar (se quitan desde la ficha del equipo). --}}
                    @if ($esEquipo)
                        @foreach ($regalos[$linea['id']] ?? [] as $regalo)
                            <div class="grid grid-cols-2 gap-2 rounded-md bg-pink-50 p-2 md:grid-cols-[minmax(0,1fr)_5rem_8rem_7rem_5rem_7rem_2rem] md:rounded-none md:p-0 dark:bg-pink-900/20"
                                wire:key="regalo-{{ $linea['id'] }}-{{ $loop->index }}">
                                <div class="col-span-2 md:col-span-1 md:p-2 md:pl-6">
                                    <span class="text-gray-500">↳</span>
                                    {!! \App\Enums\LineaTipo::badge('Accesorio') !!}
                                    <span class="text-gray-900 dark:text-gray-100">{{ $regalo['nombre'] }}</span>
                                    <span class="block text-xs text-pink-700 dark:text-pink-300">Regalo con el equipo</span>
                                </div>
                                <div class="md:p-2 md:text-right"><span class="md:hidden text-xs text-gray-500">Cant. </span>{{ $regalo['cantidad'] }}</div>
                                <div class="hidden md:block md:p-2 text-right text-gray-500">0.00</div>
                                <div class="hidden md:block"></div>
                                <div class="hidden md:block"></div>
                                <div class="text-right md:p-2 whitespace-nowrap"><span class="md:hidden text-xs text-gray-500">Subtotal Bs </span>0.00</div>
                                <div class="hidden md:block"></div>
                            </div>
                        @endforeach
                    @endif
                @endforeach
                @foreach ($cobrosExistentes as $cobro)
                    <div class="grid grid-cols-2 gap-2 rounded-md bg-green-50 p-2 md:grid-cols-[minmax(0,1fr)_5rem_8rem_7rem_5rem_7rem_2rem] md:rounded-none md:border-t md:border-gray-200 md:p-0 dark:bg-green-900/30"
                        wire:key="cobro-{{ $loop->index }}">
                        <div class="col-span-2 md:col-span-5 md:p-2">
                            {!! \App\Enums\LineaTipo::badge('Repuesto') !!} {{ $cobro['nombre'] }}
                            <span class="block text-xs text-gray-500">Cobro de taller (ya registrado). Se anula desde el detalle de la venta.</span>
                        </div>
                        <div class="col-span-2 flex justify-between md:col-span-1 md:block md:p-2 md:text-right">
                            <span class="md:hidden text-xs text-gray-500">Subtotal Bs</span>
                            <span>{{ number_format($cobro['subtotal'], 2) }}</span>
                        </div>
                        <div class="hidden md:block"></div>
                    </div>
                @endforeach
            </div>
            @error('lineas.*.cantidad') <p class="text-sm text-red-600 mt-1">{{ $message }}</p> @enderror
            <x-input-error for="lineas" class="mt-1" />
            <x-input-error for="detalles" class="mt-1" />
        </div>

        {{-- Totales --}}
        @php($t = $this->totales())
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4 grid grid-cols-2 md:grid-cols-4 gap-4">
            <div>
                <x-label value="Subtotal Bs" />
                <p class="mt-2 text-lg font-semibold">{{ number_format($t['subtotal'], 2) }}</p>
                @if ($this->totalCobrosNuevos() > 0)
                    <p class="text-xs text-green-700 dark:text-green-300">Incluye Bs {{ number_format($this->totalCobrosNuevos(), 2) }} de repuestos de taller</p>
                @endif
            </div>
            <div>
                <x-label value="Descuento Bs" />
                <x-input type="number" min="0" step="0.01" inputmode="decimal" class="mt-1 w-full" wire:model.live.debounce.400ms="venta.descuento" onfocus="this.select()" />
                <x-input-error for="venta.descuento" />
            </div>
            <div>
                <x-label value="Mano de obra Bs" />
                <x-input type="number" min="0" step="0.01" inputmode="decimal" class="mt-1 w-full" wire:model.live.debounce.400ms="venta.mano_obra" onfocus="this.select()" />
                <x-input-error for="venta.mano_obra" />
            </div>
            <div>
                <x-label value="Total Bs" />
                <p class="mt-2 text-2xl font-bold text-brand-700 dark:text-brand-300">{{ number_format($t['total'], 2) }}</p>
            </div>
        </div>

        {{-- Cobro --}}
        @php($saldo = $this->saldoPrevisto())
        <div class="mt-4 bg-white dark:bg-gray-800 rounded-lg shadow p-4">
            <div class="flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 dark:text-gray-100">Cobro</h3>
                @if (!$this->esEdicion())
                    <button type="button" wire:click="agregarPago" class="text-sm text-brand-600 hover:underline">
                        <i class="fa-solid fa-plus"></i> Agregar método
                    </button>
                @endif
            </div>

            @if ($this->esEdicion())
                {{-- Los cobros nuevos van por el modal de cobro, desde el detalle. --}}
                <ul class="mt-2 divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                    @forelse ($pagosExistentes as $pago)
                        <li class="py-1 flex justify-between" wire:key="pago-existente-{{ $loop->index }}">
                            <span class="text-gray-600 dark:text-gray-300">{{ $pago['fecha'] }} · {{ $pago['metodo'] }}</span>
                            <span>Bs {{ number_format($pago['monto'], 2) }}</span>
                        </li>
                    @empty
                        <li class="py-1 text-gray-500">Sin pagos registrados.</li>
                    @endforelse
                </ul>
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">Para cobrar o anular un pago, usa el detalle de la venta.</p>
            @else
                {{-- Lo cobrado que no es una fila: la seña de la reserva y la permuta. --}}
                @if (!empty($sena))
                    <div class="mt-2 flex justify-between rounded-md bg-amber-50 px-3 py-2 text-sm dark:bg-amber-900/30">
                        <span>Seña de la reserva #{{ $reservaId }} · {{ $sena['metodo'] ?? '' }}</span>
                        <span class="font-semibold">Bs {{ number_format((float) $sena['monto'], 2) }}</span>
                    </div>
                @endif

                @include('livewire.partials.filas-pago')

                {{-- Permuta: el equipo que entrega el cliente es un pago mas (PermutaService). --}}
                <div class="mt-4 border-t border-gray-200 pt-3 dark:border-gray-700">
                    @if (!$conPermuta)
                        <button type="button" wire:click="abrirPermuta" class="text-sm text-brand-600 hover:underline">
                            <i class="fa-solid fa-right-left"></i> Recibe un equipo en permuta
                        </button>
                    @else
                        <div class="flex items-center justify-between">
                            <h4 class="text-sm font-semibold text-gray-800 dark:text-gray-100">Equipo recibido en permuta</h4>
                            <button type="button" wire:click="quitarPermuta" class="text-xs text-red-600 hover:underline">Quitar</button>
                        </div>
                        <div class="mt-2 grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                            <div>
                                <x-label value="Modelo" />
                                <select wire:model="permuta.producto_modelo_id" class="mt-1 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                    <option value="">Modelo...</option>
                                    @foreach ($modelosPermuta as $m)
                                        <option value="{{ $m->id }}">{{ $m->nombre }}</option>
                                    @endforeach
                                </select>
                                <x-input-error for="permuta.producto_modelo_id" />
                            </div>
                            <div>
                                <x-label value="Almacenamiento" />
                                <select wire:model="permuta.almacenamiento" class="mt-1 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                    @foreach ($almacenamientos as $a)
                                        <option value="{{ $a->value }}">{{ $a->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error for="permuta.almacenamiento" />
                            </div>
                            <div>
                                <x-label value="Color" />
                                <select wire:model="permuta.color" class="mt-1 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                    <option value="">Color...</option>
                                    @foreach ($colores as $c)
                                        <option value="{{ $c->value }}">{{ $c->value }}</option>
                                    @endforeach
                                </select>
                                <x-input-error for="permuta.color" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-label value="IMEI" />
                                <div class="mt-1 flex gap-2" data-escaner>
                                    <x-input type="text" class="w-full" wire:model="permuta.imei" inputmode="numeric" autocomplete="off"
                                        placeholder="Escanee o escriba el IMEI" x-on:keydown.enter.prevent="" />
                                    <x-boton-escaner modo="input" />
                                </div>
                                <x-input-error for="permuta.imei" />
                            </div>
                            <div>
                                <x-label value="Grado" />
                                <select wire:model="permuta.estado_grado" class="mt-1 block w-full h-10 border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 focus:border-brand-500 focus:ring-brand-500 rounded-md shadow-sm">
                                    @foreach ($grados as $g)
                                        <option value="{{ $g->value }}">{{ $g->label() }}</option>
                                    @endforeach
                                </select>
                                <x-input-error for="permuta.estado_grado" />
                            </div>
                            <div>
                                <x-label value="Batería (%)" />
                                <x-input type="number" min="1" max="100" class="mt-1 w-full" wire:model="permuta.bateria_porcentaje" />
                                <x-input-error for="permuta.bateria_porcentaje" />
                            </div>
                            <div class="sm:col-span-2">
                                <x-label value="Valor que se le reconoce (Bs)" />
                                <x-input type="number" min="0" step="0.01" class="mt-1 w-full" wire:model.live.debounce.400ms="permuta.valor" onfocus="this.select()" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Entra al inventario con este valor como costo, para ponerle precio y revenderlo.</p>
                                <x-input-error for="permuta.valor" />
                            </div>
                        </div>
                    @endif
                </div>
            @endif
            <x-input-error for="pagos" class="mt-1" />

            <div class="mt-3 flex flex-wrap justify-between gap-2 text-sm">
                <span>Cobrado: <strong>Bs {{ number_format($this->cobradoPrevisto(), 2) }}</strong></span>
                @if ($saldo > 0)
                    <span class="font-semibold text-amber-700 dark:text-amber-300">
                        <i class="fa-solid fa-hand-holding-dollar"></i> Queda a crédito: Bs {{ number_format($saldo, 2) }}
                        @if (empty($venta['cliente_id']))
                            · elige el cliente
                        @endif
                    </span>
                @elseif ($saldo < 0)
                    <span class="font-semibold text-red-600">Se cobra Bs {{ number_format(-$saldo, 2) }} de más</span>
                @else
                    <span class="font-semibold text-green-700 dark:text-green-300">Pagada</span>
                @endif
            </div>
        </div>

        <div class="mt-4 flex justify-end">
            <x-primary-button wire:click="guardar" wire:loading.attr="disabled" wire:target="guardar">
                {{ $this->esEdicion() ? 'Guardar cambios' : 'Registrar venta' }}
            </x-primary-button>
        </div>
    @elseif ($this->sucursalDelDocumento() !== null)
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400 text-center">Escanee o busque lo que se vende.</p>
    @endif

    @livewire('articulo.modals.articulo-selector-modal')
    @livewire('producto.modals.producto-selector-modal')
    @livewire('producto.modals.producto-repuestos-venta-modal')
    @livewire('cliente.modals.cliente-selector-modal')
    @livewire('cliente.modals.cliente-create-modal')
</div>
