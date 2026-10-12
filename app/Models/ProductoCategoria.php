<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoCategoria extends Model
{
    protected $table = 'productos_categorias';
    protected $guarded = ['id'];

    public function productoMarca()
    {
        return $this->belongsTo(ProductoMarca::class, 'producto_marca_id');
    }
}
