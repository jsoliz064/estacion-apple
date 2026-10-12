<?php

namespace App\Models;

use App\Enums\ArticuloTipo;
use App\Traits\ArticuloDeStockTrait;
use App\Traits\Auditable;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Database\Eloquent\Model;

/**
 * Accesorio: se vende suelto, con el telefono o se regala con el. Comparte con
 * Repuesto el stock por sucursal (ArticuloDeStockTrait), pero tiene sus propias
 * categorias, marca y modelos compatibles.
 */
class Accesorio extends Model
{
    use Auditable;
    use ArticuloDeStockTrait;
    use NormalizaCodigosTrait;

    protected $table = 'accesorios';

    /** `cantidad` es el total cacheado: lo escribe solo StockService. Ver Repuesto. */
    protected $guarded = ['id', 'cantidad'];

    protected $auditarExcluye = ['cantidad'];


    public static function tipoArticulo(): ArticuloTipo
    {
        return ArticuloTipo::Accesorio;
    }

    public function categoria()
    {
        return $this->belongsTo(AccesorioCategoria::class, 'accesorio_categoria_id');
    }

    /** Los modelos de telefono con los que es compatible (una funda, un vidrio). */
    public function modelosCompatibles()
    {
        return $this->belongsToMany(ProductoModelo::class, 'accesorios_modelos', 'accesorio_id', 'producto_modelo_id');
    }

    public function regalos()
    {
        return $this->hasMany(ProductoRegalo::class, 'accesorio_id');
    }
}
