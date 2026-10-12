<?php

namespace App\Http\Controllers;

class ComisionController extends Controller
{
    public function index()
    {
        return view('app.comision.index');
    }

    public function mias()
    {
        return view('app.comision.mias');
    }
}
