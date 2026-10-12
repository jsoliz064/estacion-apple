<?php

namespace App\Livewire\Compra;

use App\Models\Compra;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Services\CompraService;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * La CABECERA de una compra: proveedor, fecha y sucursal. Crear deja la compra
 * en borrador y lleva al detalle, donde se cargan los equipos, los repuestos y
 * los accesorios linea por linea, y donde se finaliza con lo pagado al recibir.
 *
 * Antes este formulario traia el carrito de articulos y las filas de pago: una
 * compra de 50 equipos y decenas de articulos se perdia entera si se cortaba
 * internet o se recargaba la pagina antes del «Guardar».
 *
 * La sucursal se elige al crear y no cambia: es a donde entra el stock.
 */
class CompraForm extends Component
{
    use GuardadoIdempotenteTrait;

    /** Null al crear. #[Locked]: decide que compra se reescribe. */
    #[Locked]
    public ?int $compraId = null;

    public array $compra = [];

    public function mount(?int $compraId = null): void
    {
        if ($compraId) {
            abort_unless(Auth::user()?->can('compra.edit'), 403);

            $compra = Compra::findOrFail($compraId);
            $this->compraId = $compra->id;
            $this->compra = [
                'proveedor_id' => $compra->proveedor_id,
                'fecha' => $compra->fecha->toDateString(),
                'sucursal_id' => $compra->sucursal_id,
            ];

            return;
        }

        abort_unless(Auth::user()?->can('compra.create'), 403);

        // Una clave por apertura: reintentar no crea una segunda compra.
        $this->nuevaClaveIdempotencia();
        $this->compra = ['proveedor_id' => null, 'fecha' => now()->toDateString(), 'sucursal_id' => null];
    }

    public function esEdicion(): bool
    {
        return $this->compraId !== null;
    }

    protected function rules(): array
    {
        return [
            'compra.proveedor_id' => 'required|integer|exists:proveedores,id',
            'compra.fecha' => 'required|date',
            'compra.sucursal_id' => $this->esEdicion() ? 'nullable' : 'required|integer|exists:sucursales,id,activa,1',
        ];
    }

    protected function messages(): array
    {
        return [
            'compra.proveedor_id.required' => 'Elige el proveedor.',
            'compra.fecha.required' => 'Indica la fecha de la compra.',
            'compra.sucursal_id.required' => 'Elige la sucursal a la que entra la compra.',
            'compra.sucursal_id.exists' => 'La sucursal no existe o está desactivada.',
        ];
    }

    public function guardar()
    {
        $this->validate();

        $servicio = app(CompraService::class);

        if ($this->esEdicion()) {
            abort_unless(Auth::user()?->can('compra.edit'), 403);

            DB::transaction(fn() => $servicio->actualizar(
                Compra::lockForUpdate()->findOrFail($this->compraId),
                $this->compra,
            ));

            toastr()->success('Compra actualizada');

            return redirect()->route('compras.detalle', $this->compraId);
        }

        abort_unless(Auth::user()?->can('compra.create'), 403);

        // El reintento, resuelto antes de abrir la transaccion.
        if ($ya = $this->yaGuardado(Compra::class)) {
            $this->avisarYaGuardado($ya, 'compra');

            return redirect()->route('compras.detalle', $ya->id);
        }

        try {
            $compra = DB::transaction(fn() => $servicio->crear($this->compra, Auth::user(), $this->claveIdempotencia));
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Compra::class)) {
                $this->avisarYaGuardado($ya, 'compra');

                return redirect()->route('compras.detalle', $ya->id);
            }

            throw $e;
        }

        toastr()->success('Compra creada en borrador: ahora carga los equipos y artículos.');

        return redirect()->route('compras.detalle', $compra->id);
    }

    public function render()
    {
        return view('livewire.compra.compra-form', [
            'proveedores' => Proveedor::orderBy('nombre')->get(['id', 'nombre']),
            'sucursales' => $this->esEdicion()
                ? Sucursal::paraSelect($this->compra['sucursal_id'] ?? null)
                : Sucursal::activas()->orderBy('nombre')->get(),
        ]);
    }
}
