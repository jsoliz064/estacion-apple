<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoModeloAlmacenamiento extends Model
{
    protected $table = 'productos_modelos_almacenamientos';
    protected $guarded = ['id'];
    public $timestamps = false;

    public function productoModelo()
    {
        return $this->belongsTo(ProductoModelo::class, 'producto_modelo_id');
    }
}
