<?php

namespace App\Exports;

use App\Exports\Sheets\ProductosReparacionesSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class TecnicoProductoExport implements WithMultipleSheets
{
    use Exportable;

    public $tecnico;

    public function __construct($tecnico)
    {
        $this->tecnico = $tecnico;
    }

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        array_push($sheets, new ProductosReparacionesSheet($this->tecnico));
        return $sheets;
    }
}
