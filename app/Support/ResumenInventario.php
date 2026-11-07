<?php

namespace App\Support;

use App\Models\Producto;
use App\Models\Sucursal;

/**
 * Cuantos equipos vendibles hay en cada sucursal, por modelo y almacenamiento.
 *
 * Una sola copia del conteo: hoy lo usa el resumen de la pagina de inicio, y
 * otra pantalla que lo necesite viene aqui (dos consultas tarde o temprano
 * cuentan distinto). Cuenta solo lo disponible (Inventario y sin baja,
 * Producto::disponibles()), lo mismo que la tarjeta «En inventario»: es lo que
 * se le puede ofrecer a un cliente.
 */
class ResumenInventario
{
    /**
     * Los renglones de cada sucursal, con las sucursales por nombre (como el
     * card de ProductoIndex). Solo las que tienen equipos: una sucursal vacia no
     * le dice nada a quien lo mira.
     *
     * @return array<int, array{nombre: string, total: int, lineas: array<int, array{texto: string, total: int}>}>
     */
    public static function bloques(?int $modeloId = null, ?int $sucursalId = null): array
    {
        $filas = Producto::query()
            ->disponibles()
            ->when($modeloId, fn($q) => $q->where('productos.producto_modelo_id', $modeloId))
            ->when($sucursalId, fn($q) => $q->where('productos.sucursal_id', $sucursalId))
            ->join('productos_modelos as m', 'm.id', '=', 'productos.producto_modelo_id')
            ->selectRaw('productos.sucursal_id, m.nombre as modelo, productos.almacenamiento, COUNT(*) as total')
            ->groupBy('productos.sucursal_id', 'm.nombre', 'productos.almacenamiento')
            ->toBase()
            ->get()
            ->groupBy(fn($fila) => (int) $fila->sucursal_id);

        $nombres = Sucursal::whereIn('id', $filas->keys())->orderBy('nombre')->pluck('nombre', 'id');

        // Un equipo sin sucursal no deberia existir, pero si existe no puede
        // desaparecer del conteo: va al final, en su propio bloque.
        $orden = $nombres->keys()->all();
        if ($filas->has(0)) {
            $orden[] = 0;
        }

        return collect($orden)->map(function (int $id) use ($filas, $nombres) {
            // Orden natural: "64GB" antes que "128GB", que como texto saldria al reves.
            $lineas = $filas[$id]
                ->map(fn($fila) => [
                    'texto' => trim($fila->modelo . ' ' . $fila->almacenamiento),
                    'total' => (int) $fila->total,
                ])
                ->sort(fn($a, $b) => strnatcasecmp($a['texto'], $b['texto']))
                ->values()
                ->all();

            return [
                'nombre' => $nombres[$id] ?? 'Sin sucursal',
                'total' => array_sum(array_column($lineas, 'total')),
                'lineas' => $lineas,
            ];
        })->all();
    }
}
