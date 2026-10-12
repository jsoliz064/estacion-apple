<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un accesorio entregado de regalo con un equipo. Lo escribe solo
 * ProductoRegalosService: sale del stock y su costo va al costo del equipo.
 */
class ProductoRegalo extends Model
{
    protected $table = 'productos_regalos';
    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
        'costo' => 'decimal:2',
        'subtotal_costo' => 'decimal:2',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function accesorio()
    {
        return $this->belongsTo(Accesorio::class, 'accesorio_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
