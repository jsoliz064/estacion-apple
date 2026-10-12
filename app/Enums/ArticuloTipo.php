<?php

namespace App\Enums;

use App\Models\Accesorio;
use App\Models\Repuesto;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Los dos articulos con stock por cantidad: repuestos y accesorios. Son tablas
 * distintas pero comparten el stock por sucursal, las transferencias, las bajas
 * y el historial de movimientos.
 *
 * Es LA UNICA fuente de los nombres de tabla y columna que StockService y
 * MovimientoStock interpolan en SQL crudo: nunca llegan desde la pantalla, solo
 * desde un case de este enum, asi que no hay inyeccion posible.
 */
enum ArticuloTipo: string
{
    case Repuesto = 'Repuesto';
    case Accesorio = 'Accesorio';

    public function label(): string
    {
        return $this->value;
    }

    public function plural(): string
    {
        return match ($this) {
            self::Repuesto => 'Repuestos',
            self::Accesorio => 'Accesorios',
        };
    }

    /** La columna de FK en las tablas de stock y en las lineas. */
    public function columna(): string
    {
        return match ($this) {
            self::Repuesto => 'repuesto_id',
            self::Accesorio => 'accesorio_id',
        };
    }

    /** La tabla del articulo, que guarda el total cacheado `cantidad`. */
    public function tabla(): string
    {
        return match ($this) {
            self::Repuesto => 'repuestos',
            self::Accesorio => 'accesorios',
        };
    }

    /** @return class-string<Model> */
    public function modelo(): string
    {
        return match ($this) {
            self::Repuesto => Repuesto::class,
            self::Accesorio => Accesorio::class,
        };
    }

    public function buscar(int $id): ?Model
    {
        return ($this->modelo())::find($id);
    }

    /** Prefijo de permiso: repuesto.* o accesorio.* */
    public function permiso(): string
    {
        return match ($this) {
            self::Repuesto => 'repuesto',
            self::Accesorio => 'accesorio',
        };
    }

    /** Nombre de la ruta del listado. */
    public function ruta(): string
    {
        return match ($this) {
            self::Repuesto => 'repuestos',
            self::Accesorio => 'accesorios',
        };
    }

    /** Nombre de la ruta del historial de movimientos. */
    public function rutaHistorial(): string
    {
        return match ($this) {
            self::Repuesto => 'repuestos.historial',
            self::Accesorio => 'accesorios.historial',
        };
    }

    public function lineaTipo(): LineaTipo
    {
        return match ($this) {
            self::Repuesto => LineaTipo::Repuesto,
            self::Accesorio => LineaTipo::Accesorio,
        };
    }

    /** Clases LITERALES en cada rama: Tailwind no tiene safelist. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Repuesto => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
            self::Accesorio => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        };
    }

    /** @return array<int,string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function toSelectArray(): Collection
    {
        return collect(self::cases())->mapWithKeys(fn($c) => [$c->value => $c->label()]);
    }
}
