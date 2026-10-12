<?php

namespace App\Exports\Sheets;

use App\Models\Producto;
use App\Models\ProductoReparacion;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;

class ProductosReparacionesSheet implements FromView, WithTitle, WithColumnWidths
{

    public $tecnico;

    public function __construct($tecnico)
    {
        $this->tecnico = $tecnico;
    }

    public function view(): View
    {
        $reparaciones = ProductoReparacion::where('estado', 'Pendiente')->where('tecnico_id', $this->tecnico->id)->get();
        return view('exports.productos-reparaciones', compact('reparaciones'));
    }

    public function title(): string
    {
        return 'Productos Pendientes';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,
            'B' => 15,
            'C' => 35,
            'D' => 10,
            'E' => 20,
            'F' => 20,
            'G' => 30,
            'H' => 30,
            'I' => 30,
            'J' => 15,
            'K' => 15,
            'L' => 18,
            'M' => 10,
            'N' => 20,
            'O' => 20,
        ];
    }
}
