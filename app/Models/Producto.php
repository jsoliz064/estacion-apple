<?php

namespace App\Models;

use App\Enums\ProductoEstado;
use App\Enums\ReparacionTipo;
use App\Traits\Auditable;
use App\Traits\MontosEnMonedaTrait;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Producto extends Model
{
    use Auditable;
    use MontosEnMonedaTrait;
    use NormalizaCodigosTrait;

    protected $table = 'productos';
    protected $guarded = ['id'];

    protected $casts = [
        'dado_de_baja_at' => 'datetime',
    ];

    /**
     * Costo y precio cargados en Bs se pasan a USD al guardar
     * (MontosEnMonedaTrait). Quien cambia el costo llama despues a
     * recalcularCosto() (costo_total).
     */
    public static function columnasEnMoneda(): array
    {
        return ['costo_unidad' => 'costo_moneda_monto', 'precio' => 'precio_moneda_monto'];
    }

    /**
     * Busca por IMEI ordenando por relevancia.
     *
     * Los clientes suelen escribir solo los últimos dígitos del IMEI, así que las
     * coincidencias por sufijo van primero.
     *
     * Orden: exacto → termina en → empieza con → contiene.
     *
     * El UPC (serial) entra solo EXACTO: es lo que lee la pistola en la caja o
     * en el sticker de Codigos de barras, y un LIKE sobre el mezclaria codigos
     * ajenos.
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
                ->orWhere('upc', $termino))
            ->orderByRaw(
                'CASE
                    WHEN imei = ? OR upc = ? THEN 0
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

    /**
     * Lo que publica el catalogo: vendible (disponibles()) Y con la casilla
     * «Mostrar en el catalogo publico» marcada. Es la unica regla del catalogo
     * (listado, chips y detalle). Antes era `casilla OR Inventario`: la casilla
     * no ocultaba nada, y el equipo recibido en permuta salia a precio de
     * costo. La casilla nace marcada en el alta y desmarcada en la permuta.
     */
    public function scopeEnCatalogo(Builder $query): Builder
    {
        return $query->disponibles()->where('productos.disponible_catalogo', true);
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
     * costo_total = costo_unidad + costo_regalos + costo_reparacion, en USD.
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
