<?php

namespace App\Livewire\CompraLote\Modals;

use App\Models\Producto;
use App\Services\CompraService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\On;


class CompraLoteProductoDestroyModal extends Component
{
    public $openModal = false;
    public $producto;

    #[On('openCompraLoteProductoDestroyModal')]
    public function openModal($id)
    {
        $this->producto = Producto::find($id);
        $this->openModal = true;
    }

    /**
     * Quita el equipo de su compra y lo borra. CompraService::quitarProducto()
     * lo impide si ya tiene ventas, reparaciones o regalos (tiene historia).
     */
    public function destroy()
    {
        abort_unless(Auth::user()?->can('compra.edit'), 403);

        try {
            DB::transaction(fn() => app(CompraService::class)->quitarProducto(Producto::findOrFail($this->producto->id)));
        } catch (ValidationException $e) {
            toastr()->error(implode(' ', $e->validator->errors()->all()));

            return;
        }

        toastr()->success('Equipo quitado de la compra');
        $this->dispatch('refreshCompraDetalle');
        $this->dispatch('loadModelCounts');
        $this->deleteAndClose();
    }

    public function deleteAndClose()
    {
        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }


    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.compra-lote.modals.compra-lote-producto-destroy-modal');
    }
}
