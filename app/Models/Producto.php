<?php

namespace App\Models;

use App\Enums\Moneda;
use App\Enums\ProductoEstado;
use App\Enums\ReparacionTipo;
use App\Traits\Auditable;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use Auditable;
    use NormalizaCodigosTrait;

    protected $table = 'productos';
    protected $guarded = ['id'];

    protected $casts = [
        'dado_de_baja_at' => 'datetime',
    ];

    /**
     * El costo en USD se convierte a Bs AQUI, al guardar, y no en cada pantalla:
     * `costo_unidad` es lo que leen la linea de compra, el costo congelado de la
     * venta y los reportes, y no puede contradecir a los dolares de los que sale.
     * Asi el alta, la edicion y el reemplazo de un reclamo dan el mismo numero.
     * Quien cambia el costo llama despues a recalcularCosto() (costo_total).
     */
    protected static function booted(): void
    {
        static::saving(function (Producto $producto) {
            $moneda = $producto->getAttribute('costo_moneda');

            if ($moneda === Moneda::USD->value) {
                $producto->costo_unidad = round((float) $producto->costo_moneda_monto * (float) $producto->costo_tipo_cambio, 2);
            } elseif ($moneda === Moneda::BOB->value) {
                $producto->costo_moneda_monto = null;
                $producto->costo_tipo_cambio = null;
            }
        });
    }

    /**
     * El tipo de cambio que se propone para un costo en USD: el del ultimo
     * equipo cargado en dolares y, si no hay, el de los cobros en USD.
     */
    public static function tipoCambioSugerido(): float
    {
        $ultimo = self::whereNotNull('costo_tipo_cambio')->latest('id')->value('costo_tipo_cambio');

        // 2 decimales: el de un pago en USD admite 4, y el input del costo no.
        return round($ultimo ? (float) $ultimo : \App\Services\PagoService::ultimoTipoCambio(), 2);
    }

    /** El costo como se cargo: «USD 300,00 × 6,96» o «Bs 2.088,00». */
    public function costoEnMoneda(): string
    {
        if ($this->costo_moneda === Moneda::USD->value) {
            return 'USD ' . number_format((float) $this->costo_moneda_monto, 2, ',', '.')
                . ' × ' . number_format((float) $this->costo_tipo_cambio, 2, ',', '.');
        }

        return 'Bs ' . number_format((float) $this->costo_unidad, 2, ',', '.');
    }

    /**
     * Busca por IMEI ordenando por relevancia.
     *
     * Los clientes suelen escribir solo los últimos dígitos del IMEI, así que las
     * coincidencias por sufijo van primero.
     *
     * Orden: exacto → termina en → empieza con → contiene.
     *
     * El SKU entra solo EXACTO: es lo que lee la pistola en un equipo con
     * etiqueta interna, y un LIKE sobre el mezclaria codigos ajenos.
     */
    public function scopeBuscarPorImei(Builder $query, ?string $termino): Builder
    {
        $termino = trim((string) $termino);

        if ($termino === '') {
            return $query;
        }

        // Neutraliza los comodines de LIKE para que un '%' o '_' escrito por el
        // usuario se busque literalmente.
        $patron = addcslashes($termino, '%_\\');

        return $query
            ->where(fn($q) => $q
                ->where('imei', 'like', '%' . $patron . '%')
                ->orWhere('sku', $termino))
            ->orderByRaw(
                'CASE
                    WHEN imei = ? OR sku = ? THEN 0
                    WHEN imei LIKE ? THEN 1
                    WHEN imei LIKE ? THEN 2
                    ELSE 3
                END',
                [$termino, $termino, '%' . $patron, $patron . '%']
            )
            ->orderBy('imei');
    }

    /**
     * Equipos que se pueden vender: en un estado disponible y NO dados de baja.
     * Es el unico sitio de la pregunta "se puede vender?".
     */
    public function scopeDisponibles(Builder $query): Builder
    {
        return $query->whereIn('productos.estado', ProductoEstado::disponibles())
            ->whereNull('productos.dado_de_baja_at');
    }

    /** Los que no estan dados de baja: lo que muestran los listados por defecto. */
    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('productos.dado_de_baja_at');
    }

    public function scopeDadosDeBaja(Builder $query): Builder
    {
        return $query->whereNotNull('productos.dado_de_baja_at');
    }

    /** La misma pregunta, sobre una fila ya cargada. */
    public function estaDisponible(): bool
    {
        return in_array($this->estado, ProductoEstado::disponibles(), true)
            && !$this->estaDadoDeBaja();
    }

    public function estaDadoDeBaja(): bool
    {
        return $this->dado_de_baja_at !== null;
    }

    public function imagenes()
    {
        // Por id: la primera es la de la miniatura de las tablas (MIN(id)).
        return $this->hasMany(ProductoImagen::class)->orderBy('id');
    }

    public function reparaciones()
    {
        return $this->hasMany(ProductoReparacion::class, 'producto_id');
    }

    public function modelo()
    {
        return $this->belongsTo(ProductoModelo::class, 'producto_modelo_id');
    }

    /** La linea de compra que trajo el equipo (no hay compra_id: es esta). */
    public function compraDetalle()
    {
        return $this->hasOne(CompraDetalle::class, 'producto_id');
    }

    public function compra()
    {
        return $this->hasOneThrough(Compra::class, CompraDetalle::class, 'producto_id', 'id', 'id', 'compra_id');
    }

    /**
     * El pago de permuta con el que entro este equipo, si no vino de una
     * compra: es su origen y su costo (docs/03, permuta).
     */
    public function permuta()
    {
        return $this->hasOne(VentaPago::class, 'producto_id');
    }

    /** El reclamo abierto al proveedor, si esta en estado Reclamo. */
    public function reclamoAbierto()
    {
        return $this->hasOne(CompraReclamo::class, 'producto_id')->where('estado', \App\Enums\ReclamoEstado::Abierto->value);
    }

    public function reservas()
    {
        return $this->hasMany(Reserva::class, 'producto_id');
    }

    /** La reserva activa, si esta en estado Reserva. */
    public function reservaActiva()
    {
        return $this->hasOne(Reserva::class, 'producto_id')->where('estado', \App\Enums\ReservaEstado::Activa->value);
    }

    /** La linea de venta, si esta vendido (un telefono, una sola venta). */
    public function ventaDetalle()
    {
        return $this->hasOne(VentaDetalle::class, 'producto_id');
    }

    public function regalos()
    {
        return $this->hasMany(ProductoRegalo::class, 'producto_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function bajaUser()
    {
        return $this->belongsTo(User::class, 'baja_user_id');
    }

    public function ultimaReparacion()
    {
        return ProductoReparacion::where('producto_id', $this->id)->orderBy('id', 'desc')->first();
    }

    public function ultimaReparacionPendiente()
    {
        return ProductoReparacion::where('producto_id', $this->id)->where('estado', 'Pendiente')->orderBy('id', 'desc')->first();
    }

    /**
     * costo_total = costo_unidad + costo_regalos + costo_reparacion, en Bs.
     * Las dos ultimas son cacheados que solo escribe este metodo.
     */
    public function recalcularCosto()
    {
        // El trabajo externo lo paga el cliente: no es costo de inventario.
        // La exclusion va aqui y no en quien llama: si solo se omitiera la
        // llamada desde el modal, la siguiente edicion del tecnico desde
        // ReparacionEditModal volveria a sumarlo.
        $costoReparacion = $this->reparaciones()
            ->where('tipo', '!=', ReparacionTipo::Externo->value)
            ->sum('costo_total');

        $costoRegalos = $this->regalos()->sum('subtotal_costo');

        $this->costo_reparacion = round((float) $costoReparacion, 2);
        $this->costo_regalos = round((float) $costoRegalos, 2);
        $this->costo_total = round((float) $this->costo_unidad + $this->costo_regalos + $this->costo_reparacion, 2);
        $this->save();
    }
}
