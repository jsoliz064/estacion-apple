{{-- El recibo en PDF, hoja carta (VentaController@pdf, dompdf). Es el que se
     comparte por WhatsApp; la termica usa nota.blade.php. Solo tablas y CSS
     simple: dompdf no entiende flex ni grid. DejaVu Sans por las tildes y la ñ.
     Comprobante interno, sin datos fiscales, igual que la nota. --}}
@php
    $bs = fn($n) => number_format((float) $n, 2, ',', '.');
    $lineaNombre = fn($d) => $d->producto_id
        ? trim(($d->producto?->modelo?->nombre ?? 'Equipo') . ' ' . ($d->producto?->almacenamiento ?? '') . ' ' . ($d->producto?->color ?? ''))
        : ($d->articulo()?->nombre ?? 'Artículo');
    $numero = str_pad($venta->id, 6, '0', STR_PAD_LEFT);
    $logo = public_path('imgs/logo.png');
@endphp
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="utf-8">
    <title>Recibo {{ $numero }}</title>
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10.5px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        .cab td { vertical-align: top; }
        .titulo { font-size: 20px; font-weight: bold; letter-spacing: 2px; color: #2f4a2b; }
        .num { font-size: 13px; font-weight: bold; }
        .gris { color: #555; }
        .chico { font-size: 9px; }
        .caja { border: 1px solid #bbb; padding: 6px 8px; }
        .detalle th { background: #e8efe5; color: #2f4a2b; text-align: left; padding: 5px 6px; border-bottom: 1px solid #9bb394; font-size: 10px; }
        .detalle td { padding: 5px 6px; border-bottom: 1px solid #e5e5e5; vertical-align: top; }
        .der { text-align: right; white-space: nowrap; }
        .centro { text-align: center; }
        .sangria { padding-left: 18px !important; color: #333; }
        .totales td { padding: 3px 6px; }
        .total td { font-size: 14px; font-weight: bold; border-top: 2px solid #2f4a2b; padding-top: 6px; }
        .firma { border-top: 1px solid #111; width: 60%; margin: 40px auto 0; padding-top: 4px; text-align: center; }
    </style>
</head>

<body>
    {{-- Encabezado: logo y sucursal a la izquierda, numero a la derecha. --}}
    <table class="cab">
        <tr>
            <td style="width: 55%;">
                @if (is_file($logo))
                    <img src="{{ $logo }}" alt="Estación Apple" style="height: 60px;">
                @endif
                <div style="margin-top: 6px;"><strong>{{ $venta->sucursal?->nombre }}</strong></div>
                @if ($venta->sucursal?->direccion)<div class="gris">{{ $venta->sucursal->direccion }}</div>@endif
                @if ($venta->sucursal?->telefono)<div class="gris">Tel. {{ $venta->sucursal->telefono }}</div>@endif
            </td>
            <td class="der" style="width: 45%;">
                <div class="titulo">RECIBO</div>
                <div class="num">Nº {{ $numero }}</div>
                <div class="gris" style="margin-top: 4px;">{{ $venta->created_at->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table style="margin-top: 12px;">
        <tr>
            <td class="caja" style="width: 60%;">
                <span class="gris">Cliente:</span> <strong>{{ $venta->nombreCliente() ?? 'Sin cliente' }}</strong>
                @if ($venta->fichaCliente?->ci)
                    <br><span class="gris">CI/NIT:</span> {{ $venta->fichaCliente->ci }}
                @endif
                @if ($venta->fichaCliente?->telefono)
                    <br><span class="gris">Teléfono:</span> {{ $venta->fichaCliente->telefono }}
                @endif
            </td>
            <td class="caja" style="width: 40%;">
                <span class="gris">Vendedor:</span> {{ $venta->user?->name ?? '—' }}
            </td>
        </tr>
    </table>

    {{-- Cada equipo con su IMEI y su garantia; debajo, lo que se vendio con el. --}}
    <table class="detalle" style="margin-top: 14px;">
        <thead>
            <tr>
                <th style="width: 8%;" class="centro">Cant.</th>
                <th>Descripción</th>
                <th style="width: 14%;" class="der">Precio Bs</th>
                <th style="width: 12%;" class="der">Desc. Bs</th>
                <th style="width: 15%;" class="der">Subtotal Bs</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($equipos as $d)
                <tr>
                    <td class="centro">1</td>
                    <td>
                        <strong>{{ $lineaNombre($d) }}</strong>
                        <div class="chico gris">IMEI {{ $d->producto?->imei }}</div>
                        @if ($d->garantia_meses)
                            <div class="chico gris">Garantía {{ $d->garantia_meses }} mes(es), hasta el {{ $d->garantia_fecha_exp?->format('d/m/Y') }}</div>
                        @endif
                    </td>
                    <td class="der">{{ $bs($d->precio) }}</td>
                    <td class="der">{{ (float) $d->descuento > 0 ? $bs($d->descuento) : '' }}</td>
                    <td class="der">{{ $bs($d->subtotal) }}</td>
                </tr>
                @foreach ($venta->detalles->where('producto_asociado_id', $d->producto_id) as $a)
                    <tr>
                        <td class="centro">{{ $a->cantidad }}</td>
                        <td class="sangria">+ {{ $lineaNombre($a) }}{{ $a->esRegalo() ? ' (regalo)' : '' }}</td>
                        <td class="der">{{ $bs($a->precio) }}</td>
                        <td class="der">{{ (float) $a->descuento > 0 ? $bs($a->descuento) : '' }}</td>
                        <td class="der">{{ $bs($a->subtotal) }}</td>
                    </tr>
                @endforeach
            @endforeach
            @foreach ($sueltos as $d)
                <tr>
                    <td class="centro">{{ $d->cantidad }}</td>
                    <td>{{ $lineaNombre($d) }}{{ $d->esCobro() ? ' (taller)' : '' }}</td>
                    <td class="der">{{ $bs($d->precio) }}</td>
                    <td class="der">{{ (float) $d->descuento > 0 ? $bs($d->descuento) : '' }}</td>
                    <td class="der">{{ $bs($d->subtotal) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Pagos a la izquierda, totales a la derecha. --}}
    <table style="margin-top: 10px;">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                @if ($venta->pagos->isNotEmpty())
                    <div class="gris" style="margin-bottom: 3px;"><strong>Pagos</strong></div>
                    <table class="totales">
                        @foreach ($venta->pagos as $pago)
                            <tr>
                                <td>{{ $pago->descripcion() }}</td>
                                <td class="der">{{ $bs($pago->monto) }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top;">
                <table class="totales">
                    <tr><td>Subtotal</td><td class="der">{{ $bs($venta->subtotal) }}</td></tr>
                    @if ((float) $venta->descuento > 0)
                        <tr><td>Descuento</td><td class="der">-{{ $bs($venta->descuento) }}</td></tr>
                    @endif
                    @if ((float) $venta->mano_obra > 0)
                        <tr><td>Mano de obra</td><td class="der">{{ $bs($venta->mano_obra) }}</td></tr>
                    @endif
                    <tr class="total"><td>TOTAL Bs</td><td class="der">{{ $bs($venta->total) }}</td></tr>
                    @if ($venta->aCredito())
                        <tr><td><strong>Saldo pendiente</strong></td><td class="der"><strong>{{ $bs($venta->saldoPendiente()) }}</strong></td></tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- El QR lleva a los terminos de garantia (/garantia), solo si hay equipos. --}}
    <table style="margin-top: 24px;">
        <tr>
            <td style="width: 50%; vertical-align: bottom;">
                @if ($equipos->isNotEmpty())
                    <img src="{{ \App\Support\QrGarantia::dataUri(110) }}" alt="QR garantía" style="width: 95px; height: 95px;">
                    <div class="chico gris">Escanee para ver los<br>términos de garantía</div>
                @endif
            </td>
            <td style="width: 50%; vertical-align: bottom;">
                <div class="firma">Conforme</div>
            </td>
        </tr>
    </table>

    <p class="centro" style="margin-top: 24px;"><strong>¡Gracias por su compra!</strong></p>
</body>

</html>
