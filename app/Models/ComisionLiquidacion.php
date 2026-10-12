<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** El pago de comisiones a una persona por un periodo. Lo escribe SOLO ComisionService. */
class ComisionLiquidacion extends Model
{
    protected $table = 'comisiones_liquidaciones';

    protected $fillable = [
        'user_id',
        'tecnico_id',
        'desde',
        'hasta',
        'total',
        'cantidad',
        'nota',
        'pagado_por',
        'clave_idempotencia',
    ];

    protected $casts = [
        'desde' => 'date',
        'hasta' => 'date',
        'total' => 'decimal:2',
    ];

    public function comisiones()
    {
        return $this->hasMany(Comision::class, 'liquidacion_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function tecnico()
    {
        return $this->belongsTo(Tecnicos::class, 'tecnico_id');
    }

    public function pagadoPor()
    {
        return $this->belongsTo(User::class, 'pagado_por');
    }

    public function beneficiarioNombre(): string
    {
        return $this->user_id ? ($this->user?->name ?? '—') : ($this->tecnico?->nombre ?? '—');
    }

    public function beneficiarioClave(): string
    {
        return $this->user_id ? 'U-' . $this->user_id : 'T-' . $this->tecnico_id;
    }
}
