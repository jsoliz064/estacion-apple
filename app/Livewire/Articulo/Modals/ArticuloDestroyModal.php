<?php

namespace App\Livewire\Articulo\Modals;

use App\Enums\ArticuloTipo;
use App\Models\StockSucursal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Eliminar un repuesto o un accesorio.
 *
 * Las FK hacia el articulo (lineas de compra y venta, transferencias, bajas,
 * piezas de reparacion, regalos) van en RESTRICT: un articulo con historia NO
 * se borra. El conteo va ANTES de confirmar, con un aviso claro, en vez del
 * catch generico de antes. Sin movimientos (stock cargado solo desde la ficha)
 * se borra junto con sus filas de stock, dejando nota de cuanto habia.
 */
class ArticuloDestroyModal extends Component
{
    #[Locked]
    public string $tipo = '';

    public $openModal = false;
    public ?int $articuloId = null;
    public int $movimientos = 0;

    public function mount(string $tipo): void
    {
        $this->tipo = ArticuloTipo::from($tipo)->value;
    }

    private function tipoEnum(): ArticuloTipo
    {
        return ArticuloTipo::from($this->tipo);
    }

    #[On('openArticuloDestroyModal')]
    public function openModal($id)
    {
        $articulo = ($this->tipoEnum()->modelo())::findOrFail($id);
        $this->articuloId = $articulo->id;
        $this->movimientos = $articulo->cantidadMovimientos();
        $this->openModal = true;
    }

    public function destroy()
    {
        $tipo = $this->tipoEnum();
        abort_unless(Auth::user()?->can($tipo->permiso() . '.delete'), 403);

        $articulo = ($tipo->modelo())::findOrFail($this->articuloId);

        // Se vuelve a contar: entre abrir el modal y confirmar pudo venderse.
        if ($articulo->cantidadMovimientos() > 0) {
            $this->movimientos = $articulo->cantidadMovimientos();
            toastr()->error('El artículo tiene movimientos: no se puede eliminar.');

            return;
        }

        DB::transaction(function () use ($articulo, $tipo) {
            // El stock se va con la ficha: se anota cuanto habia, porque
            // `cantidad` no entra en el retrato del observer.
            $articulo->anotar('eliminado', $this->stockAlBorrar($tipo, $articulo->id));
            StockSucursal::where($tipo->columna(), $articulo->id)->delete();
            $articulo->delete();
        });

        $this->dispatch('refreshArticuloTable');
        toastr()->success($tipo->label() . ' eliminado exitosamente');
        $this->reset(['openModal', 'articuloId', 'movimientos']);
    }

    /** "Se borró con 7 unidades: Almacen 5, Centro 2." */
    private function stockAlBorrar(ArticuloTipo $tipo, int $id): string
    {
        $reparto = StockSucursal::with('sucursal:id,nombre')
            ->where($tipo->columna(), $id)
            ->where('cantidad', '<>', 0)
            ->orderByDesc('cantidad')
            ->get();

        if ($reparto->isEmpty()) {
            return 'Se borró sin stock.';
        }

        $detalle = $reparto->map(fn($s) => ($s->sucursal?->nombre ?? 'Sin sucursal') . ' ' . (int) $s->cantidad)->implode(', ');

        return 'Se borró con ' . (int) $reparto->sum('cantidad') . " unidades: {$detalle}.";
    }

    public function closeModal()
    {
        $this->reset(['openModal', 'articuloId', 'movimientos']);
    }

    public function render()
    {
        return view('livewire.articulo.modals.articulo-destroy-modal', [
            'articulo' => $this->openModal && $this->articuloId
                ? ($this->tipoEnum()->modelo())::with('stocks.sucursal:id,nombre')->find($this->articuloId)
                : null,
        ]);
    }
}
