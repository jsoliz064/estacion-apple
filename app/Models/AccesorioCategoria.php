<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccesorioCategoria extends Model
{
    protected $table = 'accesorios_categorias';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function accesorios()
    {
        return $this->hasMany(Accesorio::class, 'accesorio_categoria_id');
    }
}
