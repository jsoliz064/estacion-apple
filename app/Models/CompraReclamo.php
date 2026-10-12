<?php

namespace App\Models;

use App\Enums\ReclamoEstado;
use App\Enums\ReclamoResolucion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un equipo fallado reclamado al proveedor. Lo escribe SOLO ReclamoService.
 * `producto_abierto` es GENERADA: no va en $fillable.
 */
class CompraReclamo extends Model
{
    protected $table = 'compras_reclamos';

    protected $fillable = [
        'compra_id',
        'producto_id',
        'motivo',
        'estado',
        'resolucion',
        'producto_reemplazo_id',
        'nota_cierre',
        'user_id',
        'cerrado_por',
        'cerrado_at',
    ];

    protected $casts = [
        'estado' => ReclamoEstado::class,
        'resolucion' => ReclamoResolucion::class,
        'cerrado_at' => 'datetime',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function reemplazo()
    {
        return $this->belongsTo(Producto::class, 'producto_reemplazo_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === ReclamoEstado::Abierto;
    }

    public function scopeAbiertos(Builder $query): Builder
    {
        return $query->where('compras_reclamos.estado', ReclamoEstado::Abierto->value);
    }
}
