<?php

namespace App\Models;

use App\Enums\ArticuloTipo;
use App\Traits\ArticuloDeStockTrait;
use App\Traits\Auditable;
use App\Traits\MontosEnMonedaTrait;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Pieza de reparacion. Los accesorios son otro modelo (Accesorio); los dos
 * comparten el stock por sucursal a traves de ArticuloDeStockTrait.
 */
class Repuesto extends Model
{
    use Auditable;
    use ArticuloDeStockTrait;
    use MontosEnMonedaTrait;
    use NormalizaCodigosTrait;

    protected $table = 'repuestos';

    /**
     * `cantidad` va en $guarded: es un total CACHEADO, espejo de la suma de
     * stock_sucursales, y lo escribe solo StockService. Se blinda aqui y no
     * solo quitando el input, porque los modales de edicion hacen toArray()
     * y luego update(): la clave seguiria en el array. Se descarta EN SILENCIO,
     * como la trampa de $fillable: si un stock "no se guarda", mirar aqui.
     */
    protected $guarded = ['id', 'cantidad'];

    /**
     * `cantidad` tampoco entra en la bitacora: recalcularTotales() la reescribe
     * despues de cada movimiento y llenaria el historial con el eco de lo que
     * MovimientoStock ya cuenta.
     */
    protected $auditarExcluye = ['cantidad'];

    /** Costo y precio cargados en Bs se pasan a USD al guardar (MontosEnMonedaTrait). */
    public static function columnasEnMoneda(): array
    {
        return ['costo' => 'costo_moneda_monto', 'precio' => 'precio_moneda_monto'];
    }

    public static function tipoArticulo(): ArticuloTipo
    {
        return ArticuloTipo::Repuesto;
    }

    public function modelo()
    {
        return $this->belongsTo(ProductoModelo::class, 'producto_modelo_id');
    }

    public function categoria()
    {
        return $this->belongsTo(RepuestoCategoria::class, 'repuesto_categoria_id');
    }
}
