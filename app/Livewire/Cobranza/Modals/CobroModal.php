<?php

namespace App\Livewire\Cobranza\Modals;

use App\Enums\Moneda;
use App\Enums\PagoMomento;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\PagoService;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Cobrar a un cliente: lista sus ventas con saldo y se escribe cuanto va a
 * cada una. "Monto recibido" lo reparte de la mas antigua a la mas nueva (o
 * empezando por la venta desde la que se abrio), y cada fila se corrige a mano.
 *
 * Un cobro reparte UNA clave de idempotencia entre los pagos que crea: si la
 * respuesta no llega y se reintenta, el indice vp_clave_idem_venta_metodo_unico lo
 * frena y se avisa "ya se habia registrado" en vez de cobrar dos veces.
 */
class CobroModal extends Component
{
    use GuardadoIdempotenteTrait;

    public bool $openModal = false;

    #[Locked]
    public ?int $clienteId = null;

    /** La venta desde la que se abrio: va primera en el reparto. */
    #[Locked]
    public ?int $ventaId = null;

    /** En la moneda elegida: Bs, o dolares (con su tipo de cambio). */
    public $montoRecibido = '';
    public string $moneda = 'BOB';
    public $tipo_cambio = '';
    public $metodo_pago_id = '';
    public string $nota = '';

    /** venta_id => monto */
    public array $montos = [];

    #[On('openCobroModal')]
    public function openModal($clienteId, $ventaId = null): void
    {
        $this->reset(['montoRecibido', 'moneda', 'metodo_pago_id', 'nota', 'montos', 'ventaId']);
        $this->tipo_cambio = PagoService::ultimoTipoCambio();
        $this->resetErrorBag();

        $this->clienteId = Cliente::findOrFail($clienteId)->id;
        $this->ventaId = $ventaId ? (int) $ventaId : null;
        $this->metodo_pago_id = (string) (MetodoPago::activos()->value('id') ?? '');

        // Una clave por apertura, nunca en render().
        $this->nuevaClaveIdempotencia();

        if ($this->ventaId && $venta = $this->ventasConSaldo()->firstWhere('id', $this->ventaId)) {
            $this->montoRecibido = $venta->saldoPendiente();
            $this->repartir();
        }

        $this->openModal = true;
    }

    /** Sus ventas con saldo, en el orden del reparto. */
    private function ventasConSaldo()
    {
        return Venta::where('cliente_id', $this->clienteId)->conSaldo()
            ->orderByRaw('id = ? DESC', [(int) $this->ventaId])
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

    /** Reparte el monto recibido (en Bs) de la primera a la ultima venta del orden. */
    private function repartir(): void
    {
        $resto = max(0, $this->recibidoBs());
        $this->montos = [];

        foreach ($this->ventasConSaldo() as $venta) {
            $monto = round(min($resto, $venta->saldoPendiente()), 2);
            $this->montos[$venta->id] = $monto > 0 ? $monto : '';
            $resto = round($resto - $monto, 2);
        }
    }

    public function totalACobrar(): float
    {
        return round(array_sum(array_map(fn($m) => (float) ($m ?: 0), $this->montos)), 2);
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can('pago.create'), 403);

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

        // Solo ventas de ESTE cliente: las claves de $montos llegan del navegador.
        $ids = Venta::where('cliente_id', $this->clienteId)
            ->whereKey(array_keys(array_filter($this->montos, fn($m) => (float) ($m ?: 0) > 0)))
            ->orderBy('id')
            ->pluck('id');

        if ($ids->isEmpty()) {
            $this->addError('montos', 'Escribe cuánto se cobra al menos en una venta.');

            return;
        }

        // El reintento: la primera peticion ya cerro.
        if ($this->yaGuardado(VentaPago::class)) {
            $this->yaRegistrado();

            return;
        }

        try {
            DB::transaction(function () use ($ids) {
                $servicio = app(PagoService::class);

                foreach ($ids as $id) {
                    $bs = round((float) $this->montos[$id], 2);
                    $tc = (float) $this->tipo_cambio;
                    $pago = $this->moneda === Moneda::USD->value
                        ? ['moneda' => 'USD', 'monto_moneda' => round($bs / $tc, 2), 'tipo_cambio' => $tc, 'monto' => $bs]
                        : ['moneda' => 'BOB', 'monto' => $bs];

                    $servicio->registrar(
                        Venta::findOrFail($id),
                        [['metodo_pago_id' => (int) $this->metodo_pago_id, 'nota' => $this->nota] + $pago],
                        PagoMomento::Cobro,
                        Auth::user(),
                        $this->claveIdempotencia,
                    );
                }
            });
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $this->yaGuardado(VentaPago::class)) {
                $this->yaRegistrado();

                return;
            }

            throw $e;
        } catch (ValidationException $e) {
            // El saldo cambio mientras el modal estaba abierto (otro cobro):
            // se recalcula el reparto con lo que hay ahora.
            $this->repartir();

            throw $e;
        }

        toastr()->success('Cobro registrado: Bs ' . number_format($this->totalACobrar(), 2) . '.');
        $this->despues();
    }

    private function yaRegistrado(): void
    {
        toastr()->info('Este cobro ya se había registrado. No se cobró dos veces.');
        $this->despues();
    }

    private function despues(): void
    {
        $this->dispatch('pagosActualizados');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->reset(['clienteId', 'ventaId', 'montoRecibido', 'moneda', 'tipo_cambio', 'metodo_pago_id', 'nota', 'montos']);
    }

    public function render()
    {
        $data = ['cliente' => null, 'ventas' => collect(), 'metodos' => collect()];

        if ($this->openModal && $this->clienteId) {
            $data = [
                'cliente' => Cliente::find($this->clienteId),
                'ventas' => $this->ventasConSaldo(),
                'metodos' => MetodoPago::activos()->get(),
            ];
        }

        return view('livewire.cobranza.modals.cobro-modal', $data);
    }
}
