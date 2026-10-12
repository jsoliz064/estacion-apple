<?php

namespace App\Exports;

use App\Exports\Sheets\ProductosModelosSheet;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class ProductoModeloExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @return array
     */
    public function sheets(): array
    {
        $sheets = [];

        array_push($sheets, new ProductosModelosSheet());
        return $sheets;
    }
}
