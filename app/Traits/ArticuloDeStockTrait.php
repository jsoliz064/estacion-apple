<?php

namespace App\Traits;

use App\Enums\ArticuloTipo;
use App\Models\StockBaja;
use App\Models\StockSucursal;
use App\Models\StockTransferencia;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lo comun a los dos articulos con stock por cantidad: Repuesto y Accesorio.
 * Comparten stock_sucursales, stock_transferencias y stock_bajas, cada uno por
 * su propia columna (repuesto_id / accesorio_id), que resuelve ArticuloTipo.
 *
 * El modelo que lo usa declara tipoArticulo().
 */
trait ArticuloDeStockTrait
{
    abstract public static function tipoArticulo(): ArticuloTipo;

    /** El stock repartido por sucursal: la unica verdad del inventario. */
    public function stocks()
    {
        return $this->hasMany(StockSucursal::class, static::tipoArticulo()->columna());
    }

    public function transferencias()
    {
        return $this->hasMany(StockTransferencia::class, static::tipoArticulo()->columna());
    }

    public function bajas()
    {
        return $this->hasMany(StockBaja::class, static::tipoArticulo()->columna());
    }

    /**
     * Articulos por agotarse en ALGUNA sucursal: una fila de stock con minimo
     * fijado y la cantidad en el minimo o por debajo. Antes era un umbral fijo
     * de 9 sobre el TOTAL, que no avisaba si una tienda se quedaba sin nada
     * mientras el Almacen tenia.
     *
     * Alias propio y columnas cualificadas: este scope se anida como
     * subconsulta dentro de los withSum del resumen por sucursal, cuyo
     * contexto exterior es TAMBIEN stock_sucursales.
     */
    public function scopeBajoStock(Builder $query): Builder
    {
        $tabla = $this->getTable();
        $col = static::tipoArticulo()->columna();

        return $query->whereExists(fn($q) => $q->selectRaw('1')
            ->from('stock_sucursales as ss_min')
            ->whereColumn("ss_min.{$col}", "{$tabla}.id")
            ->where('ss_min.minimo', '>', 0)
            ->whereColumn('ss_min.cantidad', '<=', 'ss_min.minimo'));
    }

    /** Si alguna sucursal esta por agotarse. Usa `stocks` si ya vino cargado. */
    public function estaPorAgotarse(): bool
    {
        return $this->stocks->contains(fn($s) => $s->estaPorAgotarse());
    }

    /** Las unidades que hay en una sucursal concreta. */
    public function stockEn(?int $sucursalId): int
    {
        if ($sucursalId === null) {
            return 0;
        }

        // Si la relacion ya vino cargada no se vuelve a consultar: las tablas y
        // los selectores pintan esto por fila.
        if ($this->relationLoaded('stocks')) {
            return (int) ($this->stocks->firstWhere('sucursal_id', $sucursalId)?->cantidad ?? 0);
        }

        return (int) ($this->stocks()->where('sucursal_id', $sucursalId)->value('cantidad') ?? 0);
    }

    /**
     * El reparto del stock por sucursal, para la columna del catalogo.
     *
     * Clases literales y nada compuesto: no hay safelist en tailwind.config.js.
     * Las sucursales en cero no se pintan, pero los NEGATIVOS si, en rojo:
     * esconderlos seria esconder justo lo que hay que corregir.
     *
     * Espera `stocks.sucursal` ya cargado; si no, son dos consultas por fila.
     */
    public function desgloseStock(): string
    {
        // En cero no se pinta, salvo que tenga minimo: entonces es justo lo que
        // hay que reponer.
        $partes = $this->stocks
            ->filter(fn($s) => (int) $s->cantidad !== 0 || $s->estaPorAgotarse())
            ->sortByDesc('cantidad')
            ->map(function ($s) {
                $cantidad = (int) $s->cantidad;
                $clase = $cantidad < 0 || $s->estaPorAgotarse() ? 'text-red-600 font-semibold' : 'font-semibold';
                $minimo = $s->minimo > 0 ? ' <span class="text-gray-400">(mín. ' . (int) $s->minimo . ')</span>' : '';

                return '<span class="whitespace-nowrap">' . e($s->sucursal?->nombre ?? 'Sin sucursal')
                    . ' <span class="' . $clase . '">' . $cantidad . '</span>' . $minimo . '</span>';
            });

        if ($partes->isEmpty()) {
            return '<span class="text-gray-400">Sin stock</span>';
        }

        return '<div class="flex flex-col gap-0.5 text-xs">' . $partes->implode('') . '</div>';
    }

    /**
     * Cuantos registros tiene: lineas de compra o venta, transferencias, bajas
     * (y regalos o piezas de reparacion, segun el tipo). Las FK hacia el
     * articulo van en RESTRICT, asi que borrar uno con movimientos fallaria con
     * 1451; esto deja dar el aviso ANTES de intentar.
     */
    public function cantidadMovimientos(): int
    {
        $col = static::tipoArticulo()->columna();
        $id = $this->getKey();
        $db = $this->getConnection();

        $total = $db->table('compras_detalles')->where($col, $id)->count()
            + $db->table('ventas_detalles')->where($col, $id)->count()
            + $db->table('stock_transferencias')->where($col, $id)->count()
            + $db->table('stock_bajas')->where($col, $id)->count();

        if (static::tipoArticulo() === ArticuloTipo::Repuesto) {
            $total += $db->table('productos_reparaciones_repuestos')->where('repuesto_id', $id)->count();
        } else {
            $total += $db->table('productos_regalos')->where('accesorio_id', $id)->count();
        }

        return $total;
    }

    /** "Nombre · SKU" para listas y buscadores. */
    public function etiqueta(): string
    {
        return $this->nombre . ($this->sku ? ' · ' . $this->sku : '');
    }
}
