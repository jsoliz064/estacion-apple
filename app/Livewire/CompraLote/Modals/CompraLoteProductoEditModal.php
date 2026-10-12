<?php

namespace App\Livewire\CompraLote\Modals;

use App\Enums\Moneda;
use App\Enums\ProductoColor;
use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Enums\ProductoTipoVenta;
use App\Enums\ProductoVersion;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Sucursal;
use App\Services\CompraService;
use App\Services\EstadoProductoService;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;


class CompraLoteProductoEditModal extends Component
{
    use WithFileUploads;

    public $openModal = false;
    public $selectedModel;

    public $descripcion;
    public $colores = [];
    public $sucursales = [];

    /**
     * El carrusel muestra primero las guardadas ([id, url]) y despues las
     * nuevas, archivos subidos por la camara (subirFoto() en app.js) que se
     * guardan al actualizar. Quitar una guardada la anota en photosToDelete.
     */
    public array $fotosGuardadas = [];
    public $fotoNueva;
    public array $fotos = [];
    public $photosToDelete = [];

    public $currentPhotoIndex = 0;
    public $producto;

    /** El equipo espera en En compra: el select edita el estado al finalizar. */
    public bool $enBorrador = false;


    public function render()
    {
        return view('livewire.compra-lote.modals.compra-lote-producto-edit-modal', [
            'vistaFotos' => array_merge(
                array_column($this->fotosGuardadas, 'url'),
                array_map(fn($foto) => $foto->temporaryUrl(), $this->fotos),
            ),
        ]);
    }

    public function mount()
    {
        $this->colores = ProductoColor::cases();
    }

    #[On('openCompraLoteProductoEditModal')]
    public function openModal($id)
    {
        $producto = Producto::with(['modelo', 'modelo.almacenamientos', 'imagenes',])->find($id);
        $this->producto = $producto->toArray();
        $this->producto['nombre'] = $producto->modelo->nombre;
        // La columna guarda 4 decimales ("6.9600"): en pantalla, 2.
        if ($this->producto['costo_tipo_cambio'] !== null) {
            $this->producto['costo_tipo_cambio'] = round((float) $this->producto['costo_tipo_cambio'], 2);
        }
        $this->enBorrador = $producto->estado === ProductoEstado::EnCompra->value;
        $this->producto['status'] = $this->enBorrador
            ? ($producto->compraDetalle?->estado_destino ?? ProductoEstado::Inventario->value)
            : $producto->estado;
        $this->producto['disponible_catalogo'] = (bool)$producto->disponible_catalogo;
        // Recibido en permuta: su costo ES el pago de la venta y no se edita aqui.
        $this->producto['es_permuta'] = $producto->permuta()->exists();

        // Activas mas la actual: si el equipo esta en una sucursal ya
        // desactivada, el select no puede quedar sin su opcion.
        $this->sucursales = Sucursal::paraSelect($producto->sucursal_id);

        unset($this->producto['imagenes']);
        $this->fotosGuardadas = $producto->imagenes->sortBy('id')
            ->map(fn($imagen) => ['id' => $imagen->id, 'url' => $imagen->url()])
            ->values()->all();
        $this->fotos = [];
        $this->fotoNueva = null;
        $this->photosToDelete = [];
        $this->currentPhotoIndex = 0;

        $this->selectedModel = $producto->modelo;

        $this->descripcion = $producto->descripcion;

        $this->openModal = true;
    }

    public function updatedProducto($value = null, $key = null)
    {
        $this->generateDescription();

        if ($key === 'costo_moneda' && $value === Moneda::USD->value) {
            // Pasar a USD propone el tipo de cambio y los dolares del costo actual.
            $tc = round((float) ($this->producto['costo_tipo_cambio'] ?? 0), 2) ?: Producto::tipoCambioSugerido();
            $this->producto['costo_tipo_cambio'] = $tc;
            if (!(float) ($this->producto['costo_moneda_monto'] ?? 0)) {
                $this->producto['costo_moneda_monto'] = round((float) $this->producto['costo_unidad'] / $tc, 2);
            }
        }

        if (in_array($key, ['costo_moneda', 'costo_moneda_monto', 'costo_tipo_cambio'], true)) {
            $this->sincronizarCostoBs();
        }
    }

    /** En USD, el costo en Bs = USD x TC (para mostrarlo; Producto lo recalcula al guardar). */
    private function sincronizarCostoBs(): void
    {
        $usd = $this->producto['costo_moneda_monto'] ?? null;
        $tc = $this->producto['costo_tipo_cambio'] ?? null;

        if (($this->producto['costo_moneda'] ?? null) === Moneda::USD->value && is_numeric($usd) && is_numeric($tc)) {
            $this->producto['costo_unidad'] = round((float) $usd * (float) $tc, 2);
        }
    }

    protected function generateDescription()
    {
        $parts = [
            $this->selectedModel->nombre ?? '',
            "color {$this->producto['color']}",
            "de {$this->producto['almacenamiento']}",
            "con {$this->producto['bateria_porcentaje']}% de batería",
            "IMEI: {$this->producto['imei']}",
        ];

        $this->descripcion = trim(implode(' ', array_filter($parts)));
    }

    public function updateProduct()
    {
        // FUERA de la transaccion y antes del try: ValidationException extiende
        // Exception, asi que el catch de abajo la atrapaba y convertia los
        // errores por campo en un unico addError('general'). El usuario veia
        // "Error: The given data was invalid" sin saber que campo arreglar.
        $this->producto['sku'] = NormalizaCodigosTrait::normalizarCodigo($this->producto['sku'] ?? null);
        $this->sincronizarCostoBs();

        $this->validate([
            'producto.almacenamiento' => 'required',
            'producto.color' => 'required',
            'producto.version' => 'nullable',
            'producto.bateria_porcentaje' => 'required|numeric|min:0|max:100',
            'producto.costo_unidad' => 'required|numeric|min:0',
            'producto.costo_moneda' => ['required', Rule::in(Moneda::values())],
            'producto.costo_moneda_monto' => ($this->producto['costo_moneda'] ?? null) === Moneda::USD->value ? 'required|numeric|gt:0' : 'nullable',
            'producto.costo_tipo_cambio' => ($this->producto['costo_moneda'] ?? null) === Moneda::USD->value ? 'required|numeric|gt:0|decimal:0,2' : 'nullable',
            'producto.sku' => ['nullable', 'string', 'max:50', Rule::unique('productos', 'sku')->ignore($this->producto['id'] ?? null)],
            'producto.upc' => 'nullable|string|max:50',
            'producto.tipo_venta' => ['required', Rule::in(ProductoTipoVenta::values())],
            'producto.precio_cliente' => 'required|numeric|min:0',
            'producto.precio_vendedor' => 'required|numeric|min:0',
            // Vendido y Credito solo los escribe una venta.
            'producto.status' => ['required', Rule::in($this->enBorrador
                ? CompraService::estadosDestinoBorrador()
                : array_diff(ProductoEstado::values(), ProductoEstado::soloPorDocumento()))],
            // La unicidad del IMEI tambien al EDITAR: aqui era un 'required' a
            // secas, asi que ponerle a un producto el IMEI de otro se guardaba
            // sin protestar. El ignore del propio id es para que reguardar sin
            // cambiar el IMEI no choque consigo mismo.
            'producto.imei' => [
                'required',
                'string',
                'max:20',
                Rule::unique('productos', 'imei')->ignore($this->producto['id'] ?? null),
            ],
            'producto.disponible_catalogo' => 'required',
            'producto.sucursal_id' => 'required',
            'producto.estado_grado' => ['required', Rule::in(ProductoGrado::values())],
        ], [
            'producto.sku.unique' => 'Ese SKU ya lo tiene otro equipo.',
            'producto.costo_tipo_cambio.decimal' => 'El tipo de cambio debe tener máximo 2 decimales.',
        ]);

        $estados = app(EstadoProductoService::class);

        try {
            DB::beginTransaction();

            $product = Producto::with('imagenes')->find($this->producto['id']);
            if (!$product) {
                throw new \Exception("Product not found");
            }

            // El estado sale del update general y pasa por el servicio, que
            // ademas deja la fila de historial. Por aqui se podia cambiar el
            // estado de un telefono sin dejar NINGUNA traza, y CLAUDE.md
            // promete que el historial es la unica que hay.
            $estadoNuevo = $this->producto['status'];
            $estadoAnterior = $product->estado;

            $esPermuta = $product->permuta()->exists();
            $product->update([
                'imei' => $this->producto['imei'],
                'almacenamiento' => $this->producto['almacenamiento'],
                'color' => $this->producto['color'],
                'version' => $this->producto['version'] ?? null,
                'bateria_porcentaje' => $this->producto['bateria_porcentaje'],
                'sku' => $this->producto['sku'],
                'upc' => $this->producto['upc'] ?? null,
                // El de un equipo recibido en permuta no cambia: es el pago de la venta.
                'costo_unidad' => $esPermuta ? $product->costo_unidad : $this->producto['costo_unidad'],
                // En USD, Producto deriva costo_unidad de estos dos al guardar.
                'costo_moneda' => $esPermuta ? $product->costo_moneda : $this->producto['costo_moneda'],
                'costo_moneda_monto' => $esPermuta ? $product->costo_moneda_monto : ($this->producto['costo_moneda_monto'] ?? null),
                'costo_tipo_cambio' => $esPermuta ? $product->costo_tipo_cambio : ($this->producto['costo_tipo_cambio'] ?? null),
                'tipo_venta' => $this->producto['tipo_venta'],
                'precio_cliente' => $this->producto['precio_cliente'],
                'precio_vendedor' => $this->producto['precio_vendedor'],
                'descripcion' => $this->descripcion,
                'detalles' => $this->producto['detalles'],
                'estado_grado' => $this->producto['estado_grado'],
                'producto_modelo_id' => $this->selectedModel['id'],
                'disponible_catalogo' => $this->producto['disponible_catalogo'],
                'sucursal_id' => $this->producto['sucursal_id'],
            ]);

            if (!empty($this->photosToDelete)) {
                // Por Eloquent y no por query builder: el evento deleted borra su miniatura.
                // Solo las de este equipo: los ids llegan del estado del componente.
                ProductoImagen::where('producto_id', $product->id)->whereIn('id', $this->photosToDelete)->get()->each->delete();
            }

            foreach ($this->fotos as $foto) {
                ProductoImagen::guardar($product, $foto);
            }

            if ($this->enBorrador) {
                // El estado real sigue en En compra; cambiarEstadoDestino()
                // rechaza si la compra se finalizo mientras el modal estaba abierto.
                app(CompraService::class)->cambiarEstadoDestino($product, $estadoNuevo);
            } elseif ($estadoNuevo !== $estadoAnterior) {
                $estados->cambiar(
                    $product->id,
                    ProductoEstado::from($estadoAnterior),
                    ProductoEstado::from($estadoNuevo),
                    "Estado cambiado desde la edicion del lote",
                );
            }

            // costo_total sale de recalcularCosto() (unidad + regalos +
            // reparaciones), y la linea de compra se sincroniza con el costo:
            // los dos solo los escribe CompraService.
            $product->refresh()->recalcularCosto();
            app(CompraService::class)->actualizarCostoProducto($product);
            DB::commit();
            $this->updateAndClose();
        } catch (ValidationException $e) {
            DB::rollBack();

            // La precondicion del estado habla en la bolsa 'detalles'; se
            // reexpone para que el blade la muestre donde ya mira.
            $this->addError('general', implode(' ', $e->validator->errors()->all()));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fallo al editar el producto del lote', [
                'producto_id' => $this->producto['id'] ?? null,
                'excepcion' => $e,
            ]);
            $this->addError('general', 'Error: ' . $e->getMessage());
        }
    }

    public function updateAndClose()
    {
        $this->closeModal();
        $this->dispatch('refreshProductoTable');
    }

    /** Cada disparo de la camara: se valida y pasa a la lista (sin aviso: la camara lleva su contador). */
    public function updatedFotoNueva()
    {
        $this->validate(['fotoNueva' => 'image|mimes:jpg,jpeg,png,webp|max:8192'], [
            'fotoNueva.*' => 'La foto debe ser una imagen JPG, PNG o WEBP de hasta 8 MB.',
        ]);

        $this->fotos[] = $this->fotoNueva;
        $this->fotoNueva = null;
    }

    private function cantidadFotos(): int
    {
        return count($this->fotosGuardadas) + count($this->fotos);
    }

    /** Quita la foto actual: una guardada se borra al actualizar; una nueva, ya. */
    public function removePhoto()
    {
        $i = (int) $this->currentPhotoIndex;
        $guardadas = count($this->fotosGuardadas);

        if ($i < $guardadas) {
            $this->photosToDelete[] = $this->fotosGuardadas[$i]['id'];
            unset($this->fotosGuardadas[$i]);
            $this->fotosGuardadas = array_values($this->fotosGuardadas);
        } elseif (isset($this->fotos[$i - $guardadas])) {
            unset($this->fotos[$i - $guardadas]);
            $this->fotos = array_values($this->fotos);
        } else {
            return;
        }

        $total = $this->cantidadFotos();
        $this->currentPhotoIndex = $total === 0 ? 0 : min($i, $total - 1);
    }

    public function prevPhoto()
    {
        $this->currentPhotoIndex = $this->currentPhotoIndex > 0
            ? $this->currentPhotoIndex - 1
            : $this->cantidadFotos() - 1;
    }

    public function nextPhoto()
    {
        $this->currentPhotoIndex = $this->currentPhotoIndex < $this->cantidadFotos() - 1
            ? $this->currentPhotoIndex + 1
            : 0;
    }

    public function goToPhoto($index)
    {
        $this->currentPhotoIndex = $index;
    }

    public function closeModal()
    {
        $this->openModal = false;
    }

}
