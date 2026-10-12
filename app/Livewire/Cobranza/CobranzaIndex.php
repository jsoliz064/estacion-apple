<?php

namespace App\Livewire\Cobranza;

use App\Models\Venta;
use Livewire\Attributes\On;
use Livewire\Component;

/** La pantalla de cobranzas: el total por cobrar y la tabla de lo pendiente. */
class CobranzaIndex extends Component
{
    #[On('pagosActualizados')]
    public function refrescar(): void
    {
    }

    public function render()
    {
        $resumen = Venta::query()->conSaldo()->toBase()
            ->selectRaw('COUNT(*) as ventas, COUNT(DISTINCT cliente_id) as clientes, COALESCE(SUM(saldo), 0) as saldo')
            ->first();

        return view('livewire.cobranza.cobranza-index', ['resumen' => $resumen]);
    }
}
