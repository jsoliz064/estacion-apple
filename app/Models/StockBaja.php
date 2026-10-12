<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Unidades de un repuesto o accesorio dadas de baja. Las escribe BajaService. */
class StockBaja extends Model
{
    protected $table = 'stock_bajas';
    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
        'costo' => 'decimal:2',
    ];

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
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
