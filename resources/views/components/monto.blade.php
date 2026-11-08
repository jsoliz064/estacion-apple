{{-- Un monto: USD, y debajo su equivalente en Bs. Un documento (venta, compra)
     pasa SU tipo de cambio congelado; sin :tc es el del dia. sinBs omite la
     segunda linea (tablas densas). --}}
@props(['valor' => 0, 'tc' => null, 'sinBs' => false])

<span {{ $attributes->class('inline-flex flex-col leading-tight') }}>
    <span class="whitespace-nowrap">{{ \App\Support\Dinero::usd($valor) }}</span>
    @unless ($sinBs)
        <span class="whitespace-nowrap text-xs font-normal opacity-70">{{ \App\Support\Dinero::bs($valor, $tc) }}</span>
    @endunless
</span>
