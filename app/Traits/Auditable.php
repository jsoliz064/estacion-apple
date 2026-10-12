<?php

namespace App\Traits;

use App\Models\Bitacora;
use App\Observers\BitacoraObserver;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Pone un modelo bajo la bitacora. Con `use Auditable;` basta: el observer se
 * registra solo desde bootAuditable().
 *
 * El problema que resuelve anotar(): si el observer escribiera por su cuenta y
 * ademas el servicio dejara su frase, cada venta dejaria DOS filas -- la del
 * negocio y el diff del estado. Aqui el observer es el unico escritor, y quien
 * tiene contexto se lo pasa justo antes de guardar:
 *
 *     $producto->anotar('Vendido', "Vendido. Venta #65, Daniel, $ 915.40", ['venta_id' => 65]);
 *     $producto->update(['estado' => 'Vendido']);
 *
 * Sale UNA fila con el evento, la frase y {"estado": ["Inventario", "Vendido"]}.
 * Sin anotar() previo, el observer escribe 'editado' con el diff a secas, que es
 * lo que cubre las diecisiete columnas de un telefono que hasta ahora cambiaban
 * sin dejar rastro.
 */
trait Auditable
{
    /** Lo anotado para el PROXIMO save. Lo consume y lo limpia el observer. */
    protected ?array $bitacoraPendiente = null;

    public static function bootAuditable(): void
    {
        static::observe(BitacoraObserver::class);
    }

    public function bitacoras(): MorphMany
    {
        return $this->morphMany(Bitacora::class, 'auditable');
    }

    /**
     * Declara que es el guardado que viene, para que el observer lo escriba con
     * su nombre en vez de como un 'editado' anonimo.
     */
    public function anotar(string $evento, ?string $descripcion = null, array $enlaces = []): static
    {
        $this->bitacoraPendiente = [
            'evento' => $evento,
            'descripcion' => $descripcion,
            'enlaces' => Bitacora::soloEnlaces($enlaces),
        ];

        return $this;
    }

    /** Lo lee el observer: devuelve lo anotado y lo descarta de una vez. */
    public function consumirBitacoraPendiente(): ?array
    {
        $pendiente = $this->bitacoraPendiente;
        $this->bitacoraPendiente = null;

        return $pendiente;
    }

    /**
     * Columnas que la bitacora NO registra nunca.
     *
     * Sin esta lista la tabla se llena de ruido: `updated_at` cambia en cada
     * save, y `repuestos.cantidad` es un total cacheado que recalcularTotales()
     * reescribe despues de cada operacion de stock. Un modelo puede ampliarla
     * con $auditarExcluye.
     */
    public function columnasNoAuditadas(): array
    {
        return array_merge([
            'created_at',
            'updated_at',
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
            'clave_idempotencia',
        ], property_exists($this, 'auditarExcluye') ? $this->auditarExcluye : []);
    }
}
