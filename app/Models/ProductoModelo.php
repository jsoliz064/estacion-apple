<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductoModelo extends Model
{
    protected $table = 'productos_modelos';
    protected $guarded = ['id'];

    public function productoCategoria()
    {
        return $this->belongsTo(ProductoCategoria::class, 'producto_categoria_id');
    }

    public function productos()
    {
        return $this->hasMany(Producto::class, 'producto_modelo_id');
    }

    public function almacenamientos()
    {
        return $this->hasMany(ProductoModeloAlmacenamiento::class, 'producto_modelo_id');
    }

    /** Los accesorios marcados como compatibles con este modelo. */
    public function accesoriosCompatibles()
    {
        return $this->belongsToMany(Accesorio::class, 'accesorios_modelos', 'producto_modelo_id', 'accesorio_id');
    }
}
