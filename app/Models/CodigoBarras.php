<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Un codigo de barras propio (EAN-13 '20...'), impreso como sticker antes de
 * saber a que producto va. Solo lo escribe CodigoBarrasService.
 *
 * A quien se le asigno NO se guarda aqui: es quien lo tenga como upc o sku
 * (un equipo en su UPC, un repuesto o accesorio en su SKU o UPC). duenos() es
 * la unica traduccion de esa pregunta a SQL.
 */
class CodigoBarras extends Model
{
    protected $table = 'codigos_barras';
    protected $guarded = ['id'];

    protected $casts = [
        'secuencia' => 'integer',
    ];

    /** Las columnas donde un codigo queda asignado: [tabla, tipo, columna]. */
    private const RAMAS = [
        ['productos', 'Producto', 'upc'],
        ['productos', 'Producto', 'sku'],
        ['repuestos', 'Repuesto', 'upc'],
        ['repuestos', 'Repuesto', 'sku'],
        ['accesorios', 'Accesorio', 'upc'],
        ['accesorios', 'Accesorio', 'sku'],
    ];

    public function lote()
    {
        return $this->belongsTo(CodigoLote::class, 'lote_id');
    }

    /**
     * Filas (tipo, dueno_id, codigo) de todo lo que tiene un codigo cargado,
     * o solo de $codigos. Un equipo con el mismo codigo en UPC y SKU sale dos
     * veces: quien cuente duenos, que cuente (tipo, dueno_id) distintos.
     */
    public static function consultaDuenos(?array $codigos = null): Builder
    {
        $consulta = null;

        foreach (self::RAMAS as [$tabla, $tipo, $columna]) {
            $rama = DB::table($tabla)
                ->selectRaw("'{$tipo}' as tipo, id as dueno_id, {$columna} as codigo")
                ->when(
                    $codigos !== null,
                    fn($q) => $q->whereIn($columna, $codigos),
                    fn($q) => $q->whereNotNull($columna),
                );

            $consulta = $consulta ? $consulta->unionAll($rama) : $rama;
        }

        return $consulta;
    }

    /**
     * Quien tiene cada codigo, para mostrarlo: [codigo => ['iPhone 13 128GB · IMEI ...', ...]].
     * Un codigo sin dueno no aparece.
     *
     * @param  string[]  $codigos
     * @return Collection<string, string[]>
     */
    public static function duenos(array $codigos): Collection
    {
        if ($codigos === []) {
            return collect();
        }

        $filas = self::consultaDuenos($codigos)->get()
            ->unique(fn($f) => $f->codigo . '|' . $f->tipo . '|' . $f->dueno_id);

        $nombres = self::nombres($filas);

        return $filas->groupBy('codigo')->map(fn($g) => $g
            ->map(fn($f) => $nombres[$f->tipo][$f->dueno_id] ?? "{$f->tipo} #{$f->dueno_id}")
            ->values()
            ->all());
    }

    /**
     * Como se nombra a cada dueno de filas de consultaDuenos(), en tres
     * consultas (una por tabla): [tipo => [id => nombre]].
     */
    public static function nombres(Collection $filas): array
    {
        $ids = $filas->groupBy('tipo')->map(fn($g) => $g->pluck('dueno_id')->unique()->all());

        $nombres = [];
        foreach (Producto::with('modelo')->whereIn('id', $ids['Producto'] ?? [])->get() as $p) {
            $nombres['Producto'][$p->id] = trim(($p->modelo?->nombre ?? 'Equipo') . ' ' . $p->almacenamiento) . ' · IMEI ' . $p->imei;
        }
        foreach (Repuesto::whereIn('id', $ids['Repuesto'] ?? [])->get(['id', 'nombre']) as $r) {
            $nombres['Repuesto'][$r->id] = 'Repuesto: ' . $r->nombre;
        }
        foreach (Accesorio::whereIn('id', $ids['Accesorio'] ?? [])->get(['id', 'nombre']) as $a) {
            $nombres['Accesorio'][$a->id] = 'Accesorio: ' . $a->nombre;
        }

        return $nombres;
    }
}
