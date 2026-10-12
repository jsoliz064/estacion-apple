<?php

namespace App\Livewire\CuentaPagar\Modals;

use App\Enums\Moneda;
use App\Models\Compra;
use App\Models\CompraPago;
use App\Models\MetodoPago;
use App\Services\PagoProveedorService;
use App\Services\PagoService;
use App\Models\Proveedor;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Pagarle a un proveedor (cuentas por pagar): lista sus compras con saldo y se
 * escribe cuanto va a cada una. "Monto pagado" lo reparte de la mas antigua a
 * la mas nueva (o empezando por la compra desde la que se abrio). Es el espejo
 * de CobroModal, del otro lado del mostrador.
 *
 * Un pago reparte UNA clave de idempotencia entre las filas que crea: el
 * reintento choca con cp_clave_idem_compra_metodo_unico y no se paga dos veces.
 */
class PagoProveedorModal extends Component
{
    use GuardadoIdempotenteTrait;

    public bool $openModal = false;

    #[Locked]
    public ?int $proveedorId = null;

    /** La compra desde la que se abrio: va primera en el reparto. */
    #[Locked]
    public ?int $compraId = null;

    /** En la moneda elegida: Bs, o dolares (con su tipo de cambio). */
    public $montoRecibido = '';
    public string $moneda = 'BOB';
    public $tipo_cambio = '';
    public $metodo_pago_id = '';
    public string $nota = '';

    /** compra_id => monto */
    public array $montos = [];

    #[On('openPagoProveedorModal')]
    public function openModal($proveedorId, $compraId = null): void
    {
        $this->reset(['montoRecibido', 'moneda', 'metodo_pago_id', 'nota', 'montos', 'compraId']);
        $this->tipo_cambio = PagoService::ultimoTipoCambio();
        $this->resetErrorBag();

        $this->proveedorId = Proveedor::findOrFail($proveedorId)->id;
        $this->compraId = $compraId ? (int) $compraId : null;
        $this->metodo_pago_id = (string) (MetodoPago::activos()->value('id') ?? '');

        // Una clave por apertura, nunca en render().
        $this->nuevaClaveIdempotencia();

        if ($this->compraId && $compra = $this->comprasConSaldo()->firstWhere('id', $this->compraId)) {
            $this->montoRecibido = $compra->saldoPendiente();
            $this->repartir();
        }

        $this->openModal = true;
    }

    /** Sus compras con saldo, en el orden del reparto. */
    private function comprasConSaldo()
    {
        return Compra::where('proveedor_id', $this->proveedorId)->conSaldo()
            ->orderByRaw('id = ? DESC', [(int) $this->compraId])
            // Por la fecha de la compra (puede cargarse despues con fecha
            // anterior), y el id para desempatar.
            ->orderBy('fecha')
            ->orderBy('id')
            ->get();
    }

    public function updatedMontoRecibido(): void
    {
        $this->repartir();
    }

    public function updatedMoneda(): void
    {
        $this->repartir();
    }

    public function updatedTipoCambio(): void
    {
        $this->repartir();
    }

    /** El monto recibido en Bs: en USD, dolares x tasa. */
    public function recibidoBs(): float
    {
        $monto = (float) ($this->montoRecibido ?: 0);

        return $this->moneda === Moneda::USD->value ? round($monto * (float) ($this->tipo_cambio ?: 0), 2) : round($monto, 2);
    }

    /** Reparte el monto pagado (en Bs) de la primera a la ultima compra del orden. */
    private function repartir(): void
    {
        $resto = max(0, $this->recibidoBs());
        $this->montos = [];

        foreach ($this->comprasConSaldo() as $compra) {
            $monto = round(min($resto, $compra->saldoPendiente()), 2);
            $this->montos[$compra->id] = $monto > 0 ? $monto : '';
            $resto = round($resto - $monto, 2);
        }
    }

    public function totalACobrar(): float
    {
        return round(array_sum(array_map(fn($m) => (float) ($m ?: 0), $this->montos)), 2);
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can('pago-proveedor.create'), 403);

        $this->validate([
            'metodo_pago_id' => 'required|integer|exists:metodos_pago,id,activo,1,sistema,0',
            'moneda' => 'required|in:BOB,USD',
            'tipo_cambio' => 'required_if:moneda,USD|nullable|numeric|min:0.0001',
            'montos.*' => 'nullable|numeric|min:0',
            'nota' => 'nullable|string|max:255',
        ], [
            'metodo_pago_id.required' => 'Elige el método de pago.',
            'metodo_pago_id.exists' => 'Ese método no existe o está desactivado.',
            'montos.*.min' => 'Un monto no puede ser negativo.',
            'tipo_cambio.required_if' => 'Escribe el tipo de cambio del dólar.',
        ]);

        // Solo compras de ESTE proveedor: las claves de $montos llegan del navegador.
        $ids = Compra::where('proveedor_id', $this->proveedorId)
            ->whereKey(array_keys(array_filter($this->montos, fn($m) => (float) ($m ?: 0) > 0)))
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->addError('montos', 'Escribe cuánto se paga al menos en una compra.');

            return;
        }

        // El reintento: la primera peticion ya cerro.
        if ($this->yaGuardado(CompraPago::class)) {
            $this->yaRegistrado();

            return;
        }

        try {
            DB::transaction(function () use ($ids) {
                $servicio = app(PagoProveedorService::class);

                foreach ($ids as $id) {
                    $bs = round((float) $this->montos[$id], 2);
                    $tc = (float) $this->tipo_cambio;
                    $pago = $this->moneda === Moneda::USD->value
                        ? ['moneda' => 'USD', 'monto_moneda' => round($bs / $tc, 2), 'tipo_cambio' => $tc, 'monto' => $bs]
                        : ['moneda' => 'BOB', 'monto' => $bs];

                    $servicio->registrar(
                        Compra::findOrFail($id),
                        [['metodo_pago_id' => (int) $this->metodo_pago_id, 'nota' => $this->nota] + $pago],
                        false,
                        Auth::user(),
                        $this->claveIdempotencia,
                    );
                }
            });
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $this->yaGuardado(CompraPago::class)) {
                $this->yaRegistrado();

                return;
            }

            throw $e;
        } catch (ValidationException $e) {
            // El saldo cambio mientras el modal estaba abierto (otro pago):
            // se recalcula el reparto con lo que hay ahora.
            $this->repartir();

            throw $e;
        }

        toastr()->success('Pago al proveedor registrado: Bs ' . number_format($this->totalACobrar(), 2) . '.');
        $this->despues();
    }

    private function yaRegistrado(): void
    {
        toastr()->info('Este pago ya se había registrado. No se pagó dos veces.');
        $this->despues();
    }

    private function despues(): void
    {
        $this->dispatch('pagosProveedorActualizados');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->reset(['proveedorId', 'compraId', 'montoRecibido', 'moneda', 'tipo_cambio', 'metodo_pago_id', 'nota', 'montos']);
    }

    public function render()
    {
        $data = ['proveedor' => null, 'compras' => collect(), 'metodos' => collect()];

        if ($this->openModal && $this->proveedorId) {
            $data = [
                'proveedor' => Proveedor::find($this->proveedorId),
                'compras' => $this->comprasConSaldo(),
                'metodos' => MetodoPago::activos()->get(),
            ];
        }

        return view('livewire.cuenta-pagar.modals.pago-proveedor-modal', $data);
    }
}
