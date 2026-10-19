<?php

namespace App\Http\Controllers;

use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index()
    {
        return view('app.role.index');
    }

    /** Crear y editar comparten la pantalla completa de permisos (RoleForm). */
    public function create()
    {
        return view('app.role.form', ['roleId' => null]);
    }

    public function edit(int $id)
    {
        return view('app.role.form', ['roleId' => Role::findOrFail($id)->id]);
    }
}
