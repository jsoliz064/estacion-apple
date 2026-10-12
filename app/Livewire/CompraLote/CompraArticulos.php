<?php

namespace App\Livewire\CompraLote;

use App\Enums\ArticuloTipo;
use App\Enums\LineaTipo;
use App\Models\Compra;
use App\Services\CompraService;
use App\Traits\CarritoBuscadorTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Los repuestos y accesorios de una compra, en su detalle. Cada accion se
 * GUARDA en el acto (CompraService::guardarArticulo / quitarArticulo), cada una
 * en su transaccion: un corte de internet o una recarga pierde a lo sumo la
 * ultima tecla. Antes eran un carrito en memoria dentro de CompraForm.
 *
 * En borrador las lineas no tocan el stock; en una compra finalizada cada
 * cambio lo mueve (lo decide el servicio).
 */
class CompraArticulos extends Component
{
    use CarritoBuscadorTrait;

    #[Locked]
    public int $compraId;

    #[Locked]
    public ?int $sucursalId = null;

    /** Lo editable de cada linea: [detalle_id => ['cantidad', 'costo']]. */
    public array $lineas = [];

    public function mount(int $compraId): void
    {
        $compra = Compra::findOrFail($compraId);
        $this->compraId = $compra->id;
        $this->sucursalId = $compra->sucursal_id;
        $this->cargar();
    }

    private function cargar(): void
    {
        $this->lineas = Compra::findOrFail($this->compraId)->detalles()->whereNull('producto_id')->get()
            ->mapWithKeys(fn($d) => [$d->id => ['cantidad' => (int) $d->cantidad, 'costo' => (float) $d->costo]])
            ->all();
    }

    /**
     * La unica puerta de escritura: permiso, compra bloqueada y servicio. Un
     * rechazo del servicio (unidades ya vendidas, total bajo lo pagado) se
     * muestra y la pantalla vuelve a lo que hay en la base.
     */
    private function escribir(callable $accion): bool
    {
        abort_unless(Auth::user()?->can('compra.edit'), 403);

        try {
            DB::transaction(fn() => $accion(Compra::lockForUpdate()->findOrFail($this->compraId), app(CompraService::class)));
            $ok = true;
        } catch (ValidationException $e) {
            $mensaje = implode(' ', $e->validator->errors()->all());
            $this->addError('detalles', $mensaje);
            toastr()->error($mensaje);
            $ok = false;
        }

        $this->cargar();
        $this->dispatch('refreshCompraDetalle');

        return $ok;
    }

    // ------------------------------------------------------------ buscador

    protected function tiposBuscables(): array
    {
        return [LineaTipo::Repuesto, LineaTipo::Accesorio];
    }

    protected function esVenta(): bool
    {
        return false;
    }

    public function sucursalDelDocumento(): ?int
    {
        return $this->sucursalId;
    }

    /**
     * Vacio a proposito: elegir un articulo que ya esta en la compra le SUMA
     * una unidad (escanear diez fundas iguales con la pistola), en vez del
     * «Ya está en la lista» del carrito de venta.
     */
    protected function clavesEnCarrito(): array
    {
        return [];
    }

    protected function agregarLinea(string $tipo, int $id): void
    {
        $articuloTipo = LineaTipo::from($tipo)->articulo();
        $articulo = $articuloTipo?->buscar($id);

        if (!$articulo) {
            return;
        }

        $this->escribir(function (Compra $compra, CompraService $servicio) use ($articuloTipo, $articulo) {
            $actual = $compra->detalles()->where($articuloTipo->columna(), $articulo->id)->first();

            $servicio->guardarArticulo($compra, [
                'tipo' => $articuloTipo->value,
                'id' => $articulo->id,
                'cantidad' => (int) ($actual?->cantidad ?? 0) + 1,
                'costo' => $actual ? (float) $actual->costo : (float) $articulo->costo,
            ]);
        });
    }

    /** Alta al vuelo desde el modal de articulo (sin stock: entra por esta compra). */
    #[On('articuloCreado')]
    public function articuloCreado(string $tipo, int $id): void
    {
        $this->seleccionarResultado($tipo, $id);
    }

    public function abrirCrearArticulo(string $tipo): void
    {
        $this->dispatch('openArticuloCreateModal', tipo: ArticuloTipo::from($tipo)->value);
    }

    // -------------------------------------------------------------- lineas

    /** wire:model.blur de cantidad o costo: se guarda esa linea. */
    public function updatedLineas($valor, $clave): void
    {
        $this->guardarLinea((int) explode('.', (string) $clave)[0]);
    }

    public function guardarLinea(int $detalleId): void
    {
        if (!isset($this->lineas[$detalleId])) {
            return;
        }

        $this->validate([
            "lineas.{$detalleId}.cantidad" => 'required|integer|min:1',
            "lineas.{$detalleId}.costo" => 'required|numeric|min:0',
        ], [
            "lineas.{$detalleId}.cantidad.min" => 'La cantidad debe ser al menos 1.',
            "lineas.{$detalleId}.cantidad.*" => 'Indica una cantidad entera.',
            "lineas.{$detalleId}.costo.*" => 'El costo no puede ser negativo.',
        ]);

        $linea = $this->lineas[$detalleId];

        $this->escribir(function (Compra $compra, CompraService $servicio) use ($detalleId, $linea) {
            $detalle = $compra->detalles()->whereNull('producto_id')->whereKey($detalleId)->firstOrFail();
            $tipo = $detalle->tipoLinea();

            $servicio->guardarArticulo($compra, [
                'tipo' => $tipo->value,
                'id' => $detalle->{$tipo->columna()},
                'cantidad' => (int) $linea['cantidad'],
                'costo' => (float) $linea['costo'],
            ]);
        });
    }

    public function quitar(int $detalleId): void
    {
        $this->escribir(fn(Compra $compra, CompraService $servicio) => $servicio->quitarArticulo($compra, $detalleId));
    }

    public function render()
    {
        $compra = Compra::with(['detalles' => fn($q) => $q->whereNull('producto_id')->with(['repuesto', 'accesorio'])->orderByDesc('id')])
            ->findOrFail($this->compraId);

        return view('livewire.compra-lote.compra-articulos', [
            'compra' => $compra,
            'detalles' => $compra->detalles,
        ]);
    }
}
