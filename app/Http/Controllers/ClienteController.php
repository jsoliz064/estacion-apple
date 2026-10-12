<?php

namespace App\Http\Controllers;

class ClienteController extends Controller
{
    public function index()
    {
        return view('app.cliente.index');
    }

    public function historial($cliente_id)
    {
        return view('app.cliente.historial-index', compact('cliente_id'));
    }
}
