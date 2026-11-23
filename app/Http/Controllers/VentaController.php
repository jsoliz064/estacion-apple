<?php

namespace App\Http\Controllers;

use App\Enums\Moneda;
use App\Models\TipoCambio;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Support\Dinero;
use Barryvdh\DomPDF\Facade\Pdf;

/** Ventas: una sola para equipos, repuestos y accesorios. */
class VentaController extends Controller
{
    public function index()
    {
        return view('app.venta.index');
    }

    public function detalles($id)
    {
        $venta = Venta::findOrFail($id);

        return view('app.venta.detalles', compact('venta'));
    }

    /**
     * La nota de venta para la termica de 80 mm: HTML suelto, sin layout, que
     * se imprime solo al abrirse. Lo comprado se agrupa con su equipo.
     */
    public function nota($id)
    {
        return view('app.venta.nota', $this->cargarRecibo($id));
    }

    /**
     * El recibo en PDF, hoja carta: el que se comparte por WhatsApp desde el
     * detalle. inline para que el navegador lo muestre; el boton lo descarga.
     */
    public function pdf($id)
    {
        $datos = $this->cargarRecibo($id);
        $nombre = 'Recibo-' . str_pad($datos['venta']->id, 6, '0', STR_PAD_LEFT) . '.pdf';

        // Con el subconjunto de la fuente: DejaVu entera pesaba casi 1 MB por
        // recibo, mucho para mandarlo por WhatsApp.
        return Pdf::loadView('app.venta.recibo-pdf', $datos)
            ->setPaper('letter')
            ->setOption('isFontSubsettingEnabled', true)
            ->stream($nombre);
    }

    /**
     * La venta con lo que pintan la nota y el PDF, y el agrupado de lo vendido
     * bajo su equipo. Uno solo para los dos: el agrupado copiado divergia.
     */
    private function cargarRecibo($id): array
    {
        $venta = Venta::with([
            'sucursal', 'user', 'fichaCliente',
            'detalles' => fn($q) => $q->orderBy('id'),
            'detalles.producto.modelo', 'detalles.repuesto', 'detalles.accesorio',
            'pagos' => fn($q) => $q->orderBy('id'),
            'pagos.metodo', 'pagos.producto.modelo',
        ])->findOrFail($id);

        $equipos = $venta->detalles->whereNotNull('producto_id');
        $sueltos = $venta->detalles->whereNull('producto_id')
            ->filter(fn($d) => !$d->producto_asociado_id || !$equipos->contains('producto_id', $d->producto_asociado_id));

        return compact('venta', 'equipos', 'sueltos') + $this->montosEnBs($venta);
    }

    /**
     * El recibo es todo en Bs, sin USD ni tipo de cambio (pedido de la tienda:
     * es lo que se le dice y se le cobra al cliente). Al TC CONGELADO de la
     * venta, no al del dia. Todo se sigue guardando en USD: esto es la vista.
     *
     *  - $bs($usd): una linea (precio, descuento, subtotal), formateada.
     *  - $totalesBs: el total es el de la venta en Bs (el que mostro el
     *    formulario al vender y el que se cobro), y el descuento es la
     *    diferencia, para que «Subtotal - Descuento + Mano de obra = TOTAL»
     *    cuadre al centavo: convertido por su lado, un descuento cargado como
     *    200 Bs salia 200.03.
     *  - $pagoBs($pago): un pago en Bs muestra lo que se escribio
     *    (monto_moneda); uno en USD, la permuta o la seña, su USD en Bs.
     */
    private function montosEnBs(Venta $venta): array
    {
        $tc = (float) $venta->tipo_cambio ?: TipoCambio::actual();
        $subtotal = Dinero::aBs($venta->subtotal, $tc);
        $manoObra = Dinero::aBs($venta->mano_obra, $tc);
        $total = Dinero::aBs($venta->total, $tc);

        return [
            'bs' => fn($usd) => number_format(Dinero::aBs((float) $usd, $tc), 2),
            'totalesBs' => [
                'subtotal' => $subtotal,
                'mano_obra' => $manoObra,
                'total' => $total,
                'descuento' => round($subtotal + $manoObra - $total, 2),
                'saldo' => $venta->saldoPendiente() > 0 ? Dinero::aBs($venta->saldoPendiente(), $tc) : 0.0,
            ],
            'pagoBs' => fn(VentaPago $pago) => $pago->moneda === Moneda::alterna() && $pago->monto_moneda !== null
                ? round((float) $pago->monto_moneda, 2)
                : Dinero::aBs($pago->monto, $tc),
        ];
    }

    public function create()
    {
        return view('app.venta.create');
    }

    public function editar($id)
    {
        // findOrFail: con un id inexistente, un 404 limpio y no un error de Livewire.
        $venta = Venta::findOrFail($id);

        return view('app.venta.edit', compact('venta'));
    }
}
