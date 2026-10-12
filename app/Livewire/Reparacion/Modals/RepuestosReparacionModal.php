<?php

namespace App\Livewire\Reparacion\Modals;

use App\Models\ProductoModelo;
use App\Models\Repuesto;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use App\Traits\EligePorCodigoTrait;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Elegir las piezas que se montan en una reparacion: sucursal de donde salen,
 * filtros, buscador con escaner y multi-seleccion. Antes era una seccion
 * incrustada (y copiada tres veces) en los modales de estado, de editar la
 * reparacion y de garantia/trabajo externo: ocupaba medio modal en el celular.
 *
 * Contrato: despacha `repuestosReparacionElegidos(origen, sucursalId, ids)` y
 * cada modal arma sus lineas (RepuestosReparacionFormTrait). `origen` es el id
 * del componente que lo abrio: en la pantalla de productos los tres modales
 * estan montados a la vez y solo el que pidio debe recibir la respuesta.
 */
class RepuestosReparacionModal extends Component
{
    use EligePorCodigoTrait;

    public bool $openModal = false;

    #[Locked]
    public string $origen = '';

    /** repuesto_id ya cargados en la reparacion. */
    #[Locked]
    public array $excluidos = [];

    public $sucursalId = '';
    public string $search = '';
    public $filtroCategoria = '';
    public $filtroModelo = '';

    /** repuesto_id marcados. */
    public array $seleccionados = [];

    public int $pagina = 1;
    public int $porPagina = 10;

    #[On('openRepuestosReparacionModal')]
    public function openModal(string $origen, array $excluidos = [], $modeloId = null, $sucursalId = null): void
    {
        $this->reset(['search', 'filtroCategoria', 'seleccionados', 'pagina']);
        $this->resetErrorBag();
        $this->origen = $origen;
        $this->excluidos = array_values(array_map('intval', $excluidos));
        $this->filtroModelo = $modeloId ? (string) $modeloId : '';
        // La sucursal del equipo, solo si sigue activa: es donde suelen estar las piezas.
        $this->sucursalId = $sucursalId && Sucursal::activas()->whereKey($sucursalId)->exists() ? (string) $sucursalId : '';
        $this->openModal = true;
    }

    private function sucursal(): ?int
    {
        return $this->sucursalId !== '' && $this->sucursalId !== null ? (int) $this->sucursalId : null;
    }

    /** Lo marcado se valido contra la sucursal anterior: se empieza de nuevo. */
    public function updatedSucursalId(): void
    {
        $this->seleccionados = [];
        $this->resetErrorBag('sucursalId');
    }

    public function updatedSearch(): void { $this->pagina = 1; }
    public function updatedFiltroCategoria(): void { $this->pagina = 1; }
    public function updatedFiltroModelo(): void { $this->pagina = 1; }
    public function irAPagina(int $p): void { $this->pagina = max(1, $p); }

    /** Marca o desmarca una fila (el checkbox es decorativo, como en ArticuloSelectorModal). */
    public function alternar(int $id): void
    {
        if (in_array($id, $this->seleccionados, true)) {
            $this->seleccionados = array_values(array_diff($this->seleccionados, [$id]));

            return;
        }

        if ($this->sucursal() === null) {
            $this->addError('sucursalId', 'Elija primero la sucursal de donde salen las piezas.');

            return;
        }

        if ($this->sinStock([$id])->isNotEmpty()) {
            toastr()->error('Sin stock en ' . $this->nombreSucursal() . '.');

            return;
        }

        $this->seleccionados[] = $id;
    }

    /** Enter en el buscador (pistola o camara): un SKU/UPC exacto marca la pieza. */
    public function marcarPorCodigo(?string $codigo = null): void
    {
        $codigo = trim((string) $codigo);
        $this->search = $codigo;
        $this->pagina = 1;

        $pagina = $this->consulta();
        $repuesto = $this->unicoPorCodigo($pagina->getCollection(), $codigo, ['sku', 'upc']);

        if (!$repuesto) {
            if ($codigo !== '' && $pagina->total() === 0) {
                toastr()->warning("Ningún repuesto coincide con «{$codigo}».");
            }

            return;
        }

        if (in_array($repuesto->id, $this->seleccionados, true)) {
            toastr()->info('Ya está marcado.');
        } else {
            $this->alternar($repuesto->id);
        }

        $this->search = '';
    }

    public function agregar(): void
    {
        $sucursalId = $this->sucursal();

        if ($sucursalId === null || !Sucursal::activas()->whereKey($sucursalId)->exists()) {
            $this->addError('sucursalId', 'Elija una sucursal activa de donde salen las piezas.');

            return;
        }

        $ids = array_values(array_unique(array_map('intval', $this->seleccionados)));

        if ($ids === []) {
            toastr()->warning('Seleccione al menos un repuesto.');

            return;
        }

        // El array llega del cliente: se revalida contra la base. El stock de
        // verdad lo protege igual el retirar() del guardado.
        $sinStock = $this->sinStock($ids);

        if ($sinStock->isNotEmpty()) {
            toastr()->error('Sin stock en ' . $this->nombreSucursal() . ': ' . $sinStock->implode(', '));

            return;
        }

        $this->dispatch('repuestosReparacionElegidos', origen: $this->origen, sucursalId: $sucursalId, ids: $ids);
        $this->closeModal();
    }

    /** Nombres de las piezas sin stock en la sucursal elegida. */
    private function sinStock(array $ids)
    {
        return Repuesto::whereIn('id', $ids)
            ->whereDoesntHave('stocks', fn($q) => $q->where('sucursal_id', $this->sucursal())->where('cantidad', '>', 0))
            ->pluck('nombre');
    }

    public function nombreSucursal(): string
    {
        return $this->sucursal() ? (Sucursal::whereKey($this->sucursal())->value('nombre') ?? '') : '';
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->reset(['origen', 'excluidos', 'sucursalId', 'search', 'filtroCategoria', 'filtroModelo', 'seleccionados', 'pagina']);
    }

    private function consulta(): LengthAwarePaginator
    {
        $sucursalId = $this->sucursal();

        return Repuesto::query()
            ->with(['categoria:id,nombre', 'modelo:id,nombre'])
            // stockEn() mira relationLoaded(): una consulta para la pagina.
            ->when($sucursalId, fn($q) => $q->with(['stocks' => fn($s) => $s->where('sucursal_id', $sucursalId)]))
            ->when($this->excluidos, fn($q) => $q->whereNotIn('id', $this->excluidos))
            ->when($this->search !== '', function ($q) {
                $term = '%' . addcslashes($this->search, '%_\\') . '%';
                // SKU y UPC exactos: es lo que lee la pistola.
                $q->where(fn($s) => $s->where('nombre', 'like', $term)
                    ->orWhere('sku', $this->search)
                    ->orWhere('upc', $this->search)
                    ->orWhere('fabricante', 'like', $term));
            })
            ->when($this->filtroCategoria !== '' && $this->filtroCategoria !== null, fn($q) => $q->where('repuesto_categoria_id', $this->filtroCategoria))
            ->when($this->filtroModelo !== '' && $this->filtroModelo !== null, fn($q) => $q->where('producto_modelo_id', $this->filtroModelo))
            ->orderBy('nombre')
            // Paginacion manual: WithPagination reescribiria la URL de la pantalla de fondo.
            ->paginate($this->porPagina, ['*'], 'page', $this->pagina);
    }

    public function render()
    {
        $data = ['repuestos' => null, 'categorias' => collect(), 'modelos' => collect(), 'sucursales' => collect()];

        if ($this->openModal) {
            $data = [
                'repuestos' => $this->consulta(),
                'categorias' => RepuestoCategoria::orderBy('nombre')->get(['id', 'nombre']),
                'modelos' => ProductoModelo::orderBy('nombre')->get(['id', 'nombre']),
                'sucursales' => Sucursal::activas()->orderBy('nombre')->get(['id', 'nombre']),
            ];
        }

        return view('livewire.reparacion.modals.repuestos-reparacion-modal', $data + [
            'nombreSucursal' => $this->nombreSucursal(),
        ]);
    }
}
