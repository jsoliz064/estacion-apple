<?php

namespace App\Models;

use App\Enums\LineaTipo;
use Illuminate\Database\Eloquent\Model;

/**
 * Una linea de compra: un equipo (cantidad 1), un repuesto o un accesorio. La
 * escribe solo CompraService. `tipo` y `articulo_clave` son columnas GENERADAS:
 * NO van en $fillable (escribirlas da el error 3105).
 */
class CompraDetalle extends Model
{
    protected $table = 'compras_detalles';

    protected $fillable = [
        'compra_id',
        'producto_id',
        'repuesto_id',
        'accesorio_id',
        'sucursal_id',
        'cantidad',
        'costo',
        'subtotal',
        'estado_destino',
    ];

    protected $casts = [
        'cantidad' => 'integer',
    ];

    public function compra()
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }

    public function repuesto()
    {
        return $this->belongsTo(Repuesto::class, 'repuesto_id');
    }

    public function accesorio()
    {
        return $this->belongsTo(Accesorio::class, 'accesorio_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function tipoLinea(): LineaTipo
    {
        return LineaTipo::from($this->tipo);
    }

    public function articulo(): ?Model
    {
        return $this->producto ?? $this->repuesto ?? $this->accesorio;
    }
}
