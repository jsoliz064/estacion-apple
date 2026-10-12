<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RepuestoCategoria extends Model
{
    protected $table = 'repuestos_categorias';
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $fillable = [
        'nombre',
        'descripcion',
    ];
}
