<?php

namespace App\Livewire\CompraLote\Modals;

use App\Enums\Moneda;
use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Enums\ProductoTipoVenta;
use App\Enums\ProductoVersion;
use App\Models\Color;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\Sucursal;
use App\Rules\CodigoGeneradoLibre;
use App\Services\CompraService;
use App\Services\EstadoProductoService;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Traits\MontosEnMonedaFormTrait;
use Livewire\Component;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;


class CompraLoteProductoEditModal extends Component
{
    use MontosEnMonedaFormTrait;
    use WithFileUploads;

    public $openModal = false;
    public $selectedModel;

    public $descripcion;
    /** El color guardado: se sigue ofreciendo aunque se haya desactivado. */
    public ?string $colorOriginal = null;
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
            'colores' => $this->openModal ? $this->coloresOfrecidos() : collect(),
            'puedeCosto' => $this->puedeEditarCosto(),
            'puedePrecio' => $this->puedeEditarPrecio(),
        ]);
    }

    /** Los permisos de la plata: sin ellos, se editan los datos pero no el costo o el precio. */
    private function puedeEditarCosto(): bool
    {
        return (bool) Auth::user()?->can('producto.editar-costo');
    }

    private function puedeEditarPrecio(): bool
    {
        return (bool) Auth::user()?->can('producto.editar-precio');
    }

    /**
     * Los del modelo, mas el que ya tiene el equipo: editar un equipo viejo no
     * debe obligar a cambiarle un color que despues se desactivo.
     */
    private function coloresOfrecidos()
    {
        return Color::paraModelo($this->producto['producto_modelo_id'] ?? null, $this->colorOriginal);
    }

    #[On('openCompraLoteProductoEditModal')]
    public function openModal($id)
    {
        // Antes solo lo pedia el @can del boton: el servidor no comprobaba nada.
        abort_unless(Auth::user()?->can('producto.edit'), 403);

        $producto = Producto::with(['modelo', 'modelo.almacenamientos', 'imagenes',])->find($id);
        $this->producto = $producto->toArray();
        $this->producto['nombre'] = $producto->modelo->nombre;
        $this->colorOriginal = $producto->color;
        // La columna guarda 4 decimales ("6.9600"): en pantalla, sin los ceros.
        if ($this->producto['tipo_cambio'] !== null) {
            $this->producto['tipo_cambio'] = (float) $this->producto['tipo_cambio'];
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
        $this->montoEnMonedaCambio('producto.' . $key);
    }

    protected function camposMoneda(): array
    {
        return [
            'moneda' => 'producto.moneda', 'costo' => 'producto.costo_unidad', 'precio' => 'producto.precio',
            'costo_bs' => 'producto.costo_moneda_monto', 'precio_bs' => 'producto.precio_moneda_monto', 'tc' => 'producto.tipo_cambio',
        ];
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
        abort_unless(Auth::user()?->can('producto.edit'), 403);

        // FUERA de la transaccion y antes del try: ValidationException extiende
        // Exception, asi que el catch de abajo la atrapaba y convertia los
        // errores por campo en un unico addError('general'). El usuario veia
        // "Error: The given data was invalid" sin saber que campo arreglar.
        $this->producto['upc'] = NormalizaCodigosTrait::normalizarCodigo($this->producto['upc'] ?? null);
        $this->sincronizarMontosEnMoneda();

        $this->validate([
            'producto.almacenamiento' => 'required',
            'producto.color' => ['required', Rule::in($this->coloresOfrecidos()->pluck('nombre')->all())],
            'producto.version' => 'nullable',
            'producto.bateria_porcentaje' => 'required|numeric|min:0|max:100',
            'producto.costo_unidad' => 'required|numeric|min:0',
            // Un sticker de Codigos de barras se asigna a un solo registro.
            'producto.upc' => ['nullable', 'string', 'max:50', new CodigoGeneradoLibre('Producto', $this->producto['id'] ?? null)],
            'producto.tipo_venta' => ['required', Rule::in(ProductoTipoVenta::values())],
            'producto.precio' => 'required|numeric|min:0',
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
        ] + $this->reglasMontosEnMoneda(), [
        ], $this->atributosMontosEnMoneda());

        // Sin editar-costo / editar-precio, la plata no cambia (la pantalla ya
        // los bloquea; esto cubre una peticion armada a mano).
        $this->exigirPermisoDeMontos(Producto::findOrFail($this->producto['id']), $this->puedeEditarCosto(), $this->puedeEditarPrecio());

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
                'upc' => $this->producto['upc'] ?? null,
                // El de un equipo recibido en permuta no cambia: es el pago de la
                // venta, y queda en USD (la moneda se bloquea en la pantalla).
                'costo_unidad' => $esPermuta ? $product->costo_unidad : $this->producto['costo_unidad'],
                'precio' => $this->producto['precio'],
                // En Bs, Producto deriva costo_unidad y precio de estos al guardar.
                ...($esPermuta ? [] : $this->datosMontosEnMoneda()),
                'tipo_venta' => $this->producto['tipo_venta'],
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
