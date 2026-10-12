<?php

namespace App\Http\Controllers;

use App\Enums\ArticuloTipo;

/**
 * Las pantallas de inventario de repuestos y accesorios. Son dos tablas
 * distintas con el mismo componente (articulo.articulo-index): el tipo lo
 * decide AQUI la ruta, no un filtro de la pantalla.
 */
class RepuestoController extends Controller
{
    public function index()
    {
        return view('app.articulo.index', ['tipo' => ArticuloTipo::Repuesto->value]);
    }

    public function accesorios()
    {
        return view('app.articulo.index', ['tipo' => ArticuloTipo::Accesorio->value]);
    }

    public function categorias()
    {
        return view('app.repuesto.categorias');
    }

    public function categoriasAccesorios()
    {
        return view('app.accesorio.categorias');
    }

    public function historial($id)
    {
        return view('app.articulo.historial', ['tipo' => ArticuloTipo::Repuesto->value, 'articulo_id' => $id]);
    }

    public function historialAccesorio($id)
    {
        return view('app.articulo.historial', ['tipo' => ArticuloTipo::Accesorio->value, 'articulo_id' => $id]);
    }
}
