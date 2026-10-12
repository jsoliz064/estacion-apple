<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Accesorio;
use App\Models\Producto;
use App\Services\ProductoRegalosService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use App\Traits\EligePorCodigoTrait;
use Livewire\Component;

/**
 * Accesorios de regalo de un equipo (reemplazan al viejo "costo de envio"):
 * bajan el stock del accesorio y suben el costo del equipo. Las reglas viven
 * en ProductoRegalosService; el modal elige que, de donde y cuantos.
 */
class ProductoRegalosModal extends Component
{
    use EligePorCodigoTrait;

    public $openModal = false;

    #[Locked]
    public ?int $productoId = null;

    public $busqueda = '';
    public $accesorioId = null;
    public $sucursal_id = '';
    public $cantidad = 1;

    /** Cantidad editable de cada regalo ya cargado, por id de regalo. */
    public array $cantidades = [];

    #[On('openProductoRegalosModal')]
    public function openModal($id): void
    {
        $this->reset();
        $this->resetValidation();
        $this->productoId = (int) $id;
        $this->openModal = true;
        $this->cargarCantidades();
    }

    private function cargarCantidades(): void
    {
        $this->cantidades = Producto::find($this->productoId)?->regalos()
            ->pluck('cantidad', 'id')->map(fn($c) => (int) $c)->all() ?? [];
    }

    public function updatedBusqueda(): void
    {
        $this->accesorioId = null;
        $this->sucursal_id = '';
    }

    public function elegir($accesorioId): void
    {
        $this->accesorioId = (int) $accesorioId;
        $this->busqueda = '';

        // Por defecto, la sucursal del equipo si tiene stock ahi; si no, la de
        // mas stock. Es la que el operador elegiria casi siempre.
        $producto = Producto::find($this->productoId);
        $stocks = Accesorio::find($this->accesorioId)?->stocks()->where('cantidad', '>', 0)->orderByDesc('cantidad')->get() ?? collect();
        $this->sucursal_id = (string) ($stocks->firstWhere('sucursal_id', $producto?->sucursal_id)?->sucursal_id
            ?? $stocks->first()?->sucursal_id
            ?? '');
    }

    /** Enter en el buscador (pistola o camara): un SKU/UPC exacto lo elige. */
    public function elegirPorCodigo(?string $codigo = null): void
    {
        $codigo = trim((string) $codigo);
        $accesorio = $codigo === '' ? null : $this->unicoPorCodigo(
            Accesorio::query()->where('cantidad', '>', 0)
                ->where(fn($q) => $q->where('sku', $codigo)->orWhere('upc', $codigo))
                ->limit(2)
                ->get(),
            $codigo,
            ['sku', 'upc'],
        );

        if ($accesorio) {
            $this->elegir($accesorio->id);

            return;
        }

        $this->busqueda = $codigo;
        $this->updatedBusqueda();

        if ($codigo !== '' && $this->candidatos(null, $codigo)->doesntExist()) {
            toastr()->warning("Ningún accesorio con stock coincide con «{$codigo}».");
        }
    }

    /**
     * Codigo exacto (SKU/UPC) o nombre parcial: el mismo criterio que el
     * buscador de la venta. Sin texto: los compatibles con el modelo del
     * equipo, que son los que se regalan casi siempre.
     */
    private function candidatos(?Producto $producto, string $termino)
    {
        $query = Accesorio::query()->where('cantidad', '>', 0)->orderBy('nombre');

        if ($termino !== '') {
            $query->where(fn($q) => $q->where('nombre', 'like', '%' . addcslashes($termino, '%_\\') . '%')
                ->orWhere('sku', $termino)
                ->orWhere('upc', $termino));
        } elseif ($producto) {
            $query->whereHas('modelosCompatibles', fn($q) => $q->where('productos_modelos.id', $producto->producto_modelo_id));
        }

        return $query;
    }

    public function agregar(): void
    {
        $this->autorizar();

        $this->validate([
            'accesorioId' => 'required|integer|exists:accesorios,id',
            'sucursal_id' => 'required|integer|exists:sucursales,id',
            'cantidad' => 'required|integer|min:1',
        ], [
            'accesorioId.required' => 'Elige el accesorio que se regala.',
            'sucursal_id.required' => 'Elige de qué sucursal sale.',
            'cantidad.min' => 'La cantidad debe ser al menos 1.',
        ]);

        DB::transaction(fn() => app(ProductoRegalosService::class)->agregar(
            $this->productoId,
            (int) $this->accesorioId,
            (int) $this->cantidad,
            (int) $this->sucursal_id,
        ));

        $this->reset(['accesorioId', 'sucursal_id', 'busqueda']);
        $this->cantidad = 1;
        $this->despues('Regalo agregado.');
    }

    public function ajustar($regaloId): void
    {
        $this->autorizar();

        $cantidad = (int) ($this->cantidades[$regaloId] ?? 0);
        DB::transaction(fn() => app(ProductoRegalosService::class)->ajustar((int) $regaloId, $cantidad));

        $this->despues($cantidad < 1 ? 'Regalo quitado.' : 'Cantidad actualizada.');
    }

    public function quitar($regaloId): void
    {
        $this->autorizar();

        DB::transaction(fn() => app(ProductoRegalosService::class)->quitar((int) $regaloId));

        $this->despues('Regalo quitado: las unidades vuelven al stock.');
    }

    private function autorizar(): void
    {
        abort_unless(Auth::user()->can('producto.regalos'), 403);
    }

    private function despues(string $mensaje): void
    {
        $this->cargarCantidades();
        $this->dispatch('refreshProductoTable');
        toastr()->success($mensaje);
    }

    public function closeModal(): void
    {
        $this->reset();
    }

    public function render()
    {
        $producto = null;
        $resultados = collect();
        $elegido = null;
        $origenes = collect();

        if ($this->openModal && $this->productoId) {
            $producto = Producto::with(['modelo', 'regalos.accesorio', 'regalos.sucursal'])->find($this->productoId);

            if (!$this->accesorioId) {
                $resultados = $this->candidatos($producto, trim((string) $this->busqueda))->limit(8)->get();
            }

            if ($this->accesorioId) {
                $elegido = Accesorio::with('stocks.sucursal')->find($this->accesorioId);
                $origenes = $elegido ? $elegido->stocks->filter(fn($s) => (int) $s->cantidad > 0)->sortByDesc('cantidad')->values() : collect();
            }
        }

        return view('livewire.producto.modals.producto-regalos-modal', [
            'producto' => $producto,
            'editable' => $producto && !$producto->estaDadoDeBaja() && !in_array($producto->estado, ProductoEstado::vendidos(), true),
            'resultados' => $resultados,
            'elegido' => $elegido,
            'origenes' => $origenes,
        ]);
    }
}
