<?php

namespace App\Livewire\TecnicoProducto;

use App\Exports\TecnicoProductoExport;
use App\Models\Comision;
use App\Models\Tecnicos;
use Livewire\Component;
use Livewire\Attributes\On;

class TecnicoProductoIndex extends Component
{

    public $tecnico;
    public $cant_productos_pendientes;
    /** Pendiente / por pagar / pagado de su comision (Comision::cifras). */
    public array $cifras = [];

    public function mount($tecnico_id)
    {
        $tecnico = Tecnicos::find($tecnico_id);
        $this->tecnico = $tecnico;
        $this->calculateRepraciones();
    }

    private function calculateRepraciones()
    {
        $this->cant_productos_pendientes = $this->tecnico->reparaciones()->where('estado', 'Pendiente')->count();
        $this->cifras = Comision::cifras(Comision::where('tecnico_id', $this->tecnico->id));
    }

    public function render()
    {
        return view('livewire.tecnico-producto.tecnico-producto-index');
    }

    public function liquidar()
    {
        $this->dispatch('openComisionLiquidarModal', 'T-' . $this->tecnico->id);
    }

    public function openTecnicoTerminarModal($tenico_id)
    {
        $this->dispatch('openTecnicoTerminarModal', $tenico_id);
    }

    #[On('refreshTecnicoProductoIndex')]
    #[On('comisionesActualizadas')]
    public function refreshTecnicoProductoIndex()
    {
        $this->calculateRepraciones();
    }

    public function exportProductosExcel()
    {
        $now = date('Hi');
        return (new TecnicoProductoExport($this->tecnico))
            ->download("productos-pendientes-{$this->tecnico->nombre}-{$now}.xlsx");
    }
}
