<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class TecnicosController extends Controller
{
    public function index()
    {
        return view('app.tecnico.index');
    }

    public function tecnicosProductos($tecnico_id)
    {
        return view('app.tecnico.productos', compact('tecnico_id'));
    }
}
