<?php

namespace App\Livewire\Catalogo;

use App\Enums\ProductoAlmacenamiento;
use App\Enums\ProductoColor;
use App\Enums\ProductoEstado;
use App\Models\Producto;
use App\Models\ProductoModelo;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\WithPagination;

class CatalogoIndex extends Component
{
    use WithPagination;

    public $search = '';
    public $filterStorage = '';
    public $filterModel = '';
    public $selectedProduct = null;
    public $currentImageIndex = 0;
    public $models = [];
    public $availableModels = 0;
    public $soldOutModels = 0;
    public $minPriceLimit = 0;
    public $maxPriceLimit = 0;
    public $priceAuth = 'precio_cliente';

    public $storageOptionsByModel = [];

    protected $queryString = [
        'search' => ['except' => ''],
        'filterStorage' => ['except' => ''],
        'filterColor' => ['except' => ''],
        'filterModel' => ['except' => ''],
        'filterPriceRange' => ['except' => [0, 0]],
        'sortBy' => ['except' => 'newest'],
    ];


    public function mount()
    {
        $this->priceAuth = auth()->check() && auth()->user()->hasRole('Vendedor')
            ? 'precio_vendedor'
            : 'precio_cliente';
    }

    public function updatedFilterModel($value)
    {
        $this->reset('filterStorage');
        $this->resetPage();

        if ($value) {
            $this->loadStorageOptions($value);
        } else {
            $this->storageOptionsByModel = [];
        }
    }

    protected function loadStorageOptions($modelId)
    {
        $query = Producto::query()
            ->where('producto_modelo_id', $modelId)
            ->whereNotIn('productos.estado', ProductoEstado::fueraDeCatalogo())->whereNull('productos.dado_de_baja_at')
            ->where(function ($q) {
                $q->where('disponible_catalogo', true)
                    ->orWhereIn('estado', ProductoEstado::disponibles());
            });

        $this->storageOptionsByModel = $query
            ->select('almacenamiento', DB::raw('count(*) as count'))
            ->groupBy('almacenamiento')
            ->orderBy('almacenamiento')
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->almacenamiento => $item->count];
            })
            ->toArray();
    }

    public function filterByStorage($storage)
    {
        $this->filterStorage = $storage;
        $this->resetPage();
    }

    public function selectProduct($productId)
    {
        // Solo lo publicable: un id viejo (vendido, reservado, dado de baja)
        // no se abre aunque llegue por la peticion.
        $this->selectedProduct = Producto::with('imagenes')
            ->whereNotIn('productos.estado', ProductoEstado::fueraDeCatalogo())
            ->whereNull('productos.dado_de_baja_at')
            ->find($productId);
        $this->currentImageIndex = 0;
        $this->dispatch('product-selected');
    }

    public function closeProductDetail()
    {
        $this->selectedProduct = null;
    }

    public function nextImage()
    {
        if ($this->selectedProduct && $this->selectedProduct->imagenes->count() > 0) {
            $this->currentImageIndex = ($this->currentImageIndex + 1) % $this->selectedProduct->imagenes->count();
        }
    }

    public function prevImage()
    {
        if ($this->selectedProduct && $this->selectedProduct->imagenes->count() > 0) {
            $this->currentImageIndex = ($this->currentImageIndex - 1 + $this->selectedProduct->imagenes->count()) % $this->selectedProduct->imagenes->count();
        }
    }

    public function selectImage($index)
    {
        $this->currentImageIndex = $index;
    }

    public function resetFilters()
    {
        $this->reset(['search', 'filterStorage', 'filterModel', 'storageOptionsByModel']);
        $this->resetPage();
    }

    public function selectModel($modelId)
    {
        $this->filterModel = $modelId;
        $this->loadStorageOptions($modelId);
        $this->reset('filterStorage');
        $this->resetPage();
    }

    public function render()
    {
        $this->models = ProductoModelo::withCount([
            'productos as productos_disponibles_count' => function ($query) {
                $query->whereNotIn('productos.estado', ProductoEstado::fueraDeCatalogo())->whereNull('productos.dado_de_baja_at')
                    ->where(function ($q) {
                        $q->where('disponible_catalogo', 1)
                            ->orWhereIn('estado', ProductoEstado::disponibles());
                    });
            },
        ])->get();

        $this->availableModels = $this->models->where('productos_disponibles_count', '>', 0);
        $this->soldOutModels = $this->models->where('productos_disponibles_count', 0);

        $query = Producto::query()
            ->with(['imagenes', 'modelo'])
            ->whereNotIn('productos.estado', ProductoEstado::fueraDeCatalogo())->whereNull('productos.dado_de_baja_at')
            ->where(function ($q) {
                $q->where('disponible_catalogo', 1)
                    ->orWhereIn('estado', ProductoEstado::disponibles());
            });
        $filtersActive = $this->search || $this->filterModel;

        if (!$filtersActive) {
            $query->where('venta_rapida', true)
                ->orderByDesc('venta_rapida');
        } else {
            if ($this->search) {
                $query->where(function ($q) {
                    $q->whereHas('modelo', function ($modelQuery) {
                        $modelQuery->where('nombre', 'like', '%' . $this->search . '%');
                    })
                        ->orWhere('imei', 'like', '%' . $this->search . '%')
                        ->orWhere('descripcion', 'like', '%' . $this->search . '%')
                        ->orWhere('almacenamiento', 'like', '%' . $this->search . '%')
                        ->orWhere('color', 'like', '%' . $this->search . '%');
                });
            } else {

                if ($this->filterModel) {
                    $query->whereHas('modelo', function ($q) {
                        $q->where('id', $this->filterModel);
                    });
                }
                if ($this->filterStorage) {
                    $query->where('almacenamiento', $this->filterStorage);
                }
            }
        }

        $products = $query->paginate(12);

        return view('livewire.catalogo.catalogo-index', [
            'products' => $products,
            'storageOptions' => ProductoAlmacenamiento::cases(),
            'filtersActive' => $filtersActive,
            'storageOptionsByModel' => $this->storageOptionsByModel,
        ]);
    }
}
