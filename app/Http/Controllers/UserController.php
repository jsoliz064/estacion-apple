<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        return view('app.user.index');
    }

    public function historial($user_id)
    {
        return view('app.user.historial-index', compact('user_id'));
    }
}
