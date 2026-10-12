<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Sucursal extends Model
{
    protected $table = 'sucursales';
    protected $guarded = ['id'];

    protected $casts = [
        'activa' => 'boolean',
    ];

    /**
     * La sucursal tecnica a la que vuelven los equipos reparados y de donde
     * salio todo el stock al implantarse el stock por sucursal.
     *
     * Existe como constante porque el nombre estaba escrito a mano en dos
     * modales (ReparacionEditModal y TecnicoTerminarModal). La crea
     * SucursalSeeder, y es obligatoria: sin ella la reparacion terminada no
     * tiene a donde mudarse. Por eso la pantalla de sucursales no deja
     * eliminarla, desactivarla ni renombrarla (ver esAlmacen()).
     */
    public const ALMACEN = 'Almacen';

    public function productos()
    {
        return $this->hasMany(Producto::class, 'sucursal_id');
    }

    /** El stock de repuestos y accesorios que guarda esta sucursal. */
    public function stocks()
    {
        return $this->hasMany(StockSucursal::class, 'sucursal_id');
    }

    /** El id del Almacen, o null si nadie lo creo todavia. */
    public static function almacenId(): ?int
    {
        return static::where('nombre', self::ALMACEN)->value('id');
    }

    public function esAlmacen(): bool
    {
        return $this->getOriginal('nombre', $this->nombre) === self::ALMACEN;
    }

    /**
     * Solo las que se ofrecen al CARGAR algo nuevo. Una sucursal inactiva deja
     * de aparecer en los selects de alta, pero sigue en los filtros de las
     * tablas y en los reportes: sus ventas viejas no desaparecen.
     */
    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('activa', true);
    }

    /**
     * Las opciones de un select de EDICION: las activas mas la que ya tiene el
     * registro. Sin la segunda, abrir un producto de una sucursal ya
     * desactivada mostraria el select vacio y guardar lo moveria de sucursal.
     */
    public static function paraSelect(?int $actualId = null): Collection
    {
        return static::query()
            ->where(fn($q) => $q->where('activa', true)
                ->when($actualId, fn($q) => $q->orWhere('id', $actualId)))
            ->orderBy('nombre')
            ->get();
    }

    /**
     * Cuantos registros apuntan a esta sucursal. Casi todas esas FK son
     * nullOnDelete: borrarla no fallaria, dejaria ventas y productos sin
     * sucursal sin avisar a nadie. Por eso con movimientos no se borra, se
     * desactiva (docs/01).
     */
    public function cantidadMovimientos(): int
    {
        $id = $this->id;

        return $this->productos()->count()
            + DB::table('ventas')->where('sucursal_id', $id)->count()
            + DB::table('compras')->where('sucursal_id', $id)->count()
            + DB::table('stock_sucursales')->where('sucursal_id', $id)->count()
            + DB::table('stock_bajas')->where('sucursal_id', $id)->count()
            + DB::table('productos_regalos')->where('sucursal_id', $id)->count()
            + DB::table('stock_transferencias')
                ->where(fn($q) => $q->where('sucursal_origen_id', $id)->orWhere('sucursal_destino_id', $id))
                ->count();
    }
}
