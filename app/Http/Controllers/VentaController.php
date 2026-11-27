<?php

namespace App\Http\Controllers;

use App\Enums\Moneda;
use App\Models\Venta;
use App\Models\VentaDetalle;
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
     *  - $precioBs($linea) / $subtotalBs($linea): lo escrito en Bs si la linea
     *    se cargo en Bs (precio_bs), o su USD al TC. $bs($usd): el resto (el
     *    descuento por linea de ventas viejas). Formateados.
     *  - $totalesBs: los de Venta::importes() (MontosVentaBs), la misma cuenta
     *    del formulario: el TOTAL es el que se le dijo y se le cobro al cliente
     *    («te lo dejo en 11500 Bs» sale 11500.00, no 11499.96) y el recuadro
     *    «Subtotal - Descuento + Mano de obra = TOTAL» cuadra al centavo.
     *  - $pagoBs($pago): un pago en Bs muestra lo que se escribio
     *    (monto_moneda); uno en USD, la permuta o la seña, su USD en Bs.
     */
    private function montosEnBs(Venta $venta): array
    {
        $tc = $venta->tcDeBs();
        $importes = $venta->importes();
        $pagoBs = fn(VentaPago $pago) => $pago->moneda === Moneda::alterna() && $pago->monto_moneda !== null
            ? round((float) $pago->monto_moneda, 2)
            : Dinero::aBs($pago->monto, $tc);

        // Lo que falta en Bs: el total en Bs menos lo pagado en Bs. Solo si la
        // venta tiene saldo de verdad (en USD, la que manda).
        $saldo = $venta->saldoPendiente() > 0
            ? max(0, round($importes['total_bs'] - $venta->pagos->sum(fn($p) => $pagoBs($p)), 2))
            : 0.0;

        return [
            'bs' => fn($usd) => number_format(Dinero::aBs((float) $usd, $tc), 2),
            'precioBs' => fn(VentaDetalle $d) => number_format($d->precioBs($tc), 2),
            'subtotalBs' => fn(VentaDetalle $d) => number_format($d->subtotalBs($tc), 2),
            'totalesBs' => [
                'subtotal' => $importes['subtotal_bs'],
                'mano_obra' => $importes['mano_obra_bs'],
                'total' => $importes['total_bs'],
                'descuento' => $importes['descuento_bs'],
                'saldo' => $saldo,
            ],
            'pagoBs' => $pagoBs,
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
