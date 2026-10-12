<?php

namespace App\Livewire\Sucursal\Modals;

use App\Models\Sucursal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Eliminar una sucursal, o desactivarla si ya tiene movimientos.
 *
 * Casi todas las FK hacia sucursales son nullOnDelete: borrar una con ventas no
 * fallaria, las dejaria sin sucursal en silencio y desapareceria de los
 * reportes. Por eso el conteo va ANTES de confirmar y, si hay algo, la unica
 * salida es desactivarla (docs/01: "una sucursal con movimientos no se elimina").
 */
class SucursalDestroyModal extends Component
{
    public $openModal = false;
    public ?Sucursal $sucursal = null;
    public int $movimientos = 0;

    #[On('openSucursalDestroyModal')]
    public function openModal($id)
    {
        $this->sucursal = Sucursal::findOrFail($id);

        if ($this->sucursal->esAlmacen()) {
            toastr()->error('El Almacén es obligatorio: no se puede eliminar.');
            $this->reset();

            return;
        }

        $this->movimientos = $this->sucursal->cantidadMovimientos();
        $this->openModal = true;
    }

    public function destroy()
    {
        abort_unless(Auth::user()?->can('sucursal.delete'), 403);

        $sucursal = Sucursal::findOrFail($this->sucursal->id);

        // Se vuelve a contar: entre abrir el modal y confirmar pudo registrarse
        // una venta en esa sucursal.
        if ($sucursal->esAlmacen() || $sucursal->cantidadMovimientos() > 0) {
            toastr()->error('La sucursal tiene movimientos: desactívala en lugar de eliminarla.');
            $this->movimientos = max(1, $sucursal->cantidadMovimientos());

            return;
        }

        $sucursal->delete();
        $this->dispatch('refreshSucursalTable');
        toastr()->success('Sucursal eliminada exitosamente');
        $this->reset();
    }

    public function desactivar()
    {
        abort_unless(Auth::user()?->can('sucursal.delete'), 403);

        $sucursal = Sucursal::findOrFail($this->sucursal->id);

        if ($sucursal->esAlmacen()) {
            toastr()->error('El Almacén es obligatorio: no se puede desactivar.');

            return;
        }

        $sucursal->update(['activa' => false]);
        $this->dispatch('refreshSucursalTable');
        toastr()->success('Sucursal desactivada: ya no se ofrece al cargar productos ni ventas.');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.sucursal.modals.sucursal-destroy-modal');
    }
}
