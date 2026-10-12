<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductoModeloController extends Controller
{
    public function index()
    {
        return view('app.producto-modelo.index');
    }
}
