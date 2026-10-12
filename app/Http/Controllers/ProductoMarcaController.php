<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductoMarcaController extends Controller
{
    public function index()
    {
        return view('app.producto-marca.index');
    }
}
