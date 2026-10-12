<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index()
    {
        return view('app.producto.index');
    }

    public function productoHistorial($producto_id)
    {
        return view('app.producto.historial-index', compact('producto_id'));
    }
}
