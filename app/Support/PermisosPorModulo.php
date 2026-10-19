<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Agrupa los permisos de spatie por modulo para la pantalla de roles. Antes
 * eran 113 casillas en una lista plana y no habia forma de encontrar nada.
 *
 * El grupo es el prefijo antes del primer punto ('compra.edit' -> 'compra').
 * Un prefijo que no este en MODULOS no se pierde: sale con su prefijo como
 * titulo, asi un permiso que se agregue manana aparece igual.
 */
class PermisosPorModulo
{
    /** Prefijo -> nombre que ve el usuario. */
    public const MODULOS = [
        'accesorio' => 'Accesorios',
        'accesorio-categoria' => 'Categorías de accesorios',
        'cliente' => 'Clientes',
        'cobranza' => 'Cobranzas',
        'codigo' => 'Códigos de barras',
        'comision' => 'Comisiones',
        'compra' => 'Compras',
        'cuenta-pagar' => 'Cuentas por pagar',
        'dashboard' => 'Inicio',
        'metodo-pago' => 'Métodos de pago',
        'pago' => 'Cobros de ventas',
        'pago-proveedor' => 'Pagos a proveedores',
        'producto' => 'Productos',
        'producto.estado' => 'Estados del equipo',
        'producto-categoria' => 'Categorías de productos',
        'producto-marca' => 'Marcas',
        'producto-modelo' => 'Modelos',
        'proveedor' => 'Proveedores',
        'reporte' => 'Reportes',
        'repuesto' => 'Repuestos',
        'repuesto-categoria' => 'Categorías de repuestos',
        'reserva' => 'Reservas',
        'rol' => 'Roles',
        'sucursal' => 'Sucursales',
        'tecnico' => 'Técnicos',
        'user' => 'Usuarios',
        'venta' => 'Ventas',
    ];

    /**
     * La clave del grupo de un permiso. Los producto.estado.* van aparte:
     * son los que filtran el selector de estado, y entre los de Productos se
     * perdian.
     */
    public static function clave(string $permiso): string
    {
        if (str_starts_with($permiso, 'producto.estado.')) {
            return 'producto.estado';
        }

        return Str::before($permiso, '.');
    }

    public static function nombre(string $clave): string
    {
        return self::MODULOS[$clave] ?? Str::ucfirst($clave);
    }

    /**
     * @param  Collection  $permisos  modelos Permission (o cualquier cosa con ->name)
     * @return Collection<string, array{nombre: string, permisos: Collection}>  ordenado por nombre del grupo
     */
    public static function agrupar(Collection $permisos): Collection
    {
        return $permisos
            ->groupBy(fn($p) => self::clave($p->name))
            ->map(fn($grupo, $clave) => [
                'nombre' => self::nombre($clave),
                'permisos' => $grupo->sortBy('name')->values(),
            ])
            ->sortBy('nombre', SORT_NATURAL | SORT_FLAG_CASE);
    }
}
