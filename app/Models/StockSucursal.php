<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * El stock de un repuesto O de un accesorio en una sucursal. La UNICA verdad
 * del stock: repuestos.cantidad y accesorios.cantidad son su suma cacheada.
 *
 * NO se escribe desde los componentes: el unico camino es StockService.
 */
class StockSucursal extends Model
{
    protected $table = 'stock_sucursales';
    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
        'minimo' => 'integer',
    ];

    /** En su minimo o por debajo (con un minimo fijado). */
    public function estaPorAgotarse(): bool
    {
        return $this->minimo > 0 && $this->cantidad <= $this->minimo;
    }

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
}
