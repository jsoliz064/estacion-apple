<?php

namespace App\Http\Controllers;

use App\Models\Compra;

/**
 * Compras: una sola para equipos, repuestos y accesorios. Crear y editar son
 * paginas (CompraForm); el detalle es donde se cargan los equipos.
 */
class CompraController extends Controller
{
    public function index()
    {
        return view('app.compra.index');
    }

    public function create()
    {
        return view('app.compra.form', ['compraId' => null]);
    }

    public function edit($id)
    {
        return view('app.compra.form', ['compraId' => Compra::findOrFail($id)->id]);
    }

    public function show($id)
    {
        $compra = Compra::findOrFail($id);

        return view('app.compra-lote.index', compact('compra'));
    }
}
