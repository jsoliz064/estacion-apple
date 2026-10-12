<?php

namespace App\Http\Controllers;

/**
 * Los reportes (docs/09): el general y los cuatro nuevos, cada uno con su
 * pantalla y su permiso. REPORTES es la unica lista: la usan las pestanas de
 * navegacion y el item del menu.
 */
class ReporteController extends Controller
{
    /** nombre de ruta => [permiso, titulo] */
    public const REPORTES = [
        'reporte' => ['reporte.index', 'General'],
        'reportes.vendedores' => ['reporte.vendedores', 'Vendedores'],
        'reportes.productos' => ['reporte.productos', 'Productos'],
        'reportes.inventario' => ['reporte.inventario', 'Inventario'],
        'reportes.clientes' => ['reporte.clientes', 'Clientes'],
    ];

    /** La primera ruta de reporte que el usuario puede abrir, o null. */
    public static function primeraRuta(): ?string
    {
        $user = auth()->user();

        foreach (self::REPORTES as $ruta => [$permiso]) {
            if ($user?->can($permiso)) {
                return $ruta;
            }
        }

        return null;
    }

    public function index()
    {
        return view('app.reporte.index');
    }

    public function vendedores()
    {
        return view('app.reporte.vendedores');
    }

    public function productos()
    {
        return view('app.reporte.productos');
    }

    public function inventario()
    {
        return view('app.reporte.inventario');
    }

    public function clientes()
    {
        return view('app.reporte.clientes');
    }
}
