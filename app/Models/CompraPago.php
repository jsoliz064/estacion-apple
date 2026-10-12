<?php

namespace App\Models;

use App\Enums\Moneda;
use Illuminate\Database\Eloquent\Model;

/**
 * Un pago al proveedor por una compra. Lo escribe y lo anula SOLO
 * PagoProveedorService. Sin Auditable: su alta y su anulacion quedan en la
 * bitacora de la COMPRA, junto al cambio de `pagado`.
 */
class CompraPago extends Model
{
    protected $table = 'compras_pagos';

    protected $fillable = [
        'compra_id',
        'metodo_pago_id',
        'monto',
        'moneda',
        'monto_moneda',
        'tipo_cambio',
        'al_recibir',
        'fecha',
        'nota',
        'user_id',
        'clave_idempotencia',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'moneda' => Moneda::class,
        'al_recibir' => 'boolean',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function metodo()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** «Transferencia», «Efectivo · USD 100 a 6,96». */
    public function descripcion(): string
    {
        $texto = $this->metodo?->nombre ?? 'Pago';

        if ($this->moneda === Moneda::USD) {
            $texto .= ' · USD ' . number_format((float) $this->monto_moneda, 2) . ' a ' . rtrim(rtrim(number_format((float) $this->tipo_cambio, 4), '0'), '.');
        }

        return $texto . ($this->al_recibir ? ' · al recibir' : '');
    }
}
