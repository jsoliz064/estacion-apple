<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Un metodo de pago (Efectivo, QR...). Uno con pagos no se borra: se
 * desactiva, y deja de ofrecerse al cobrar. Los filtros siguen con todos.
 *
 * Los de `sistema` (hoy solo «Permuta») los usa el codigo: no se ofrecen al
 * cobrar a mano, ni se editan ni se borran. Se buscan por nombre, como el
 * Almacen.
 */
class MetodoPago extends Model
{
    public const PERMUTA = 'Permuta';

    protected $table = 'metodos_pago';
    protected $guarded = ['id'];

    protected $casts = [
        'activo' => 'boolean',
        'sistema' => 'boolean',
    ];

    public static function permutaId(): ?int
    {
        return static::where('nombre', self::PERMUTA)->where('sistema', true)->value('id');
    }

    public function pagos()
    {
        return $this->hasMany(VentaPago::class, 'metodo_pago_id');
    }

    /** Los que se ofrecen al cobrar a mano: activos y no de sistema. */
    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true)->where('sistema', false)->orderBy('orden')->orderBy('nombre');
    }
}
