{{-- La nota de venta para la impresora termica de 80 mm (docs/03). HTML suelto,
     sin el layout: se abre en otra pestaña e imprime sola. Ancho util de 72 mm
     (el papel de 80 mm tiene unos 4 mm de margen fisico por lado). Comprobante
     interno: sin datos fiscales (decision del usuario). --}}
@php
    $bs = fn($n) => number_format((float) $n, 2, ',', '.');
    $lineaNombre = fn($d) => $d->producto_id
        ? trim(($d->producto?->modelo?->nombre ?? 'Equipo') . ' ' . ($d->producto?->almacenamiento ?? '') . ' ' . ($d->producto?->color ?? ''))
        : ($d->articulo()?->nombre ?? 'Artículo');
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nota {{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #000; font-family: 'Courier New', Courier, monospace; font-size: 12px; line-height: 1.35; }
        .nota { width: 72mm; margin: 0 auto; padding: 4mm 0; }
        .centro { text-align: center; }
        .logo { max-width: 40mm; max-height: 22mm; display: block; margin: 0 auto 2mm; }
        h1 { font-size: 15px; margin: 1mm 0 0; letter-spacing: 1px; }
        .num { font-size: 13px; font-weight: bold; }
        hr { border: 0; border-top: 1px dashed #000; margin: 2mm 0; }
        .fila { display: flex; justify-content: space-between; gap: 2mm; }
        .fila span:last-child { text-align: right; white-space: nowrap; }
        .chico { font-size: 10.5px; }
        .sangria { padding-left: 3mm; }
        .total { font-size: 15px; font-weight: bold; }
        .qr { text-align: center; }
        .qr svg { width: 28mm; height: 28mm; display: block; margin: 1mm auto; }
        .acciones { text-align: center; margin: 4mm 0; }
        .acciones button { font: inherit; padding: 2mm 4mm; cursor: pointer; }
        @media print { .acciones { display: none; } }
    </style>
</head>

<body>
    <div class="nota">
        <div class="centro">
            <img class="logo" src="{{ asset('imgs/logo.png') }}" alt="Estación Apple">
            <h1>NOTA DE COMPRA</h1>
            <div class="num">Nº {{ str_pad($venta->id, 6, '0', STR_PAD_LEFT) }}</div>
        </div>

        <hr>
        <div class="centro">
            <div><strong>{{ $venta->sucursal?->nombre }}</strong></div>
            @if ($venta->sucursal?->direccion)<div class="chico">{{ $venta->sucursal->direccion }}</div>@endif
            @if ($venta->sucursal?->telefono)<div class="chico">Tel. {{ $venta->sucursal->telefono }}</div>@endif
        </div>

        <hr>
        <div class="fila"><span>Fecha</span><span>{{ $venta->created_at->format('d/m/Y H:i') }}</span></div>
        <div class="fila"><span>Cliente</span><span>{{ $venta->nombreCliente() ?? 'Sin cliente' }}</span></div>
        @if ($venta->fichaCliente?->ci)
            <div class="fila"><span>CI/NIT</span><span>{{ $venta->fichaCliente->ci }}</span></div>
        @endif
        <div class="fila"><span>Vendedor</span><span>{{ $venta->user?->name ?? '—' }}</span></div>

        <hr>
        {{-- Cada equipo con su IMEI y, debajo, lo que se vendio con el. --}}
        @foreach ($equipos as $d)
            <div>{{ $lineaNombre($d) }}</div>
            <div class="fila chico"><span>IMEI {{ $d->producto?->imei }}</span><span>{{ $bs($d->subtotal) }}</span></div>
            @foreach ($venta->detalles->where('producto_asociado_id', $d->producto_id) as $a)
                <div class="fila sangria chico">
                    <span>+ {{ $a->cantidad > 1 ? $a->cantidad . ' x ' : '' }}{{ $lineaNombre($a) }}{{ $a->esRegalo() ? ' (regalo)' : '' }}</span>
                    <span>{{ $bs($a->subtotal) }}</span>
                </div>
            @endforeach
        @endforeach
        @foreach ($sueltos as $d)
            <div class="fila">
                <span>{{ $d->cantidad > 1 ? $d->cantidad . ' x ' : '' }}{{ $lineaNombre($d) }}{{ $d->esCobro() ? ' (taller)' : '' }}</span>
                <span>{{ $bs($d->subtotal) }}</span>
            </div>
        @endforeach

        <hr>
        <div class="fila"><span>Subtotal</span><span>{{ $bs($venta->subtotal) }}</span></div>
        @if ((float) $venta->descuento > 0)
            <div class="fila"><span>Descuento</span><span>-{{ $bs($venta->descuento) }}</span></div>
        @endif
        @if ((float) $venta->mano_obra > 0)
            <div class="fila"><span>Mano de obra</span><span>{{ $bs($venta->mano_obra) }}</span></div>
        @endif
        <div class="fila total"><span>TOTAL Bs</span><span>{{ $bs($venta->total) }}</span></div>

        @if ($venta->pagos->isNotEmpty())
            <hr>
            @foreach ($venta->pagos as $pago)
                <div class="fila chico"><span>{{ $pago->descripcion() }}</span><span>{{ $bs($pago->monto) }}</span></div>
            @endforeach
        @endif
        @if ($venta->aCredito())
            <div class="fila"><strong>Saldo pendiente</strong><strong>{{ $bs($venta->saldoPendiente()) }}</strong></div>
        @endif

        @php($garantias = $equipos->filter(fn($d) => $d->garantia_meses))
        @if ($garantias->isNotEmpty())
            <hr>
            @foreach ($garantias as $d)
                <div class="chico">Garantía {{ $d->garantia_meses }} mes(es): {{ $lineaNombre($d) }}, hasta el {{ $d->garantia_fecha_exp?->format('d/m/Y') }}</div>
            @endforeach
        @endif

        {{-- El QR lleva a los terminos de garantia (/garantia): el vendedor le
             pide al cliente que lo escanee. Solo con equipos, que son lo que tiene garantia. --}}
        @if ($equipos->isNotEmpty())
            <hr>
            <div class="qr">
                {!! \App\Support\QrGarantia::svg() !!}
                <div class="chico">Escanee para ver los términos de garantía</div>
            </div>
        @endif

        <hr>
        <div class="centro"><strong>¡Gracias por su compra!</strong></div>

        <div class="acciones">
            <button type="button" onclick="window.print()">Imprimir</button>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => window.print());
    </script>
</body>

</html>
