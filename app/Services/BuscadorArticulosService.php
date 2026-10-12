<?php

namespace App\Services;

use App\Enums\ArticuloTipo;
use App\Enums\LineaTipo;
use App\Models\Producto;
use Illuminate\Support\Collection;

/**
 * El buscador de las pantallas de venta y compra: un solo campo que acepta
 * IMEI, codigo de barras (UPC), SKU o nombre, sobre equipos, repuestos y
 * accesorios. La pistola USB "teclea" el codigo y manda Enter; la camara del
 * celular escribe en el mismo campo.
 *
 * Devuelve filas normalizadas, iguales para los tres tipos:
 *   ['tipo' => 'Producto'|'Repuesto'|'Accesorio', 'id', 'etiqueta', 'detalle',
 *    'codigo', 'precio', 'costo', 'stock']
 */
class BuscadorArticulosService
{
    private const LIMITE = 12;

    /**
     * @param  LineaTipo[]  $tipos       que buscar
     * @param  int|null     $sucursalId  para el stock de repuestos/accesorios
     * @param  bool         $soloDisponibles  equipos vendibles (venta) o todos los vigentes
     */
    public function buscar(string $termino, array $tipos, ?int $sucursalId = null, bool $soloDisponibles = true): Collection
    {
        $termino = trim($termino);

        if (mb_strlen($termino) < 2) {
            return collect();
        }

        $resultados = collect();

        if (in_array(LineaTipo::Producto, $tipos, true)) {
            $resultados = $resultados->merge($this->equipos($termino, $soloDisponibles));
        }

        foreach ([ArticuloTipo::Repuesto, ArticuloTipo::Accesorio] as $tipo) {
            if (in_array($tipo->lineaTipo(), $tipos, true)) {
                $resultados = $resultados->merge($this->articulos($tipo, $termino, $sucursalId));
            }
        }

        return $resultados->values();
    }

    /**
     * Coincidencia EXACTA y UNICA por codigo (IMEI, UPC o SKU): lo que manda el
     * lector al terminar con Enter. Si hay cero o mas de una, devuelve null y la
     * pantalla muestra la lista.
     */
    public function porCodigo(string $codigo, array $tipos, ?int $sucursalId = null, bool $soloDisponibles = true): ?array
    {
        $codigo = trim($codigo);

        if ($codigo === '') {
            return null;
        }

        $exactas = $this->buscar($codigo, $tipos, $sucursalId, $soloDisponibles)
            ->filter(fn($r) => in_array($codigo, $r['codigos'], true));

        return $exactas->count() === 1 ? $exactas->first() : null;
    }

    private function equipos(string $termino, bool $soloDisponibles): Collection
    {
        $patron = '%' . addcslashes($termino, '%_\\') . '%';

        return Producto::query()
            ->with('modelo:id,nombre')
            ->when($soloDisponibles, fn($q) => $q->disponibles(), fn($q) => $q->vigentes())
            ->where(fn($q) => $q
                ->where('imei', 'like', $patron)
                ->orWhere('sku', $termino)
                ->orWhere('upc', $termino))
            // Exacto primero (lector de codigos), luego por sufijo de IMEI: el
            // cliente suele dictar los ultimos digitos.
            ->orderByRaw('CASE WHEN imei = ? OR sku = ? OR upc = ? THEN 0 WHEN imei LIKE ? THEN 1 ELSE 2 END', [
                $termino, $termino, $termino, '%' . addcslashes($termino, '%_\\'),
            ])
            ->limit(self::LIMITE)
            ->get()
            ->map(fn(Producto $p) => [
                'tipo' => LineaTipo::Producto->value,
                'id' => $p->id,
                'etiqueta' => trim(($p->modelo?->nombre ?? 'Equipo') . ' ' . $p->almacenamiento . ' ' . $p->color),
                'detalle' => 'IMEI ' . $p->imei . ($p->sku ? ' · SKU ' . $p->sku : ''),
                'codigos' => array_values(array_filter([$p->imei, $p->sku, $p->upc])),
                'precio' => (float) $p->precio_cliente,
                'precio_vendedor' => (float) $p->precio_vendedor,
                'costo' => (float) $p->costo_total,
                'stock' => 1,
            ]);
    }

    private function articulos(ArticuloTipo $tipo, string $termino, ?int $sucursalId): Collection
    {
        $patron = '%' . addcslashes($termino, '%_\\') . '%';
        $modelo = $tipo->modelo();

        return $modelo::query()
            ->with(['stocks' => fn($q) => $q->when($sucursalId, fn($s) => $s->where('sucursal_id', $sucursalId))])
            ->where(fn($q) => $q
                ->where('sku', $termino)
                ->orWhere('upc', $termino)
                ->orWhere('nombre', 'like', $patron))
            ->orderByRaw('CASE WHEN sku = ? OR upc = ? THEN 0 ELSE 1 END', [$termino, $termino])
            ->orderBy('nombre')
            ->limit(self::LIMITE)
            ->get()
            ->map(fn($a) => [
                'tipo' => $tipo->value,
                'id' => $a->id,
                'etiqueta' => $a->nombre,
                'detalle' => trim(($a->sku ? 'SKU ' . $a->sku : '') . ($sucursalId ? ' · Stock ' . $a->stockEn($sucursalId) : ' · Stock ' . $a->cantidad), ' ·'),
                'codigos' => array_values(array_filter([$a->sku, $a->upc])),
                'precio' => (float) $a->precio,
                'precio_vendedor' => (float) $a->precio,
                'costo' => (float) $a->costo,
                'stock' => $sucursalId ? $a->stockEn($sucursalId) : (int) $a->cantidad,
            ]);
    }
}
