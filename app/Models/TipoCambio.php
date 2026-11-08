<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * El tipo de cambio del dia, en Bs por USD. Lo escribe solo TipoCambioService.
 *
 * Es la unica fuente del TC que se propone: antes cada pantalla buscaba el
 * ultimo que encontraba (el del ultimo equipo en USD, el del ultimo cobro en
 * USD, o un 6.96 fijo) y cada una proponia uno distinto.
 */
class TipoCambio extends Model
{
    protected $table = 'tipos_cambio';

    protected $guarded = ['id'];

    protected $casts = [
        'valor' => 'float',
    ];

    /** Si nunca se cargo uno (base recien sembrada sin seeder). */
    public const RESPALDO = 6.96;

    /** Una vez por peticion: se lee en cada celda de una tabla con Bs. */
    private static ?self $vigente = null;

    private static bool $leido = false;

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public static function vigente(): ?self
    {
        if (!self::$leido) {
            self::$vigente = self::with('user:id,name')->latest('id')->first();
            self::$leido = true;
        }

        return self::$vigente;
    }

    public static function actual(): float
    {
        return self::vigente()?->valor ?? self::RESPALDO;
    }

    public static function actualizadoHoy(): bool
    {
        return (bool) self::vigente()?->created_at?->isToday();
    }

    /** Tras escribir uno nuevo, la proxima lectura va a la base. */
    public static function olvidar(): void
    {
        self::$vigente = null;
        self::$leido = false;
    }
}
