<?php

namespace App\Exports\Sheets;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductosModelosSheet implements FromView, WithTitle, WithColumnWidths
{

    public function view(): View
    {
        $productosAgrupados = Producto::with('modelo')
            ->select(
                'producto_modelo_id',
                'estado_grado',
                'almacenamiento',
                'version',
                DB::raw('COUNT(*) as total_cantidad'),
                // Utilizamos group_concat (MySQL) o string_agg (PostgreSQL) para los colores
                DB::raw('GROUP_CONCAT(color) as colores')
            )
            ->disponibles()
            ->whereNotNull('sucursal_id') // Filtramos los que están en la sucursal (en stock/inventario)
            ->groupBy('producto_modelo_id', 'estado_grado', 'almacenamiento', 'version')
            ->orderBy('producto_modelo_id')
            ->get();

        // Procesar la columna de colores para contar las repeticiones
        $productos = $productosAgrupados->map(function ($grupo) {
            $listaColores = explode(',', $grupo->colores);
            $conteoColores = array_count_values(array_filter($listaColores));

            $coloresFormateados = [];
            foreach ($conteoColores as $color => $cantidad) {
                // Formato Ej: "Negro 4"
                $coloresFormateados[] = ucfirst(trim($color)) . " " . $cantidad;
            }

            return [
                'grado' => $grupo->estado_grado ?? 'N/A',
                'modelo' => $grupo->modelo ? $grupo->modelo->nombre : 'Desconocido',
                'memoria' => $grupo->almacenamiento ?? 'N/A',
                'version' => $grupo->version ?? 'N/A',
                'cantidad' => $grupo->total_cantidad,
                'colores_detalle' => implode(', ', $coloresFormateados) ?: 'Sin color',
            ];
        });

        return view('exports.productos-modelos', compact('productos'));
    }

    public function title(): string
    {
        return 'Productos';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 35,
            'C' => 15,
            'D' => 15,
            'E' => 15,
            'F' => 70,
        ];
    }
}
