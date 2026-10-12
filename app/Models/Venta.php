<?php

namespace App\Models;

use App\Enums\LineaTipo;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Una venta: equipos, repuestos y accesorios en un solo documento, en Bs. Lo
 * vendido vive en ventas_detalles, que escribe solo VentaService; lo cobrado,
 * en ventas_pagos, que escribe solo PagoService.
 *
 * `saldo` es una columna GENERADA (total - pagado): no va en $fillable, y no
 * se refresca en el modelo hasta releerlo.
 */
class Venta extends Model
{
    use Auditable;

    protected $table = 'ventas';
    protected $guarded = ['id', 'created_at', 'updated_at'];

    /**
     * OJO: con $guarded y $fillable a la vez gana $fillable, y una columna que
     * falte aqui se descarta en el create() SIN ERROR. Ya paso con mano_obra, y
     * sin clave_idempotencia toda la idempotencia quedaria inerte.
     */
    protected $fillable = [
        'subtotal',
        'descuento',
        'mano_obra',
        'total',
        'costo_total',
        'cliente',
        'cliente_id',
        'user_id',
        'sucursal_id',
        'clave_idempotencia',
        'pagado',
        'pagada_at',
    ];

    protected $casts = [
        'pagada_at' => 'datetime',
    ];

    /**
     * La ficha del cliente. Se llama fichaCliente y NO cliente a proposito:
     * `cliente` es una COLUMNA de esta tabla (el nombre congelado), y una
     * relacion con ese nombre quedaria tapada por el atributo.
     */
    public function fichaCliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * El nombre del cliente para mostrar. LA REGLA DE LECTURA, EN UN SOLO SITIO:
     * la ficha primero y el texto congelado como respaldo.
     */
    public function nombreCliente(): ?string
    {
        return $this->fichaCliente?->nombre ?? $this->cliente;
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class, 'venta_id');
    }

    /** Las lineas de equipo. */
    public function detallesProductos()
    {
        return $this->detalles()->where('tipo', LineaTipo::Producto->value);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function pagos()
    {
        return $this->hasMany(VentaPago::class, 'venta_id');
    }

    /** La comision de su vendedor (ComisionService). */
    public function comision()
    {
        return $this->hasOne(Comision::class, 'venta_id');
    }

    /** Lo que falta cobrar, sin depender de que `saldo` (generada) este releida. */
    public function saldoPendiente(): float
    {
        return round(max(0, (float) $this->total - (float) $this->pagado), 2);
    }

    public function aCredito(): bool
    {
        return $this->saldoPendiente() > 0;
    }

    public function estaPagada(): bool
    {
        return !$this->aCredito();
    }

    /** Las que tienen algo por cobrar: la pantalla de cobranzas y la deuda del cliente. */
    public function scopeConSaldo(Builder $query): Builder
    {
        return $query->where('ventas.saldo', '>', 0);
    }

    public function ganancia(): float
    {
        return round((float) $this->total - (float) $this->costo_total, 2);
    }

    /**
     * Recalcula los totales DESDE LA BASE, nunca desde el formulario:
     *   subtotal    = SUM(lineas.subtotal)
     *   total       = subtotal - descuento + mano_obra
     *   costo_total = SUM(lineas.subtotal_costo) + mano_obra
     * mano_obra suma al total Y al costo para que se cancele en la ganancia.
     */
    public function recalcularTotales(): void
    {
        $sumas = $this->detalles()
            ->selectRaw('COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(subtotal_costo), 0) as costo')
            ->first();

        $manoObra = (float) $this->mano_obra;

        $subtotal = round((float) $sumas->subtotal, 2);
        $total = round($subtotal - (float) $this->descuento + $manoObra, 2);

        // Un solo sitio para "no se puede bajar el total por debajo de lo ya
        // cobrado": editar, quitar una linea, descontar... Sin esto, el CHECK
        // ventas_pagado_rango lo impediria con un error de SQL ilegible.
        if ($total < (float) $this->pagado) {
            throw ValidationException::withMessages([
                'detalles' => 'La venta #' . $this->id . ' ya tiene Bs ' . number_format((float) $this->pagado, 2)
                    . ' cobrados y el total quedaría en Bs ' . number_format($total, 2)
                    . '. Anula un pago antes de bajar el total.',
            ]);
        }

        $this->subtotal = $subtotal;
        $this->total = $total;
        $this->costo_total = round((float) $sumas->costo + $manoObra, 2);
        $this->save();
    }
}
