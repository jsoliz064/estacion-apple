<?php

namespace App\Models;

use App\Enums\ArticuloTipo;
use App\Traits\ArticuloDeStockTrait;
use App\Traits\Auditable;
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

    /**
     * Circulo de color para la datatable. Estilo inline obligatorio: no hay
     * safelist en tailwind.config.js.
     */
    public function getDivColor(): string
    {
        $style = $this->color_hex
            ? "background-color: {$this->color_hex};"
            : 'background-color: transparent;';

        return '<div class="w-6 h-6 rounded-full border border-gray-300 mx-auto" style="' . $style . '" title="' . e($this->color ?? 'Sin color') . '"></div>';
    }

    /**
     * Nombre y color en una sola celda: punto pintado + nombre del color entre
     * parentesis. Sin color se devuelve solo el nombre.
     */
    public function getNombreConColor(): string
    {
        $nombre = e($this->nombre);

        if (!$this->color && !$this->color_hex) {
            return $nombre;
        }

        $hex = $this->color_hex ?: 'transparent';
        $etiqueta = $this->color ?: $this->color_hex;

        return '<span class="inline-flex items-center gap-1.5">'
            . '<span class="inline-block w-3 h-3 rounded-full border border-gray-300 shrink-0" style="background-color: ' . e($hex) . ';"></span>'
            . '<span>' . $nombre . ' <span class="text-gray-500 dark:text-gray-400">(' . e($etiqueta) . ')</span></span>'
            . '</span>';
    }
}
