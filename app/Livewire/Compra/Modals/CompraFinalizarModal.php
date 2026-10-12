<?php

namespace App\Livewire\Compra\Modals;

use App\Enums\LineaTipo;
use App\Models\Compra;
use App\Models\MetodoPago;
use App\Services\CompraService;
use App\Traits\FilasDePagoFormTrait;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Finalizar una compra en borrador (CompraService::finalizar): su stock entra
 * a la sucursal, sus equipos pasan de En compra a su estado y se registra lo
 * pagado al recibir. Lo que falte queda en cuentas por pagar.
 *
 * Reintentar es inocuo: el servicio rechaza una compra ya finalizada, y aqui se
 * traduce a «ya estaba finalizada» en vez de un error.
 */
class CompraFinalizarModal extends Component
{
    use FilasDePagoFormTrait;
    use GuardadoIdempotenteTrait;

    public bool $openModal = false;

    #[Locked]
    public ?int $compraId = null;

    #[On('openCompraFinalizarModal')]
    public function openModal(int $compraId): void
    {
        abort_unless(Auth::user()?->can('compra.finalizar'), 403);

        $this->resetErrorBag();
        $this->compraId = Compra::findOrFail($compraId)->id;
        $this->montoTocado = false;
        $this->pagos = [$this->filaPago(MetodoPago::activos()->value('id'))];
        // La clave de los pagos: se siembra al abrir, nunca en render().
        $this->nuevaClaveIdempotencia();
        $this->openModal = true;
    }

    private function compra(): ?Compra
    {
        return $this->compraId ? Compra::find($this->compraId) : null;
    }

    /** Lo que se debe antes de estos pagos (puede haber un adelanto). */
    public function saldoPrevisto(): float
    {
        return round(($this->compra()?->saldoPendiente() ?? 0) - $this->sumaFilas(), 2);
    }

    public function finalizar(): void
    {
        abort_unless(Auth::user()?->can('compra.finalizar'), 403);

        try {
            DB::transaction(fn() => app(CompraService::class)->finalizar(
                Compra::lockForUpdate()->findOrFail($this->compraId),
                Auth::user(),
                $this->pagos,
                $this->claveIdempotencia,
            ));
        } catch (ValidationException $e) {
            // El reintento de una que ya entro: se avisa y se cierra.
            if (!$this->compra()?->esBorrador()) {
                toastr()->info("La compra #{$this->compraId} ya estaba finalizada.");
                $this->cerrarYRefrescar();

                return;
            }

            throw $e;
        }

        toastr()->success('Compra finalizada: el stock entró y los equipos ya se pueden vender.');
        $this->cerrarYRefrescar();
    }

    private function cerrarYRefrescar(): void
    {
        $this->openModal = false;
        $this->dispatch('refreshCompraDetalle');
        $this->dispatch('refreshProductoTable');
        $this->dispatch('pagosProveedorActualizados');
    }

    public function closeModal(): void
    {
        $this->openModal = false;
    }

    public function render()
    {
        $compra = $this->openModal ? Compra::with('detalles')->find($this->compraId) : null;

        if ($compra) {
            $this->seguirMonto($compra->saldoPendiente());
        }

        return view('livewire.compra.modals.compra-finalizar-modal', [
            'compra' => $compra,
            'equipos' => $compra ? $compra->detalles->whereNotNull('producto_id')->count() : 0,
            'repuestos' => $compra ? (int) $compra->detalles->where('tipo', LineaTipo::Repuesto->value)->sum('cantidad') : 0,
            'accesorios' => $compra ? (int) $compra->detalles->where('tipo', LineaTipo::Accesorio->value)->sum('cantidad') : 0,
            'metodos' => $compra ? MetodoPago::activos()->get() : collect(),
        ]);
    }
}
