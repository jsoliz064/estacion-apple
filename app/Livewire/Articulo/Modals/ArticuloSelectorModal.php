<?php

namespace App\Livewire\Articulo\Modals;

use App\Enums\ArticuloTipo;
use App\Models\AccesorioCategoria;
use App\Models\ProductoModelo;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use App\Traits\EligePorCodigoTrait;
use Livewire\Component;

/**
 * Elegir repuestos y accesorios del catalogo con buscador, filtros y
 * multi-seleccion. Lo usan las pantallas de venta y de compra.
 *
 * Contrato: despacha SOLO pares [tipo, id] (evento `articulosSeleccionados`) y
 * cada padre construye su propia linea con su agregarLinea().
 *
 * Un tipo a la vez (son dos tablas); la seleccion sobrevive al cambiar de tipo.
 */
class ArticuloSelectorModal extends Component
{
    use EligePorCodigoTrait;

    public bool $openModal = false;

    public string $tipo = 'Repuesto';
    public string $search = '';
    public string $filtroCategoria = '';
    public string $filtroModelo = '';

    /** Claves "Tipo:id" marcadas. */
    public array $seleccionados = [];

    /** Claves "Tipo:id" ya cargadas en el documento. */
    #[Locked]
    public array $excluidos = [];

    /** La sucursal del documento: decide QUE stock se muestra. */
    #[Locked]
    public ?int $sucursalId = null;

    /** Venta: un articulo sin stock en la sucursal no se puede elegir. Compra: si. */
    #[Locked]
    public bool $soloConStock = false;

    public int $pagina = 1;
    public int $porPagina = 10;

    #[On('openArticuloSelectorModal')]
    public function openModal(array $excluidos = [], ?int $sucursalId = null, bool $soloConStock = false): void
    {
        $this->reset(['search', 'filtroCategoria', 'filtroModelo', 'seleccionados', 'pagina']);
        $this->excluidos = array_values(array_map('strval', $excluidos));
        $this->sucursalId = $sucursalId;
        $this->soloConStock = $soloConStock;
        $this->openModal = true;
    }

    private function tipoEnum(): ArticuloTipo
    {
        return ArticuloTipo::tryFrom($this->tipo) ?? ArticuloTipo::Repuesto;
    }

    public function updatedTipo(): void
    {
        $this->tipo = $this->tipoEnum()->value;
        $this->reset(['filtroCategoria', 'filtroModelo', 'pagina']);
    }

    public function updatedSearch(): void { $this->pagina = 1; }
    public function updatedFiltroCategoria(): void { $this->pagina = 1; }
    public function updatedFiltroModelo(): void { $this->pagina = 1; }
    public function irAPagina(int $p): void { $this->pagina = max(1, $p); }

    /**
     * Marca o desmarca una fila. Es el UNICO camino: el checkbox es decorativo
     * (sin wire:model), porque con el enlace en la casilla Y el wire:click en el
     * <tr> un clic disparaba los dos y el cambio se anulaba solo.
     */
    public function alternar(int $id): void
    {
        $clave = $this->tipoEnum()->value . ':' . $id;

        if (in_array($clave, $this->seleccionados, true)) {
            $this->seleccionados = array_values(array_diff($this->seleccionados, [$clave]));

            return;
        }

        if ($this->sinStock($this->tipoEnum(), [$id])->isNotEmpty()) {
            toastr()->error('Sin stock en ' . $this->nombreSucursal() . '.');

            return;
        }

        $this->seleccionados[] = $clave;
    }

    /**
     * Enter en el buscador (pistola o camara): un SKU/UPC exacto del tipo que
     * se esta mirando marca la fila, con la misma regla de stock que el clic.
     */
    public function marcarPorCodigo(?string $codigo = null): void
    {
        $codigo = trim((string) $codigo);
        $this->search = $codigo;
        $this->pagina = 1;

        $pagina = $this->consulta();
        $articulo = $this->unicoPorCodigo($pagina->getCollection(), $codigo, ['sku', 'upc']);

        if (!$articulo) {
            if ($codigo !== '' && $pagina->total() === 0) {
                toastr()->warning('Ningún ' . mb_strtolower($this->tipoEnum()->value) . " coincide con «{$codigo}».");
            }

            return;
        }

        if (in_array($this->tipoEnum()->value . ':' . $articulo->id, $this->seleccionados, true)) {
            toastr()->info('Ya está marcado.');
        } else {
            $this->alternar($articulo->id);
        }

        $this->search = '';
    }

    public function agregarSeleccionados(): void
    {
        $items = collect($this->seleccionados)->unique()->map(function ($clave) {
            [$tipo, $id] = explode(':', $clave) + [null, null];

            return ['tipo' => ArticuloTipo::from($tipo)->value, 'id' => (int) $id];
        })->values();

        if ($items->isEmpty()) {
            toastr()->warning('Seleccione al menos un artículo.');

            return;
        }

        // El array llega del cliente: se revalida el stock contra la base.
        foreach (ArticuloTipo::cases() as $tipo) {
            $sinStock = $this->sinStock($tipo, $items->where('tipo', $tipo->value)->pluck('id')->all());

            if ($sinStock->isNotEmpty()) {
                toastr()->error('Sin stock en ' . $this->nombreSucursal() . ': ' . $sinStock->implode(', '));

                return;
            }
        }

        $this->dispatch('articulosSeleccionados', items: $items->all());
        $this->closeModal();
    }

    /** Nombres de los articulos que no tienen stock en la sucursal (si la venta lo exige). */
    private function sinStock(ArticuloTipo $tipo, array $ids)
    {
        if (!$this->soloConStock || $this->sucursalId === null || $ids === []) {
            return collect();
        }

        return ($tipo->modelo())::whereIn('id', $ids)
            ->whereDoesntHave('stocks', fn($q) => $q->where('sucursal_id', $this->sucursalId)->where('cantidad', '>', 0))
            ->pluck('nombre');
    }

    public function nombreSucursal(): string
    {
        return $this->sucursalId ? (Sucursal::whereKey($this->sucursalId)->value('nombre') ?? '#' . $this->sucursalId) : '';
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->reset(['search', 'filtroCategoria', 'filtroModelo', 'seleccionados', 'excluidos', 'pagina', 'sucursalId', 'soloConStock']);
    }

    private function consulta(): LengthAwarePaginator
    {
        $tipo = $this->tipoEnum();
        $esRepuesto = $tipo === ArticuloTipo::Repuesto;
        $excluidos = collect($this->excluidos)
            ->filter(fn($c) => str_starts_with($c, $tipo->value . ':'))
            ->map(fn($c) => (int) substr($c, strlen($tipo->value) + 1))
            ->all();

        return ($tipo->modelo())::query()
            ->with(array_filter([
                'categoria:id,nombre',
                $esRepuesto ? 'modelo:id,nombre' : null,
                // stockEn() mira relationLoaded(): una consulta para la pagina.
                $this->sucursalId ? 'stocks' : null,
            ]))
            ->when($this->sucursalId, fn($q) => $q->with(['stocks' => fn($s) => $s->where('sucursal_id', $this->sucursalId)]))
            ->when($excluidos, fn($q) => $q->whereNotIn('id', $excluidos))
            ->when($this->search !== '', function ($q) {
                $term = '%' . addcslashes($this->search, '%_\\') . '%';
                $q->where(fn($s) => $s->where('nombre', 'like', $term)
                    ->orWhere('sku', $this->search)
                    ->orWhere('upc', $this->search));
            })
            ->when($this->filtroCategoria !== '', fn($q) => $q->where($esRepuesto ? 'repuesto_categoria_id' : 'accesorio_categoria_id', $this->filtroCategoria))
            ->when($this->filtroModelo !== '', function ($q) use ($esRepuesto) {
                $esRepuesto
                    ? $q->where('producto_modelo_id', $this->filtroModelo)
                    : $q->whereHas('modelosCompatibles', fn($m) => $m->where('productos_modelos.id', $this->filtroModelo));
            })
            ->orderBy('nombre')
            // Paginacion manual: WithPagination reescribiria la URL de la pantalla de fondo.
            ->paginate($this->porPagina, ['*'], 'page', $this->pagina);
    }

    public function render()
    {
        $tipo = $this->tipoEnum();
        $data = ['articulos' => null, 'categorias' => collect(), 'modelos' => collect()];

        if ($this->openModal) {
            $data = [
                'articulos' => $this->consulta(),
                'categorias' => $tipo === ArticuloTipo::Repuesto
                    ? RepuestoCategoria::orderBy('nombre')->get(['id', 'nombre'])
                    : AccesorioCategoria::orderBy('nombre')->get(['id', 'nombre']),
                'modelos' => ProductoModelo::orderBy('nombre')->get(['id', 'nombre']),
            ];
        }

        return view('livewire.articulo.modals.articulo-selector-modal', $data + [
            'tipoEnum' => $tipo,
            'nombreSucursal' => $this->nombreSucursal(),
        ]);
    }
}
