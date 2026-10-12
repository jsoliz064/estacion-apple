<?php

namespace App\Livewire\Reserva\Modals;

use App\Enums\LineaTipo;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Reserva;
use App\Services\BuscadorArticulosService;
use App\Services\ReservaService;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Reservar un equipo para un cliente con una seña (ReservaService::crear).
 * Se abre con el equipo (desde el modal de estado) o sin el (desde la pantalla
 * de reservas), y entonces se busca por IMEI o SKU, tambien con el lector.
 */
class ReservaCreateModal extends Component
{
    use ClienteBuscadorTrait;
    use GuardadoIdempotenteTrait;

    public bool $openModal = false;

    public ?int $productoId = null;
    public ?int $clienteId = null;
    public ?string $clienteNombre = null;
    public $sena = '';
    public $metodo_pago_id = '';
    public string $nota = '';

    public string $buscarEquipo = '';
    public array $equipos = [];

    #[On('openReservaCreateModal')]
    public function openModal($productoId = null): void
    {
        $this->reset(['productoId', 'clienteId', 'clienteNombre', 'sena', 'nota', 'buscarEquipo', 'equipos', 'searchCliente', 'filteredClientes']);
        $this->resetErrorBag();

        if ($productoId && Producto::disponibles()->whereKey($productoId)->exists()) {
            $this->productoId = (int) $productoId;
        }

        $this->metodo_pago_id = (string) (MetodoPago::activos()->value('id') ?? '');
        $this->nuevaClaveIdempotencia();
        $this->openModal = true;
    }

    protected function fijarCliente(?Cliente $cliente): void
    {
        $this->clienteId = $cliente?->id;
        $this->clienteNombre = $cliente?->nombre;
    }

    public function clienteIdElegido(): ?int
    {
        return $this->clienteId;
    }

    public function updatedBuscarEquipo($valor): void
    {
        $this->equipos = app(BuscadorArticulosService::class)
            ->buscar((string) $valor, [LineaTipo::Producto], null, true)->all();
    }

    /** Enter en el buscador (pistola o camara): IMEI o SKU exacto lo elige. */
    public function elegirEquipoPorCodigo(?string $codigo = null): void
    {
        $codigo = trim((string) $codigo);
        $fila = app(BuscadorArticulosService::class)->porCodigo($codigo, [LineaTipo::Producto], null, true);

        if ($fila) {
            $this->elegirEquipo($fila['id']);

            return;
        }

        $this->buscarEquipo = $codigo;
        $this->updatedBuscarEquipo($codigo);

        if ($codigo !== '' && $this->equipos === []) {
            toastr()->warning("Ningún equipo disponible coincide con «{$codigo}».");
        }
    }

    public function elegirEquipo($id): void
    {
        $this->productoId = Producto::disponibles()->whereKey($id)->value('id');
        $this->buscarEquipo = '';
        $this->equipos = [];
    }

    public function quitarEquipo(): void
    {
        $this->productoId = null;
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can('reserva.create'), 403);

        $this->validate([
            'productoId' => 'required|integer',
            'clienteId' => 'required|integer|exists:clientes,id',
            'sena' => 'required|numeric|min:0.01',
            'metodo_pago_id' => 'required|integer',
            'nota' => 'nullable|string|max:255',
        ], [
            'productoId.required' => 'Elige el equipo que se reserva.',
            'clienteId.required' => 'Elige el cliente que reserva.',
            'sena.required' => 'Escribe el monto de la seña.',
            'sena.min' => 'La seña tiene que ser mayor a cero.',
            'metodo_pago_id.required' => 'Elige el método de pago de la seña.',
        ]);

        if ($ya = $this->yaGuardado(Reserva::class)) {
            toastr()->info("Esta reserva ya se había registrado (#{$ya->id}). No se creó otra.");
            $this->despues();

            return;
        }

        try {
            $reserva = DB::transaction(fn() => app(ReservaService::class)->crear(
                $this->productoId, $this->clienteId, (float) $this->sena, (int) $this->metodo_pago_id,
                $this->nota, Auth::user(), $this->claveIdempotencia,
            ));
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Reserva::class)) {
                toastr()->info("Esta reserva ya se había registrado (#{$ya->id}). No se creó otra.");
                $this->despues();

                return;
            }

            throw $e;
        }

        toastr()->success("Equipo reservado (reserva #{$reserva->id}).");
        $this->despues();
    }

    private function despues(): void
    {
        $this->dispatch('reservasActualizadas');
        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->reset(['productoId', 'clienteId', 'clienteNombre', 'sena', 'nota', 'buscarEquipo', 'equipos']);
    }

    public function render()
    {
        return view('livewire.reserva.modals.reserva-create-modal', [
            'producto' => $this->openModal && $this->productoId ? Producto::with('modelo')->find($this->productoId) : null,
            'metodos' => $this->openModal ? MetodoPago::activos()->get() : collect(),
        ]);
    }
}
