<?php

namespace App\Http\Controllers;

use App\Models\CodigoBarras;
use App\Models\CodigoLote;
use Illuminate\Http\Request;

class CodigoBarrasController extends Controller
{
    /**
     * Los tamanos de etiqueta: la unica lista (el modal y la impresion la leen).
     * [ancho mm, alto mm] y un alto null es papel continuo (la impresora de
     * recibos con rollo adhesivo): la pagina crece con el contenido y los
     * stickers se cortan a mano.
     */
    public const TAMANOS = [
        '50x25' => ['nombre' => '50 × 25 mm (etiqueta)', 'ancho' => 50, 'alto' => 25],
        '40x25' => ['nombre' => '40 × 25 mm (etiqueta)', 'ancho' => 40, 'alto' => 25],
        '58' => ['nombre' => '58 mm continuo (rollo de recibos adhesivo)', 'ancho' => 58, 'alto' => null],
    ];

    public const TAMANO_POR_DEFECTO = '50x25';

    /** [clave => nombre], para el :options de x-select (que ignora su contenido). */
    public static function opcionesTamano(): array
    {
        return array_map(fn($t) => $t['nombre'], self::TAMANOS);
    }

    public function index()
    {
        return view('app.codigo.index');
    }

    /**
     * La hoja de stickers de un lote, para la impresora de etiquetas: HTML
     * suelto que se imprime solo, como la nota de venta. Cada codigo sale
     * tantas veces como copias tenga el lote.
     */
    public function imprimir(Request $request, int $id)
    {
        $lote = CodigoLote::findOrFail($id);

        $clave = array_key_exists($request->query('tam'), self::TAMANOS) ? $request->query('tam') : self::TAMANO_POR_DEFECTO;
        $soloLibres = $request->boolean('libres');

        $codigos = $lote->codigos()->orderBy('secuencia')->pluck('codigo');

        if ($soloLibres) {
            $asignados = CodigoBarras::duenos($codigos->all())->keys();
            $codigos = $codigos->diff($asignados)->values();
        }

        return view('app.codigo.imprimir', [
            'lote' => $lote,
            'codigos' => $codigos,
            'tamano' => self::TAMANOS[$clave],
            'soloLibres' => $soloLibres,
        ]);
    }
}
