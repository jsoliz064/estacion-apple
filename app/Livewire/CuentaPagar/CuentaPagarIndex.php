<?php

namespace App\Livewire\CuentaPagar;

use App\Models\Compra;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/** Cuentas por pagar: el total que se debe, por proveedor y por compra. */
class CuentaPagarIndex extends Component
{
    #[On('pagosProveedorActualizados')]
    public function refrescar(): void
    {
    }

    public function render()
    {
        $resumen = Compra::query()->conSaldo()->toBase()
            ->selectRaw('COUNT(*) as compras, COUNT(DISTINCT proveedor_id) as proveedores, COALESCE(SUM(saldo), 0) as saldo')
            ->first();

        // Por proveedor: lo que se le debe y desde cuando.
        $porProveedor = DB::table('compras as c')
            ->join('proveedores as p', 'p.id', '=', 'c.proveedor_id')
            ->where('c.saldo', '>', 0)
            ->groupBy('p.id', 'p.nombre')
            ->orderByDesc(DB::raw('SUM(c.saldo)'))
            ->selectRaw('p.id, p.nombre, COUNT(*) as compras, SUM(c.saldo) as saldo, MIN(c.fecha) as desde')
            ->get();

        return view('livewire.cuenta-pagar.cuenta-pagar-index', ['resumen' => $resumen, 'porProveedor' => $porProveedor]);
    }
}
