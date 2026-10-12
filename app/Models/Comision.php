<?php

namespace App\Models;

use App\Enums\ComisionEstado;
use App\Enums\ComisionOrigen;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * La comision de una venta (vendedor) o de una reparacion (tecnico). La escribe
 * SOLO ComisionService. El estado se deriva de `ganada_at` y `liquidacion_id`
 * (ComisionEstado); los scopes son la unica traduccion a SQL de esa regla.
 */
class Comision extends Model
{
    protected $table = 'comisiones';

    protected $fillable = [
        'origen',
        'user_id',
        'tecnico_id',
        'venta_id',
        'producto_reparacion_id',
        'referencia',
        'base',
        'porcentaje',
        'monto',
        'ganada_at',
        'liquidacion_id',
    ];

    protected $casts = [
        'origen' => ComisionOrigen::class,
        'base' => 'decimal:2',
        'porcentaje' => 'decimal:2',
        'monto' => 'decimal:2',
        'ganada_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnicos::class, 'tecnico_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function reparacion()
    {
        return $this->belongsTo(ProductoReparacion::class, 'producto_reparacion_id');
    }

    public function liquidacion()
    {
        return $this->belongsTo(ComisionLiquidacion::class, 'liquidacion_id');
    }

    public function estado(): ComisionEstado
    {
        return match (true) {
            $this->liquidacion_id !== null => ComisionEstado::Pagada,
            $this->ganada_at !== null => ComisionEstado::PorPagar,
            default => ComisionEstado::Pendiente,
        };
    }

    public function estaLiquidada(): bool
    {
        return $this->liquidacion_id !== null;
    }

    public function beneficiarioNombre(): string
    {
        return $this->user_id ? ($this->user?->name ?? '—') : ($this->tecnico?->nombre ?? '—');
    }

    /** Clave de la persona: U-5 (vendedor) o T-3 (tecnico). */
    public function beneficiarioClave(): string
    {
        return $this->user_id ? 'U-' . $this->user_id : 'T-' . $this->tecnico_id;
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereNull('comisiones.ganada_at');
    }

    public function scopePorPagar(Builder $query): Builder
    {
        return $query->whereNotNull('comisiones.ganada_at')->whereNull('comisiones.liquidacion_id');
    }

    public function scopePagadas(Builder $query): Builder
    {
        return $query->whereNotNull('comisiones.liquidacion_id');
    }

    public function scopeConEstado(Builder $query, ComisionEstado|string $estado): Builder
    {
        $estado = $estado instanceof ComisionEstado ? $estado : ComisionEstado::tryFrom($estado);

        return match ($estado) {
            ComisionEstado::Pendiente => $query->pendientes(),
            ComisionEstado::PorPagar => $query->porPagar(),
            ComisionEstado::Pagada => $query->pagadas(),
            default => $query,
        };
    }

    /** Filtra por la clave U-5 / T-3. Una clave mal formada no devuelve nada. */
    public function scopeDeBeneficiario(Builder $query, string $clave): Builder
    {
        [$tipo, $id] = self::partirClave($clave);

        return match ($tipo) {
            'U' => $query->where('comisiones.user_id', $id),
            'T' => $query->where('comisiones.tecnico_id', $id),
            default => $query->whereRaw('1 = 0'),
        };
    }

    /** Lo de un usuario: sus ventas y las reparaciones del tecnico vinculado a el. */
    public function scopeDeUsuario(Builder $query, User $user): Builder
    {
        $tecnicoId = Tecnicos::where('user_id', $user->id)->value('id');

        return $query->where(fn($q) => $q->where('comisiones.user_id', $user->id)
            ->when($tecnicoId, fn($w) => $w->orWhere('comisiones.tecnico_id', $tecnicoId)));
    }

    /**
     * Las tres cifras de docs/05 sobre una consulta ya filtrada (una persona,
     * todas): pendiente, por pagar y pagado. Un solo SELECT.
     *
     * @return array{pendiente: float, por_pagar: float, pagado: float}
     */
    public static function cifras(Builder $query): array
    {
        $fila = $query->toBase()->selectRaw('
            COALESCE(SUM(CASE WHEN comisiones.ganada_at IS NULL THEN comisiones.monto END), 0) AS pendiente,
            COALESCE(SUM(CASE WHEN comisiones.ganada_at IS NOT NULL AND comisiones.liquidacion_id IS NULL THEN comisiones.monto END), 0) AS por_pagar,
            COALESCE(SUM(CASE WHEN comisiones.liquidacion_id IS NOT NULL THEN comisiones.monto END), 0) AS pagado')
            ->first();

        return [
            'pendiente' => round((float) $fila->pendiente, 2),
            'por_pagar' => round((float) $fila->por_pagar, 2),
            'pagado' => round((float) $fila->pagado, 2),
        ];
    }

    /** @return array{0: ?string, 1: int} ['U'|'T'|null, id] */
    public static function partirClave(?string $clave): array
    {
        if (!$clave || !preg_match('/^([UT])-(\d+)$/', $clave, $m)) {
            return [null, 0];
        }

        return [$m[1], (int) $m[2]];
    }
}
