<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proveedor extends Model
{
    protected $table = 'proveedores';
    protected $guarded = ['id'];

    public function compra()
    {
        return $this->compras();
    }

    public function compras()
    {
        return $this->hasMany(Compra::class, 'proveedor_id');
    }

    public function reclamos()
    {
        return $this->hasManyThrough(CompraReclamo::class, Compra::class, 'proveedor_id', 'compra_id');
    }

    /** Lo que se le debe: la suma de los saldos de sus compras. */
    public function deuda(): float
    {
        return round((float) $this->compras()->conSaldo()->sum('saldo'), 2);
    }
}
