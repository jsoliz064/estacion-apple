{{-- Los stickers de un lote de codigos para la impresora de etiquetas. HTML
     suelto, sin el layout, como la nota de venta: se abre en otra pestaña e
     imprime solo. Una etiqueta por pagina (@page del tamaño de la etiqueta):
     la impresora de etiquetas avanza a la siguiente con su sensor.
     En papel continuo (alto null) no hay salto de pagina: los stickers salen
     uno tras otro con una linea para cortar. --}}
@php
    $continuo = $tamano['alto'] === null;
    $ancho = $tamano['ancho'];
    $alto = $tamano['alto'] ?? 25;
    // 5 mm libres por lado: EAN-13 pide una zona blanca de 11 modulos a la
    // izquierda para que el lector encuentre el inicio, y la etiqueta
    // troquelada ademas se corre un poco al avanzar.
    $util = $ancho - 10;
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Códigos · lote {{ $lote->id }}</title>
    <style>
        @page { size: {{ $ancho }}mm {{ $continuo ? 'auto' : $alto . 'mm' }}; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; }
        .etiqueta {
            width: {{ $ancho }}mm; height: {{ $alto }}mm; padding: 1.5mm 0 1mm;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            overflow: hidden;
            @if (!$continuo) break-after: page; page-break-after: always; @endif
        }
        /* last-of-type y no last-child: despues de la ultima va el script que imprime. */
        .etiqueta:last-of-type { break-after: auto; page-break-after: auto; }
        @if ($continuo) .etiqueta + .etiqueta { border-top: 1px dashed #000; } @endif
        .marca { font-size: 6.5pt; font-weight: bold; letter-spacing: .5px; line-height: 1; }
        .barras { width: {{ $util }}mm; height: {{ round($alto * 0.5, 1) }}mm; margin: 1mm 0 .5mm; }
        .barras svg { width: 100%; height: 100%; display: block; }
        .numero { font-family: 'Courier New', Courier, monospace; font-size: 8.5pt; letter-spacing: 1.5px; line-height: 1; }
        .acciones { font-family: Arial, sans-serif; font-size: 13px; padding: 4mm; text-align: center; border-bottom: 1px solid #ccc; }
        .acciones button { font: inherit; padding: 2mm 4mm; cursor: pointer; }
        @media screen { .etiqueta { margin: 2mm auto; outline: 1px dotted #999; } }
        @media print { .acciones { display: none; } }
    </style>
</head>

<body>
    <div class="acciones">
        Lote #{{ $lote->id }} · {{ $codigos->count() }} {{ $codigos->count() === 1 ? 'código' : 'códigos' }}
        × {{ $lote->copias }} {{ $lote->copias === 1 ? 'copia' : 'copias' }}
        @if ($soloLibres) (solo los libres) @endif
        · {{ $tamano['nombre'] }}
        <br><br>
        <button type="button" onclick="window.print()">Imprimir</button>
    </div>

    @forelse ($codigos as $codigo)
        @php($svg = \App\Support\CodigoBarrasSvg::ean13($codigo))
        @for ($i = 0; $i < $lote->copias; $i++)
            <div class="etiqueta">
                <div class="marca">ESTACIÓN APPLE</div>
                <div class="barras">{!! $svg !!}</div>
                <div class="numero">{{ $codigo }}</div>
            </div>
        @endfor
    @empty
        <p class="acciones">No hay códigos libres en este lote: todos ya están asignados.</p>
    @endforelse

    @if ($codigos->isNotEmpty())
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</body>

</html>
