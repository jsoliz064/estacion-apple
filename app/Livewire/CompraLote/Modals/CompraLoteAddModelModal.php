<?php

namespace App\Livewire\CompraLote\Modals;

use App\Enums\Moneda;
use App\Enums\ProductoColor;
use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Enums\ProductoTipoVenta;
use App\Models\Bitacora;
use App\Models\Compra;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\ProductoModeloAlmacenamiento;
use App\Models\ProductoReparacion;
use App\Models\Sucursal;
use App\Models\Tecnicos;
use App\Services\CompraService;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Alta de un equipo dentro de una compra (uno por uno: lleva IMEI, fotos y
 * camara). Lo escribe CompraService::agregarProducto(), que crea el producto y
 * su linea de compra. Todo en Bs; el viejo "costo de envio" ya no existe (lo
 * reemplazan los accesorios de regalo, que se cargan desde la ficha).
 */
class CompraLoteAddModelModal extends Component
{
    use WithFileUploads;

    public $openModal = false;
    public $selectedModel;
    public $compra;

    // Form fields
    public $almacenamiento;
    public $color;
    public $version;
    public $imei;
    public $sku;
    public $upc;
    public $bateria_porcentaje = 0;
    public $disponible_catalogo = false;
    public $sucursal_id;

    /** El costo en Bs: el que se guarda. En USD lo calcula sincronizarCostoBs(). */
    public $costo_unidad = 0;
    public $costo_moneda = 'BOB';
    public $costo_usd = 0;
    public $tipo_cambio;

    public $precio_cliente = 0;
    public $precio_vendedor = 0;
    public $descripcion;
    public $status = 'Inventario';
    public $tipo_venta = 'Venta';
    public $tecnico_selected;
    public $tecnicos = [];
    public $sucursales = [];
    public $colores = [];

    /**
     * Las fotos llegan de la camara como archivos (subirFoto() en app.js, el
     * upload de Livewire) y esperan aqui hasta guardar el equipo. Antes eran
     * data URLs en el estado: cada peticion del modal arrastraba todas.
     */
    public $fotoNueva;
    public array $fotos = [];

    public $detalles = [
        'Batería cambiada',
        'Borde un poco desgastado',
        'Con Bandeja Chip',
        'Face ID',
        'Mensaje de pantalla pieza desconocida',
        'Mensaje de batería desconocida',
        'Mensaje de pantalla pieza original',
        'Mensaje de batería original',
        'Mensaje de tarjeta madre original',
        'Mensaje cámara true deep',
        'Pantalla genérica',
        'Pantalla cambiada (original)',
        'Pequeña grieta adelante',
        'Pequeña grieta en la tapa',
        'Punto innotable en la pantalla',
        'Punto en la pantalla',
        'Rayones en la pantalla',
        'R sim',
        'Sin Face ID',
        'MDM',
    ];

    public $detallesSeleccionados = [];
    public $detallesText;
    public $estado_grado = '1';

    public $currentPhotoIndex = 0;

    protected function rules()
    {
        return [
            'almacenamiento' => 'required',
            'color' => 'required',
            'version' => 'nullable',
            'imei' => 'required|string|min:1|max:20|unique:productos,imei',
            'sku' => 'nullable|string|max:50|unique:productos,sku',
            'upc' => 'nullable|string|max:50',
            'bateria_porcentaje' => 'required|numeric|min:1|max:100',
            'costo_unidad' => 'required|numeric|min:0|decimal:0,2',
            'costo_moneda' => ['required', Rule::in(Moneda::values())],
            'costo_usd' => $this->costo_moneda === Moneda::USD->value ? 'required|numeric|gt:0|decimal:0,2' : 'nullable',
            'tipo_cambio' => $this->costo_moneda === Moneda::USD->value ? 'required|numeric|gt:0|decimal:0,2' : 'nullable',
            'precio_cliente' => 'required|numeric|min:0|decimal:0,2|gte:costo_unidad',
            'precio_vendedor' => 'required|numeric|min:0|decimal:0,2|gte:costo_unidad',
            'descripcion' => 'nullable|string',
            'disponible_catalogo' => 'required',
            'estado_grado' => ['required', Rule::in(ProductoGrado::values())],
            'tipo_venta' => ['required', Rule::in(ProductoTipoVenta::values())],
            // Al dar de alta no se puede elegir Vendido ni Credito: los escribe una venta.
            'status' => ['required', Rule::in($this->estadosPermitidos())],
            'tecnico_selected' => 'required_if:status,' . ProductoEstado::Reparacion->value,
            'sucursal_id' => 'required|exists:sucursales,id,activa,1',
        ];
    }

    protected function messages()
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'numeric' => 'El campo :attribute debe ser numérico.',
            'imei.unique' => 'Este IMEI ya existe en el sistema.',
            'sku.unique' => 'Ese SKU ya lo tiene otro equipo.',
            'min' => 'El valor mínimo para :attribute es :min.',
            'max' => 'El valor máximo para :attribute es :max.',
            'decimal' => 'El campo :attribute debe tener máximo 2 decimales.',
            'gte' => 'El precio debe ser mayor o igual al costo.',
            'almacenamiento.required' => 'Debe seleccionar un almacenamiento.',
            'color.required' => 'Debe seleccionar un color.',
            'estado_grado.required' => 'Debe seleccionar el grado.',
            'tecnico_selected.required_if' => 'Elija el técnico de la reparación.',
            'sucursal_id.required' => 'Debe seleccionar una sucursal',
            'sucursal_id.exists' => 'La sucursal elegida no existe o está desactivada.',
        ];
    }

    protected function validationAttributes()
    {
        return [
            'imei' => 'IMEI',
            'bateria_porcentaje' => 'porcentaje de batería',
            'costo_unidad' => 'costo',
            'costo_usd' => 'costo en USD',
            'tipo_cambio' => 'tipo de cambio',
            'precio_cliente' => 'precio al cliente',
            'precio_vendedor' => 'precio al vendedor',
            'estado_grado' => 'grado',
            'tipo_venta' => 'tipo de venta',
            'status' => 'estado',
        ];
    }

    public function mount($compra_lote)
    {
        $this->compra = $compra_lote;
        $this->colores = ProductoColor::cases();
        $this->tecnicos = Tecnicos::all();
        $this->sucursales = Sucursal::activas()->orderBy('nombre')->get();
    }

    /**
     * En borrador, el estado elegido es al que pasa el equipo al FINALIZAR
     * (mientras tanto espera en En compra), y no incluye Reparacion: no se
     * manda al tecnico un equipo que todavia no se recibio.
     *
     * @return string[]
     */
    private function estadosPermitidos(): array
    {
        if (Compra::whereKey($this->compra->id)->value('finalizada_at') === null) {
            return CompraService::estadosDestinoBorrador();
        }

        return array_values(array_diff(ProductoEstado::values(), ProductoEstado::soloPorDocumento()));
    }

    public function render()
    {
        $permitidos = $this->estadosPermitidos();

        return view('livewire.compra-lote.modals.compra-lote-add-model-modal', [
            'estadosAlta' => collect(ProductoEstado::cases())
                ->filter(fn($e) => in_array($e->value, $permitidos, true)),
            'enBorrador' => Compra::whereKey($this->compra->id)->value('finalizada_at') === null,
            // URLs de vista previa de Livewire: el carrusel ya no recibe base64.
            'vistaFotos' => array_map(fn($foto) => $foto->temporaryUrl(), $this->fotos),
        ]);
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

    public function removePhoto()
    {
        if (!isset($this->fotos[$this->currentPhotoIndex])) {
            return;
        }

        unset($this->fotos[$this->currentPhotoIndex]);
        $this->fotos = array_values($this->fotos);

        if (empty($this->fotos)) {
            $this->currentPhotoIndex = 0;

            return;
        }

        $this->currentPhotoIndex = min(max(0, $this->currentPhotoIndex - 1), count($this->fotos) - 1);
    }

    #[On('openModalSelector')]
    public function open($modelId)
    {
        $this->selectedModel = ProductoModelo::with('almacenamientos')->find($modelId);
        // Limpio: al elegir un modelo, los datos del ultimo equipo (quiza de
        // otro modelo) se colaban en el formulario.
        $this->resetForm(false);
        $this->openModal = true;

        // El precio de referencia del modelo, si ya lo cargaron: 128GB por defecto.
        if ($this->selectedModel->almacenamientos->firstWhere('almacenamiento', '128GB')) {
            $this->almacenamiento = '128GB';
            $this->aplicarPreciosDeReferencia();
        }

        $this->generateDescription();
    }

    public function updatedAlmacenamiento($value)
    {
        $this->aplicarPreciosDeReferencia();
        $this->generateDescription();
    }

    /** Copia los precios de referencia del modelo/almacenamiento, si los hay (no pisa con ceros). */
    private function aplicarPreciosDeReferencia(): void
    {
        $ref = ProductoModeloAlmacenamiento::where('producto_modelo_id', $this->selectedModel->id)
            ->where('almacenamiento', $this->almacenamiento)
            ->first();

        if (!$ref) {
            return;
        }

        // El de referencia esta en Bs: en USD no se toca.
        if ((float) $ref->costo > 0 && $this->costo_moneda === Moneda::BOB->value) {
            $this->costo_unidad = $ref->costo;
        }
        if ((float) $ref->precio > 0) {
            $this->precio_vendedor = $ref->precio;
        }
        if ((float) $ref->precio_cliente > 0) {
            $this->precio_cliente = $ref->precio_cliente;
        }
    }

    /**
     * @param  bool  $copiarUltimo  true en «Guardar y continuar»: copia los
     *         valores del ultimo equipo de esta compra, porque en un lote los
     *         equipos suelen repetirse y asi solo se cambia lo que difiere.
     *         false al abrir desde un modelo: el formulario arranca limpio.
     */
    public function resetForm(bool $copiarUltimo = true)
    {
        $this->resetErrorBag();

        $ultimo = $copiarUltimo
            ? Compra::find($this->compra->id)?->productos()->orderByDesc('productos.id')->first()
            : null;

        if (!$copiarUltimo) {
            $this->almacenamiento = null;
        }

        $this->costo_unidad = $ultimo?->costo_unidad ?? 0;
        // En un lote en dolares se repiten la moneda y el tipo de cambio.
        $this->costo_moneda = $ultimo?->costo_moneda ?? Moneda::BOB->value;
        $this->costo_usd = $ultimo?->costo_moneda_monto ?? 0;
        $this->tipo_cambio = $ultimo?->costo_tipo_cambio ? round((float) $ultimo->costo_tipo_cambio, 2) : Producto::tipoCambioSugerido();
        $this->precio_cliente = $ultimo?->precio_cliente ?? 0;
        $this->precio_vendedor = $ultimo?->precio_vendedor ?? 0;
        $this->detallesSeleccionados = [];
        $this->detallesText = '';
        $this->estado_grado = $ultimo?->estado_grado ?? ProductoGrado::Uno->value;
        $this->tipo_venta = $ultimo?->tipo_venta ?? ProductoTipoVenta::Venta->value;
        $this->color = $ultimo?->color ?? ProductoColor::Negro->value;
        $this->version = $ultimo?->version ?? '';
        $this->imei = '';
        $this->sku = '';
        $this->upc = '';
        $this->bateria_porcentaje = $ultimo?->bateria_porcentaje ?? 0;
        $this->descripcion = '';
        // En borrador el ultimo esta En compra: se repite el estado al que pasara.
        $estadoUltimo = $ultimo?->estado === ProductoEstado::EnCompra->value
            ? $ultimo->compraDetalle?->estado_destino
            : $ultimo?->estado;
        $this->status = $estadoUltimo && in_array($estadoUltimo, $this->estadosPermitidos(), true)
            ? $estadoUltimo
            : ProductoEstado::Inventario->value;
        $this->tecnico_selected = null;
        $this->fotos = [];
        $this->fotoNueva = null;
        $this->currentPhotoIndex = 0;
        $this->sucursal_id = $ultimo?->sucursal_id ?? $this->compra->sucursal_id;
    }

    public function closeModal()
    {
        $this->openModal = false;
    }

    public function updated($propertyName)
    {
        if (in_array($propertyName, ['almacenamiento', 'color', 'imei', 'bateria_porcentaje'])) {
            $this->generateDescription();
        }

        if (in_array($propertyName, ['costo_moneda', 'costo_usd', 'tipo_cambio'])) {
            $this->sincronizarCostoBs();
        }
    }

    /**
     * En USD, el costo en Bs = USD x TC. Se calcula aqui para mostrarlo y para
     * que la regla «el precio no baja del costo» compare en Bs; el que se guarda
     * lo vuelve a calcular Producto al guardar.
     */
    private function sincronizarCostoBs(): void
    {
        if ($this->costo_moneda === Moneda::USD->value && is_numeric($this->costo_usd) && is_numeric($this->tipo_cambio)) {
            $this->costo_unidad = round((float) $this->costo_usd * (float) $this->tipo_cambio, 2);
        }
    }

    protected function generateDescription()
    {
        $parts = [
            $this->selectedModel->nombre ?? '',
            $this->color ? "color {$this->color}" : '',
            $this->almacenamiento ? "de {$this->almacenamiento}" : '',
            isset($this->bateria_porcentaje) ? "con {$this->bateria_porcentaje}% de batería" : '',
            $this->imei ? "IMEI: {$this->imei}" : '',
        ];

        $this->descripcion = trim(implode(' ', array_filter($parts)));
    }

    protected function saveProduct()
    {
        // La pistola puede dejar espacios o un salto de linea al final.
        $this->imei = trim((string) $this->imei);
        $this->sku = NormalizaCodigosTrait::normalizarCodigo($this->sku);
        $this->upc = NormalizaCodigosTrait::normalizarCodigo($this->upc);
        $this->sincronizarCostoBs();
        $this->validate();

        // La clave natural del alta es el IMEI (productos_imei_unico): un
        // reintento choca contra el indice en lugar de crear el equipo dos veces.
        try {
            $producto = DB::transaction(function () {
                $compra = Compra::lockForUpdate()->findOrFail($this->compra->id);

                $producto = app(CompraService::class)->agregarProducto($compra, [
                    'producto_modelo_id' => $this->selectedModel->id,
                    'almacenamiento' => $this->almacenamiento,
                    'color' => $this->color,
                    'version' => $this->version ?: null,
                    'imei' => $this->imei,
                    'sku' => $this->sku,
                    'upc' => $this->upc,
                    'bateria_porcentaje' => $this->bateria_porcentaje,
                    'costo_unidad' => $this->costo_unidad,
                    'costo_moneda' => $this->costo_moneda,
                    'costo_moneda_monto' => $this->costo_moneda === Moneda::USD->value ? $this->costo_usd : null,
                    'costo_tipo_cambio' => $this->costo_moneda === Moneda::USD->value ? $this->tipo_cambio : null,
                    'precio_cliente' => $this->precio_cliente,
                    'precio_cliente_ant' => 0,
                    'precio_vendedor' => $this->precio_vendedor,
                    'precio_vendedor_ant' => 0,
                    'estado' => $this->status,
                    'tipo_venta' => $this->tipo_venta,
                    'descripcion' => $this->descripcion,
                    'detalles' => $this->detallesText,
                    'estado_grado' => $this->estado_grado,
                    'disponible_catalogo' => $this->disponible_catalogo,
                    'sucursal_id' => $this->sucursal_id,
                ], $this->fotos);

                if ($this->status == ProductoEstado::Reparacion->value) {
                    $tecnico = Tecnicos::findOrFail($this->tecnico_selected);

                    $productoReparacion = ProductoReparacion::create([
                        'tecnico_id' => $tecnico->id,
                        'producto_id' => $producto->id,
                        'costo' => 0,
                        'fecha_entrega' => now()->format('Y-m-d'),
                    ]);

                    // Hecho suelto: la reparacion no es un cambio del producto,
                    // que ya nacio en Reparacion en su fila de alta.
                    Bitacora::registrar(
                        $producto,
                        $this->status,
                        "Producto en reparacion con el tecnico {$tecnico->nombre}",
                        ['producto_reparacion_id' => $productoReparacion->id],
                    );
                }

                return $producto;
            });
        } catch (QueryException $e) {
            if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'imei')) {
                $this->addError('imei', "El IMEI {$this->imei} ya está registrado. Si acabas de guardarlo, revisa la lista antes de repetir.");

                return null;
            }
            if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'sku')) {
                $this->addError('sku', 'Ese SKU ya lo tiene otro equipo.');

                return null;
            }

            throw $e;
        }

        $this->dispatch('loadModelCounts');

        return $producto;
    }

    public function saveAndClose()
    {
        // Si el alta no entro (IMEI repetido), el modal se queda abierto con el error.
        if (!$this->saveProduct()) {
            return;
        }

        $this->closeModal();
        $this->dispatch('refreshProductoTable');
        $this->dispatch('refreshCompraDetalle');
    }

    public function saveAndContinue()
    {
        if (!$this->saveProduct()) {
            return;
        }

        $this->dispatch('refreshProductoTable');
        $this->dispatch('refreshCompraDetalle');
        toastr()->success('Se ha guardado el producto exitosamente');
        $this->resetForm();
        $this->dispatch('compra-equipo-guardado');
    }
}
