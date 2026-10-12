<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use LogicException;

/**
 * Una fila por hecho, sobre cualquier modelo.
 *
 * Es INMUTABLE: los hooks de abajo impiden editarla y borrarla, igual que los
 * candados de MovimientoStock. No es decoracion -- la tabla a
 * la que sustituye se editaba (ProductoEstadoModal sobrescribia la descripcion
 * de una fila anterior, perdiendo el texto para siempre) y se borraba
 * (RepuestosDeReparacionService::cancelarCobros). Un historial que se puede
 * reescribir no sirve para lo unico que sirve un historial.
 *
 * Quien escribe aqui es BitacoraObserver, y por la via directa registrar().
 */
class Bitacora extends Model
{
    protected $table = 'bitacoras';

    protected $guarded = ['id'];

    /** Sin updated_at: una fila nace y no se vuelve a tocar. */
    public const UPDATED_AT = null;

    protected $casts = [
        'cambios' => 'array',
        'created_at' => 'datetime',
    ];

    /** Los enlaces de contexto que acepta registrar() y anotar(). */
    public const ENLACES = [
        'venta_id',
        'compra_id',
        'producto_reparacion_id',
        'repuesto_id',
        'accesorio_id',
        'sucursal_id',
    ];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('La bitacora es inmutable: una fila ya escrita no se modifica.');
        });

        static::deleting(function () {
            throw new LogicException('La bitacora es inmutable: una fila ya escrita no se borra. Si un hecho se deshizo, se registra el hecho contrario.');
        });
    }

    /**
     * Escribe una fila suelta, para hechos que NO son un cambio de modelo.
     *
     * Los cambios de modelo los captura BitacoraObserver solo; esta via es para
     * lo que no pasa por un save(): una reparacion que termina sin mover el
     * estado, o un ajuste de stock -- que StockService hace con SQL
     * crudo (DB::statement / DB::update), asi que ningun observer lo ve.
     */
    public static function registrar(
        Model $modelo,
        string $evento,
        ?string $descripcion = null,
        array $enlaces = [],
        ?array $cambios = null,
    ): self {
        return self::create([
            'auditable_type' => $modelo->getMorphClass(),
            'auditable_id' => $modelo->getKey(),
            'evento' => $evento,
            'descripcion' => $descripcion,
            'cambios' => $cambios,
            // Puede ser null y es correcto: un seeder, un comando o un job no
            // tienen sesion.
            'user_id' => Auth::id(),
        ] + self::soloEnlaces($enlaces));
    }

    /**
     * Los tipos de sujeto que la bitacora conoce: [clase => etiqueta].
     *
     * Es la LISTA BLANCA de las pantallas: el tipo llega como parametro de
     * montaje y un `auditable_type` arbitrario no puede colarse a un where.
     */
    public const TIPOS = [
        Producto::class => 'Teléfono',
        Repuesto::class => 'Repuesto',
        Accesorio::class => 'Accesorio',
        Venta::class => 'Venta',
        Compra::class => 'Compra',
        Cliente::class => 'Cliente',
        User::class => 'Usuario',
    ];

    /**
     * Sobre QUE es el hecho, legible y con enlace a su ficha cuando la hay.
     *
     * Necesita `auditable` cargado (with('auditable')): un morphTo sin eager
     * load es una consulta por fila. Si el sujeto se borro, auditable es null y
     * se dice asi -- la fila sobrevive a proposito, sin FK.
     *
     * @return array{etiqueta: string, url: ?string}
     */
    public function sujeto(): array
    {
        $tipo = self::TIPOS[$this->auditable_type] ?? class_basename($this->auditable_type);
        $modelo = $this->auditable;
        $id = $this->auditable_id;

        if ($modelo === null) {
            return ['etiqueta' => "{$tipo} #{$id} (eliminado)", 'url' => null];
        }

        return match ($this->auditable_type) {
            Producto::class => ['etiqueta' => "Teléfono IMEI {$modelo->imei}", 'url' => route('productos.historial', $id)],
            Repuesto::class => ['etiqueta' => $modelo->nombre, 'url' => route('repuestos.historial', $id)],
            Accesorio::class => ['etiqueta' => $modelo->nombre, 'url' => route('accesorios.historial', $id)],
            Venta::class => ['etiqueta' => "Venta #{$id}", 'url' => route('ventas.detalles', $id)],
            Compra::class => ['etiqueta' => "Compra #{$id}", 'url' => route('compras.detalle', $id)],
            Cliente::class => ['etiqueta' => "Cliente {$modelo->nombre}", 'url' => route('clientes.historial', $id)],
            User::class => ['etiqueta' => "Usuario {$modelo->name}", 'url' => route('users.historial', $id)],
            default => ['etiqueta' => "{$tipo} #{$id}", 'url' => null],
        };
    }

    /** Descarta cualquier clave que no sea uno de los seis enlaces. */
    public static function soloEnlaces(array $enlaces): array
    {
        return array_intersect_key($enlaces, array_flip(self::ENLACES));
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class, 'venta_id');
    }

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function accesorio()
    {
        return $this->belongsTo(Accesorio::class, 'accesorio_id');
    }

    public function reparacion()
    {
        return $this->belongsTo(ProductoReparacion::class, 'producto_reparacion_id');
    }

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }
}
