<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Una transferencia de stock entre sucursales, de un repuesto o un accesorio.
 * La escribe StockService::transferir(). MovimientoStock lee cada fila como DOS
 * movimientos (Salida en el origen, Entrada en el destino): neta cero.
 */
class StockTransferencia extends Model
{
    protected $table = 'stock_transferencias';
    protected $guarded = ['id'];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function accesorio()
    {
        return $this->belongsTo(Accesorio::class, 'accesorio_id');
    }

    public function origen()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function destino()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
