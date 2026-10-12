<?php

namespace App\Models;

use App\Enums\Moneda;
use App\Enums\PagoMomento;
use Illuminate\Database\Eloquent\Model;

/**
 * Un pago de una venta: al venderla o en un cobro posterior. Lo escribe y lo
 * anula SOLO PagoService, que mantiene ventas.pagado.
 *
 * Sin Auditable: un pago es un hecho que no se edita. Su alta y su anulacion
 * quedan en la bitacora de la VENTA (eventos pago / pago-anulado), junto al
 * cambio de `pagado` que provocan.
 */
class VentaPago extends Model
{
    protected $table = 'ventas_pagos';

    protected $fillable = [
        'venta_id',
        'metodo_pago_id',
        'monto',
        'moneda',
        'monto_moneda',
        'tipo_cambio',
        'producto_id',
        'momento',
        'fecha',
        'nota',
        'user_id',
        'clave_idempotencia',
    ];

    protected $casts = [
        'fecha' => 'datetime',
        'momento' => PagoMomento::class,
        'moneda' => Moneda::class,
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function metodo()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** El equipo recibido en permuta, si este pago es una permuta. */
    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function esPermuta(): bool
    {
        return $this->producto_id !== null;
    }

    public function esSena(): bool
    {
        return $this->momento === PagoMomento::Sena;
    }

    /**
     * Como se lee el pago en el detalle y en la nota: «Efectivo», «QR · USD 100
     * a 6,96», «Permuta · iPhone 11 IMEI ...». Necesita metodo y producto.modelo.
     */
    public function descripcion(): string
    {
        if ($this->esPermuta()) {
            $p = $this->producto;

            return 'Permuta' . ($p ? ' · ' . trim(($p->modelo?->nombre ?? 'Equipo') . ' ' . $p->almacenamiento) . ' IMEI ' . $p->imei : '');
        }

        $texto = $this->metodo?->nombre ?? 'Pago';

        if ($this->moneda === Moneda::USD) {
            $texto .= ' · USD ' . number_format((float) $this->monto_moneda, 2) . ' a ' . rtrim(rtrim(number_format((float) $this->tipo_cambio, 4), '0'), '.');
        }

        if ($this->esSena()) {
            $texto .= ' · seña de la reserva';
        }

        return $texto;
    }
}
