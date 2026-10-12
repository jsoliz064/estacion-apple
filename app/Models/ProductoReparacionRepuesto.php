<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoReparacionRepuesto extends Model
{
    protected $table = 'productos_reparaciones_repuestos';
    protected $guarded = ['id'];

     public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function reparacion()
    {
        return $this->belongsTo(ProductoReparacion::class, 'producto_reparacion_id');
    }
}
