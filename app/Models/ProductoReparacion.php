<?php

namespace App\Models;

use App\Observers\ProductoReparacionObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

/**
 * El observer mantiene la comision del tecnico: la reparacion se crea, se
 * termina y se edita desde varias pantallas, y engancharse en cada una era la
 * copia que diverge. Por eso esta tabla no se escribe con el query builder.
 */
#[ObservedBy(ProductoReparacionObserver::class)]
class ProductoReparacion extends Model
{
    protected $table = 'productos_reparaciones';
    protected $guarded = ['id'];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnicos::class, 'tecnico_id');
    }

    public function repuestos()
    {
        return $this->hasMany(ProductoReparacionRepuesto::class, 'producto_reparacion_id');
    }

    /** La comision de su tecnico (ComisionService). */
    public function comision()
    {
        return $this->hasOne(Comision::class, 'producto_reparacion_id');
    }
}
