<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index()
    {
        return view('app.proveedor.index');
    }

    public function historial($proveedor_id)
    {
        return view('app.proveedor.historial', compact('proveedor_id'));
    }
}
