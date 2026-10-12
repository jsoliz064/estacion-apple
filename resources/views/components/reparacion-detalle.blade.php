{{--
    Detalle de una reparacion, solo lectura.

    Un unico bloque compartido por las dos pantallas que muestran una reparacion
    sin editarla: el detalle del historial del producto y el "ver reparacion" de
    la pantalla de tecnicos. Antes cada una tenia su propia copia y las dos se
    habian quedado atras respecto a las pantallas de edicion: faltaban el costo
    de repuestos, el tipo de cambio, los dos totales, la comision y la garantia.

    UNIDADES: todo en Bs. costo_total = costo (mano de obra) + costo_repuestos.

    No se recalcula nada: todo se lee tal como se guardo. Recalcular aqui
    abriria la puerta a que el detalle mostrara una cifra distinta de la
    registrada.
--}}
@props(['reparacion'])

@if ($reparacion)
    @php
        $bs = fn($v) => 'Bs. ' . number_format((float) $v, 2);

        // El modelo ProductoReparacion no declara $casts, asi que vuelve como
        // entero. Los tres componentes de edicion hacen lo mismo.
        $garantiaTecnico = (bool) $reparacion->garantia_tecnico;
        $comision = $reparacion->comision;

        $claseSi = 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200';
        $claseNo = 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
        $claseDistintivo = 'inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium';
    @endphp

    <div class="m-2 grid grid-cols-1 md:grid-cols-2 gap-6 animate-fade-in">
        <div>
            <x-label>Tecnico:</x-label>
            {{-- La FK tecnico_id es onDelete set null: sin el ?-> esto revienta
                 en cuanto se borre un tecnico con reparaciones. --}}
            <x-input type="text" value="{{ $reparacion->tecnico?->nombre ?? 'Sin tecnico' }}" class="w-full"
                disabled="true"></x-input>
        </div>

        <div>
            <x-label>Estado de la Reparacion:</x-label>
            <div class="mt-1">
                <span
                    class="{{ $claseDistintivo }} {{ $reparacion->estado === 'Pendiente'
                        ? 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200'
                        : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' }}">
                    {{ $reparacion->estado }}
                </span>
            </div>
        </div>
    </div>

    <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
        <div>
            <x-label>Fecha de Entrega:</x-label>
            <x-input type="text" value="{{ $reparacion->fecha_entrega ?? '-' }}" class="w-full"
                disabled="true"></x-input>
        </div>

        <div>
            <x-label>Fecha de Recogida:</x-label>
            <x-input type="text" value="{{ $reparacion->fecha_recogida ?? '-' }}" class="w-full"
                disabled="true"></x-input>
        </div>

        <div>
            <x-label>Venta de Garantia:</x-label>
            <x-input type="text" value="{{ $reparacion->venta_id ? 'Venta #' . $reparacion->venta_id : '-' }}"
                class="w-full" disabled="true"></x-input>
        </div>
    </div>

    <div class="m-2">
        <x-label>Repuestos Técnico:</x-label>
        <textarea disabled="true" rows="3"
            class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm">{{ $reparacion->repuestos_tecnico }}</textarea>
    </div>

    <div class="m-2">
        <x-label>Repuestos Propios:</x-label>
        <textarea disabled="true" rows="3"
            class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm">{{ $reparacion->repuestos_propios }}</textarea>
    </div>

    <div class="m-2">
        <x-label>Repuestos a Devolver:</x-label>
        <textarea disabled="true" rows="3"
            class="w-full mt-1 p-2 border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-brand-500 dark:focus:border-brand-600 focus:ring-brand-500 dark:focus:ring-brand-600 rounded-md shadow-sm">{{ $reparacion->repuestos_devolver }}</textarea>
    </div>

    @if ($reparacion->repuestos->count() > 0)
        <div class="m-2">
            {{-- "Repuestos del inventario" y no "Repuestos Propios": esa
                 etiqueta ya la lleva el textarea de arriba, y tenerla dos veces
                 en la misma pantalla hacia pensar que eran lo mismo. --}}
            <x-label>Repuestos del Inventario:</x-label>
            <div class="overflow-x-auto mt-2">
                <table class="min-w-full border text-xs">
                    <thead>
                        <tr class="bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                            <th class="p-2 border border-gray-300 dark:border-gray-600 text-left">Repuesto</th>
                            <th class="p-2 border border-gray-300 dark:border-gray-600 text-right">Costo (Bs)</th>
                            <th class="p-2 border border-gray-300 dark:border-gray-600 text-right">Cant.</th>
                            <th class="p-2 border border-gray-300 dark:border-gray-600 text-right">Subtotal (Bs)</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-900 text-gray-800 dark:text-gray-300">
                        @foreach ($reparacion->repuestos as $linea)
                            @php
                                $repuesto = $linea->repuesto;
                                $categoria = $repuesto?->categoria ? $repuesto->categoria->nombre . ' - ' : '';
                                $modelo = $repuesto?->modelo?->nombre ?? '';
                                $detalle = trim($categoria . $modelo);
                            @endphp
                            <tr class="bg-gray-50 dark:bg-gray-800">
                                <td class="p-2 border border-gray-300 dark:border-gray-600">
                                    {{ $repuesto?->nombre ?? 'Repuesto eliminado' }}
                                    @if ($detalle)
                                        <span class="text-gray-500 dark:text-gray-400">({{ $detalle }})</span>
                                    @endif
                                </td>
                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                    {{ number_format((float) $linea->costo, 2) }}</td>
                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                    {{ $linea->cantidad }}</td>
                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right">
                                    {{ number_format((float) $linea->subtotal_costo, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="m-2 grid grid-cols-1 md:grid-cols-3 gap-6 animate-fade-in">
        <div>
            <x-label>Mano de obra (Bs):</x-label>
            <x-input type="text" value="{{ $bs($reparacion->costo) }}" class="w-full" disabled="true"></x-input>
        </div>
        <div>
            <x-label>Repuestos (Bs):</x-label>
            <x-input type="text" value="{{ $bs($reparacion->costo_repuestos) }}" class="w-full" disabled="true"></x-input>
        </div>
        <div>
            <x-label>Total (Bs):</x-label>
            <x-input type="text" value="{{ $bs($reparacion->costo_total) }}" class="w-full" disabled="true"></x-input>
        </div>
    </div>

    <div class="m-2 flex flex-wrap gap-6">
        <div>
            <x-label>Comisión del técnico:</x-label>
            <div class="mt-1 text-sm">
                @if ($comision)
                    {{ $bs($comision->monto) }} ({{ rtrim(rtrim(number_format((float) $comision->porcentaje, 2), '0'), '.') }} %)
                    {!! $comision->estado()->badge() !!}
                @else
                    <span class="{{ $claseDistintivo }} {{ $claseNo }}">Sin comisión</span>
                @endif
            </div>
        </div>

        <div>
            <x-label>Garantia del Tecnico:</x-label>
            <div class="mt-1">
                <span class="{{ $claseDistintivo }} {{ $garantiaTecnico ? $claseSi : $claseNo }}">
                    {{ $garantiaTecnico ? 'Sí' : 'No' }}
                </span>
            </div>
        </div>
    </div>
@endif
