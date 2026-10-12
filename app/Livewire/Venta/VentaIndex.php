<?php

namespace App\Livewire\Venta;

use App\Enums\LineaTipo;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\Venta;
use Carbon\Carbon;
use Livewire\Component;

class VentaIndex extends Component
{
    public $fechaDesde = '';
    public $fechaHasta = '';
    public array $selectedUsers = [];
    public array $selectedSucursales = [];

    public float $totalVenta = 0;
    public int $cantidadEquipos = 0;
    public int $cantidadArticulos = 0;
    public int $cantidadVentas = 0;
    public float $totalGanancia = 0;

    public $users = [];
    public $sucursales = [];

    public function mount()
    {
        $this->users = User::orderBy('name')->get();
        $this->sucursales = Sucursal::orderBy('nombre')->get();
        $this->applyFilters();
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['fechaDesde', 'fechaHasta', 'selectedUsers.0', 'selectedSucursales.0'])) {
            $this->applyFilters();
        }
    }

    public function applyFilters()
    {
        $this->calculateTotals();

        $this->dispatch('filtersUpdated', [
            'fechaDesde' => $this->fechaDesde,
            'fechaHasta' => $this->fechaHasta,
            'users' => $this->selectedUsers,
            'sucursales' => $this->selectedSucursales,
        ]);
    }

    /**
     * Totales del periodo filtrado, en Bs. La ganancia es total - costo_total
     * de cada venta (mano de obra incluida en los dos, asi que se cancela).
     */
    public function calculateTotals()
    {
        $query = Venta::query()
            ->when($this->fechaDesde, fn($q) => $q->where('ventas.created_at', '>=', Carbon::parse($this->fechaDesde)->startOfDay()))
            ->when($this->fechaHasta, fn($q) => $q->where('ventas.created_at', '<=', Carbon::parse($this->fechaHasta)->endOfDay()))
            ->when(!empty($this->selectedUsers), fn($q) => $q->whereIn('ventas.user_id', $this->selectedUsers))
            ->when(!empty($this->selectedSucursales), fn($q) => $q->whereIn('ventas.sucursal_id', $this->selectedSucursales));

        $this->totalVenta = (float) (clone $query)->sum('total');
        $this->cantidadVentas = (clone $query)->count();
        $this->totalGanancia = round($this->totalVenta - (float) (clone $query)->sum('costo_total'), 2);

        // Sin los regalos: no son articulos vendidos.
        $lineas = (clone $query)->join('ventas_detalles', 'ventas.id', '=', 'ventas_detalles.venta_id')->whereNull('ventas_detalles.producto_regalo_id');
        $this->cantidadEquipos = (clone $lineas)->where('ventas_detalles.tipo', LineaTipo::Producto->value)->count();
        $this->cantidadArticulos = (int) (clone $lineas)->where('ventas_detalles.tipo', '!=', LineaTipo::Producto->value)->sum('ventas_detalles.cantidad');
    }

    public function render()
    {
        return view('livewire.venta.venta-index');
    }

    public function ventaCreate()
    {
        return redirect()->route('ventas.crear');
    }
}
