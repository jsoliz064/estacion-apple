<?php

namespace App\Livewire\Producto\Modals;

use App\Models\Producto;
use App\Services\CompraService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoDestroyModal extends Component
{
    public $openModal = false;
    public $producto;

    #[On('openProductoDestroyModal')]
    public function openModal($id)
    {
        $this->producto = Producto::find($id);
        $this->openModal = true;
    }

    public function destroy()
    {
        abort_unless(auth()->user()->can('producto.delete'), 403);

        // Por CompraService: el equipo cuelga de su linea de compra (FK en
        // RESTRICT), y quitarlo tiene que recalcular el total de esa compra.
        // Un equipo con historia (venta, reparaciones, regalos) no se borra:
        // se da de baja.
        try {
            DB::transaction(fn() => app(CompraService::class)->quitarProducto(Producto::findOrFail($this->producto->id)));
        } catch (ValidationException $e) {
            toastr()->error(collect($e->errors())->flatten()->first());
            return;
        }

        $this->dispatch('refreshProductoTable');
        toastr()->success('Producto eliminado');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
    public function render()
    {
        return view('livewire.producto.modals.producto-destroy-modal');
    }
}
