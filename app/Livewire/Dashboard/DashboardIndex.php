<?php

namespace App\Livewire\Dashboard;

use App\Enums\ProductoEstado;
use App\Enums\ProductoTipoVenta;
use App\Models\Producto;
use App\Models\Venta;
use Livewire\Component;

/** El tablero de inicio. Todo en Bs, sobre la venta unificada. */
class DashboardIndex extends Component
{
    public $ventas_dia = 0;
    public $ventas_dia_cant = 0;
    public $ventas_mes = 0;
    public $ventas_mes_cant = 0;
    public $productos_inventario = 0;
    public $productos_oferta = 0;
    public $productos_reparacion = 0;
    public $productos_reserva = 0;
    public $productos_credito = 0;
    public $por_cobrar = 0;
    public $ventas_credito = 0;
    public $por_pagar = 0;
    public $comisiones_por_pagar = 0;

    public function mount()
    {
        // Inventario cuenta todo lo vendible; oferta es un subconteo de eso
        // (tipo_venta = Oferta), no una cifra aparte que se sume.
        $this->productos_inventario = Producto::disponibles()->count();
        $this->productos_oferta = Producto::disponibles()->where('tipo_venta', ProductoTipoVenta::Oferta->value)->count();
        $this->productos_reparacion = Producto::vigentes()->where('estado', ProductoEstado::Reparacion->value)->count();
        $this->productos_reserva = Producto::vigentes()->where('estado', ProductoEstado::Reserva->value)->count();
        $this->productos_credito = Producto::vigentes()->where('estado', ProductoEstado::Credito->value)->count();
        $this->por_cobrar = (float) Venta::conSaldo()->sum('saldo');
        $this->ventas_credito = Venta::conSaldo()->count();
        $this->por_pagar = (float) \App\Models\Compra::conSaldo()->sum('saldo');
        $this->comisiones_por_pagar = (float) \App\Models\Comision::porPagar()->sum('monto');

        $dia = [now()->startOfDay(), now()->endOfDay()];
        $mes = [now()->startOfMonth(), now()->endOfMonth()];

        $this->ventas_dia = (float) Venta::whereBetween('created_at', $dia)->sum('total');
        $this->ventas_dia_cant = Venta::whereBetween('created_at', $dia)->count();
        $this->ventas_mes = (float) Venta::whereBetween('created_at', $mes)->sum('total');
        $this->ventas_mes_cant = Venta::whereBetween('created_at', $mes)->count();
    }

    public function render()
    {
        return view('livewire.dashboard.dashboard-index');
    }
}
