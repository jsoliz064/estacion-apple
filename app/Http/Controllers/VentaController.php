<?php

namespace App\Http\Controllers;

use App\Models\Venta;
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

        return compact('venta', 'equipos', 'sueltos');
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
