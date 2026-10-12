{{--
    Los cambios de una fila de la bitacora: siempre pares [antes, despues].

    Crear es [null, valor] y borrar [valor, null] (ver BitacoraObserver), asi
    que una sola plantilla sirve para los tres casos y para las tres pantallas.

    Con mas de cuatro campos se pliega: el alta de un telefono trae una docena,
    y desplegados empujan la tabla hacia abajo hasta perderla de vista.
--}}
@props(['cambios' => null])

@php
    $cambios = is_array($cambios) ? $cambios : [];

    $legible = function ($valor) {
        if ($valor === null || $valor === '') {
            return '—';
        }
        if (is_bool($valor)) {
            return $valor ? 'sí' : 'no';
        }
        if (is_array($valor)) {
            $valor = json_encode($valor, JSON_UNESCAPED_UNICODE);
        }

        return mb_strimwidth((string) $valor, 0, 60, '…');
    };

    $etiqueta = fn($columna) => ucfirst(str_replace('_', ' ', (string) $columna));
@endphp

@if ($cambios !== [])
    @php $plegar = count($cambios) > 4; @endphp

    <div class="mt-1 text-xs text-gray-600 dark:text-gray-400">
        @if ($plegar)
            <details>
                <summary class="cursor-pointer select-none text-brand-600 dark:text-brand-400">
                    {{ count($cambios) }} campos
                </summary>
        @endif

        <ul class="space-y-0.5 {{ $plegar ? 'mt-1' : '' }}">
            @foreach ($cambios as $columna => $par)
                @php
                    [$antes, $despues] = is_array($par) && count($par) === 2 ? array_values($par) : [null, $par];
                @endphp
                <li>
                    <span class="font-medium text-gray-700 dark:text-gray-300">{{ $etiqueta($columna) }}:</span>

                    @if ($antes === null)
                        {{-- Alta: no habia nada antes. --}}
                        <span>{{ $legible($despues) }}</span>
                    @elseif ($despues === null)
                        {{-- Borrado: el valor que tenia. --}}
                        <span class="line-through">{{ $legible($antes) }}</span>
                    @else
                        <span class="text-red-700 dark:text-red-400">{{ $legible($antes) }}</span>
                        <span aria-hidden="true">→</span>
                        <span class="text-green-700 dark:text-green-400">{{ $legible($despues) }}</span>
                    @endif
                </li>
            @endforeach
        </ul>

        @if ($plegar)
            </details>
        @endif
    </div>
@endif
