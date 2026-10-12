<?php

namespace App\Models;

use App\Enums\ReservaEstado;
use App\Enums\SenaDestino;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un equipo apartado por un cliente con una seña. La escribe SOLO
 * ReservaService. `producto_activo` es GENERADA (una sola reserva activa por
 * equipo): no va en $fillable.
 */
class Reserva extends Model
{
    protected $table = 'reservas';

    protected $fillable = [
        'producto_id',
        'cliente_id',
        'sena',
        'metodo_pago_id',
        'estado',
        'sena_destino',
        'venta_id',
        'nota',
        'user_id',
        'cerrada_por',
        'cerrada_at',
        'clave_idempotencia',
    ];

    protected $casts = [
        'estado' => ReservaEstado::class,
        'sena_destino' => SenaDestino::class,
        'cerrada_at' => 'datetime',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function metodo()
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaActiva(): bool
    {
        return $this->estado === ReservaEstado::Activa;
    }

    public function scopeActivas(Builder $query): Builder
    {
        return $query->where('reservas.estado', ReservaEstado::Activa->value);
    }
}
