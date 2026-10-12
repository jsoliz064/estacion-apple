<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;
use App\Traits\EligePorCodigoTrait;
use Livewire\Component;

/**
 * Modal para elegir telefonos del inventario con buscador, filtros y
 * multi-seleccion. Es el gemelo de RepuestoSelectorModal, y vive en el
 * namespace del catalogo de productos y no en el de ventas porque manana lo
 * puede abrir otra pantalla.
 *
 * Contrato: despacha SOLO los ids (evento `productosSeleccionados`) y quien
 * escucha construye su propia linea. Un modal que enviara filas ya armadas
 * tendria que conocer el dialecto de cada pantalla que lo usa.
 */
class ProductoSelectorModal extends Component
{
    use EligePorCodigoTrait;

    public bool $openModal = false;

    public string $search = '';
    public string $filtroModelo = '';
    public string $filtroSucursal = '';

    public array $seleccionados = [];
    public array $excluidos = [];

    /** Vendedor o Cliente: decide que precio se muestra, para no mentir. */
    public string $tipoPrecio = 'Vendedor';

    public int $pagina = 1;
    public int $porPagina = 10;

    /**
     * Los excluidos llegan frescos en CADA apertura y el modal se cierra al
     * agregar, asi que su lista nunca puede quedar obsoleta.
     */
    #[On('openProductoSelectorModal')]
    public function openModal(array $excluidos = [], $sucursalId = null, string $tipoPrecio = 'Vendedor'): void
    {
        $this->resetEstado();
        $this->excluidos = array_map('intval', $excluidos);

        // Preseleccionada, no impuesta: se puede quitar el filtro para ver las
        // otras tiendas, que es lo que la pantalla de crear ya permite.
        $this->filtroSucursal = (string) ($sucursalId ?? '');
        $this->tipoPrecio = $tipoPrecio;
        $this->openModal = true;
    }

    public function updatedSearch(): void { $this->pagina = 1; }
    public function updatedFiltroModelo(): void { $this->pagina = 1; }
    public function updatedFiltroSucursal(): void { $this->pagina = 1; }

    public function irAPagina(int $p): void { $this->pagina = max(1, $p); }

    public function limpiarSeleccion(): void { $this->seleccionados = []; }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'filtroModelo', 'filtroSucursal', 'pagina']);
    }

    /**
     * Enter en el buscador (pistola o camara): un IMEI o SKU exacto marca ese
     * equipo y deja el campo listo para el siguiente. El UPC no basta: es el
     * del modelo, y deja la lista filtrada para elegir a mano.
     */
    public function marcarPorCodigo(?string $codigo = null): void
    {
        $codigo = trim((string) $codigo);
        $this->search = $codigo;
        $this->pagina = 1;

        $productos = $this->productosQuery();
        $producto = $this->unicoPorCodigo($productos->getCollection(), $codigo, ['imei', 'sku']);

        if (!$producto) {
            if ($codigo !== '' && $productos->total() === 0) {
                toastr()->warning("Ningún equipo disponible coincide con «{$codigo}».");
            }

            return;
        }

        if (in_array((string) $producto->id, array_map('strval', $this->seleccionados), true)) {
            toastr()->info('Ese equipo ya está marcado.');
        } else {
            $this->seleccionados[] = $producto->id;
        }

        $this->search = '';
    }

    public function agregarSeleccionados(): void
    {
        $ids = array_values(array_unique(array_map('intval', $this->seleccionados)));

        if (empty($ids)) {
            toastr()->warning('Seleccione al menos un producto.');
            return;
        }

        $this->dispatch('productosSeleccionados', ids: $ids);
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->resetEstado();
    }

    /** Siempre reset dirigido, nunca $this->reset() a secas. */
    protected function resetEstado(): void
    {
        $this->reset([
            'search', 'filtroModelo', 'filtroSucursal',
            'seleccionados', 'excluidos', 'tipoPrecio', 'pagina',
        ]);
    }

    public function esVendedor(): bool
    {
        return $this->tipoPrecio !== 'Cliente';
    }

    protected function productosQuery(): LengthAwarePaginator
    {
        return Producto::query()
            ->with(['modelo:id,nombre', 'sucursal:id,nombre'])
            // Inventario y sin baja: es lo mismo que revalida VentaService
            // al confirmar la venta.
            ->disponibles()
            ->when($this->excluidos, fn($q) => $q->whereNotIn('id', $this->excluidos))
            ->when($this->search !== '', function ($q) {
                // Neutraliza los comodines para que un '%' escrito se busque literal.
                $term = '%' . addcslashes($this->search, '%_\\') . '%';

                // Un solo cuadro para IMEI, modelo, capacidad, color y
                // descripcion: en el mostrador no se sabe de antemano por cual
                // de los cinco se esta buscando. El SKU y el UPC, exactos: es
                // lo que lee la pistola.
                $q->where(fn($sub) => $sub->where('imei', 'like', $term)
                    ->orWhere('sku', $this->search)
                    ->orWhere('upc', $this->search)
                    ->orWhere('descripcion', 'like', $term)
                    ->orWhere('almacenamiento', 'like', $term)
                    ->orWhere('color', 'like', $term)
                    ->orWhereHas('modelo', fn($m) => $m->where('nombre', 'like', $term)));
            })
            ->when($this->filtroModelo !== '', fn($q) => $q->where('producto_modelo_id', $this->filtroModelo))
            ->when($this->filtroSucursal !== '', fn($q) => $q->where('sucursal_id', $this->filtroSucursal))
            // Agrupado por modelo, que es como se busca un telefono. La
            // subconsulta ordena por el nombre sin necesidad de un join.
            ->orderBy(
                ProductoModelo::select('nombre')
                    ->whereColumn('productos_modelos.id', 'productos.producto_modelo_id')
            )
            ->orderBy('almacenamiento')
            // Paginacion manual: WithPagination registra `page` en el query
            // string con history:true y reescribiria la URL de la venta.
            ->paginate($this->porPagina, ['*'], 'page', $this->pagina);
    }

    public function render()
    {
        // Los catalogos van en render(), NUNCA en mount(): son variables de
        // vista, no propiedades publicas, asi que reset() no puede vaciarlos ni
        // viajan en el payload de Livewire. Con el modal cerrado, 0 consultas.
        $data = ['modelos' => collect(), 'sucursales' => collect(), 'productos' => null];

        if ($this->openModal) {
            $data = [
                'modelos' => ProductoModelo::orderBy('nombre')->get(),
                'sucursales' => Sucursal::orderBy('nombre')->get(),
                'productos' => $this->productosQuery(),
            ];
        }

        return view(
            'livewire.producto.modals.producto-selector-modal',
            $data
        );
    }
}
