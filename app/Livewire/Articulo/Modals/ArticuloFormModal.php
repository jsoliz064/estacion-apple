<?php

namespace App\Livewire\Articulo\Modals;

use App\Enums\ArticuloTipo;
use App\Models\AccesorioCategoria;
use App\Models\ProductoModelo;
use App\Models\RepuestoCategoria;
use App\Rules\CodigoGeneradoLibre;
use App\Enums\Moneda;
use App\Traits\ArticuloStockSucursalTrait;
use App\Traits\MontosEnMonedaFormTrait;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Crear y editar un repuesto o un accesorio. Un solo modal para las dos
 * acciones y los dos tipos: los campos comunes (nombre, codigo de barras,
 * costo, precio, stock por sucursal) son los mismos, y cada tipo agrega los
 * suyos (repuesto: fabricante, modelo, categoria de pieza; accesorio:
 * marca, categoria de accesorio, modelos compatibles).
 *
 * El tipo lo fija quien lo declara (#[Locked]); la pantalla de compra lo
 * declara dos veces, una por tipo, para dar de alta articulos al vuelo.
 */
class ArticuloFormModal extends Component
{
    use ArticuloStockSucursalTrait;
    use MontosEnMonedaFormTrait;

    #[Locked]
    public string $tipo = '';

    /** Si es false, el formulario no muestra el bloque de stock (alta al vuelo desde una compra). */
    #[Locked]
    public bool $conStock = true;

    public $openModal = false;
    public ?int $articuloId = null;
    public array $articulo = [];
    /** Accesorio: ids de los modelos compatibles. */
    public array $modelos = [];

    public function mount(string $tipo, bool $conStock = true): void
    {
        $this->tipo = ArticuloTipo::from($tipo)->value;
        $this->conStock = $conStock;
    }

    protected function articuloTipo(): ArticuloTipo
    {
        return ArticuloTipo::from($this->tipo);
    }

    public function esRepuesto(): bool
    {
        return $this->articuloTipo() === ArticuloTipo::Repuesto;
    }

    protected function rules(): array
    {
        $reglas = [
            'articulo.nombre' => 'required|string|max:255',
            // Un sticker de Codigos de barras se asigna a un solo registro, en
            // cualquiera de las tres tablas.
            'articulo.upc' => ['nullable', 'string', 'max:50', new CodigoGeneradoLibre($this->articuloTipo()->value, $this->articuloId)],
            'articulo.costo' => 'required|numeric|min:0',
            'articulo.precio' => 'required|numeric|min:0',
        ] + $this->reglasMontosEnMoneda();

        if ($this->esRepuesto()) {
            $reglas += [
                'articulo.fabricante' => 'nullable|string|max:255',
                'articulo.producto_modelo_id' => 'required|integer|exists:productos_modelos,id',
                'articulo.repuesto_categoria_id' => 'nullable|integer|exists:repuestos_categorias,id',
            ];
        } else {
            $reglas += [
                'articulo.marca' => 'nullable|string|max:255',
                'articulo.accesorio_categoria_id' => 'nullable|integer|exists:accesorios_categorias,id',
                'modelos' => 'array',
                'modelos.*' => 'integer|exists:productos_modelos,id',
            ];
        }

        return $this->conStock ? array_merge($reglas, $this->reglasDeStock()) : $reglas;
    }

    protected function messages(): array
    {
        return [
            'articulo.nombre.required' => 'Debe ingresar un nombre',
            'articulo.costo.required' => 'Debe ingresar el costo',
            'articulo.precio.required' => 'Debe ingresar el precio',
            'articulo.producto_modelo_id.required' => 'Debe seleccionar el modelo al que corresponde el repuesto',
            'stockSucursales.*.required' => 'Indique la cantidad de cada sucursal.',
            'stockSucursales.*.min' => 'El stock de una sucursal no puede ser negativo.',
            'minimosSucursales.*.min' => 'El mínimo no puede ser negativo.',
        ];
    }

    /** Costo y precio en USD, o cargados en Bs (MontosEnMonedaFormTrait). */
    protected function camposMoneda(): array
    {
        return [
            'moneda' => 'articulo.moneda', 'costo' => 'articulo.costo', 'precio' => 'articulo.precio',
            'costo_bs' => 'articulo.costo_moneda_monto', 'precio_bs' => 'articulo.precio_moneda_monto', 'tc' => 'articulo.tipo_cambio',
        ];
    }

    public function updatedArticulo($valor, $clave): void
    {
        $this->montoEnMonedaCambio('articulo.' . $clave);
    }

    #[On('openArticuloCreateModal')]
    public function abrirCrear(?string $tipo = null): void
    {
        // La pantalla de compra declara un modal por tipo y avisa a cual abrir.
        if ($tipo !== null && $tipo !== $this->tipo) {
            return;
        }

        $this->resetExcept(['tipo', 'conStock']);
        $this->resetErrorBag();
        // Por defecto en USD: todo el sistema se maneja en dolares.
        $this->articulo = ['moneda' => Moneda::base()->value, 'costo' => 0, 'precio' => 0];
        $this->openModal = true;
    }

    #[On('openArticuloEditModal')]
    public function abrirEditar($id): void
    {
        $articulo = ($this->articuloTipo()->modelo())::findOrFail($id);

        $this->resetExcept(['tipo', 'conStock']);
        $this->resetErrorBag();
        $this->articuloId = $articulo->id;
        // `cantidad` es el total cacheado: no viaja en el formulario.
        $this->articulo = collect($articulo->toArray())->except(['id', 'cantidad', 'created_at', 'updated_at'])->all();
        // La columna guarda 4 decimales ("6.9600"): en pantalla, sin los ceros.
        if ($this->articulo['tipo_cambio'] !== null) {
            $this->articulo['tipo_cambio'] = (float) $this->articulo['tipo_cambio'];
        }

        if (!$this->esRepuesto()) {
            $this->modelos = $articulo->modelosCompatibles()->pluck('productos_modelos.id')->map(fn($v) => (string) $v)->all();
        }

        $this->cargarStockSucursales($articulo->id);
        $this->openModal = true;
    }

    public function guardar()
    {
        $tipo = $this->articuloTipo();
        abort_unless(Auth::user()?->can($tipo->permiso() . ($this->articuloId ? '.edit' : '.create')), 403);

        // Un codigo en blanco es NULL, antes de validar el sticker.
        $this->articulo['upc'] = NormalizaCodigosTrait::normalizarCodigo($this->articulo['upc'] ?? null);
        $this->sincronizarMontosEnMoneda();
        $this->validate($this->rules(), $this->messages(), $this->atributosMontosEnMoneda());

        // Al editar, sin editar-costo / editar-precio la plata no cambia. Al
        // crear no aplica: quien da de alta carga el costo y el precio.
        if ($this->articuloId) {
            $this->exigirPermisoDeMontos(($tipo->modelo())::findOrFail($this->articuloId), $this->puedeEditarCosto(), $this->puedeEditarPrecio());
        }

        $campos = $this->esRepuesto()
            ? ['nombre', 'upc', 'costo', 'precio', 'fabricante', 'producto_modelo_id', 'repuesto_categoria_id']
            : ['nombre', 'upc', 'costo', 'precio', 'marca', 'accesorio_categoria_id'];

        $datos = collect($campos)->mapWithKeys(fn($c) => [$c => ($this->articulo[$c] ?? null) === '' ? null : ($this->articulo[$c] ?? null)])->all()
            + $this->datosMontosEnMoneda();

        $articulo = DB::transaction(function () use ($tipo, $datos) {
            $modelo = $tipo->modelo();
            $articulo = $this->articuloId
                ? tap($modelo::findOrFail($this->articuloId))->update($datos)
                : $modelo::create($datos);

            if (!$this->esRepuesto()) {
                $articulo->modelosCompatibles()->sync(array_map('intval', $this->modelos));
            }

            if ($this->conStock) {
                $this->guardarStockSucursales($articulo->id);
            }

            return $articulo;
        });

        $creado = !$this->articuloId;
        $this->dispatch('refreshArticuloTable');
        // Para las pantallas que dan de alta al vuelo (compra): agregan la linea.
        if ($creado) {
            $this->dispatch('articuloCreado', tipo: $tipo->value, id: $articulo->id);
        }
        toastr()->success($tipo->label() . ($creado ? ' creado' : ' actualizado') . ' exitosamente');
        $this->resetExcept(['tipo', 'conStock']);
    }

    /** Crear siempre puede cargar la plata; editar, solo con su permiso. */
    private function puedeEditarCosto(): bool
    {
        return !$this->articuloId || (bool) Auth::user()?->can($this->articuloTipo()->permiso() . '.editar-costo');
    }

    private function puedeEditarPrecio(): bool
    {
        return !$this->articuloId || (bool) Auth::user()?->can($this->articuloTipo()->permiso() . '.editar-precio');
    }

    public function closeModal()
    {
        $this->resetExcept(['tipo', 'conStock']);
    }

    public function render()
    {
        $abierto = $this->openModal;

        return view('livewire.articulo.modals.articulo-form-modal', [
            // Catalogos en render() y solo con el modal abierto: cerrado, 0 consultas.
            'catalogoModelos' => $abierto ? ProductoModelo::orderBy('nombre')->get(['id', 'nombre']) : collect(),
            'catalogoCategorias' => !$abierto ? collect() : ($this->esRepuesto()
                ? RepuestoCategoria::orderBy('nombre')->get(['id', 'nombre'])
                : AccesorioCategoria::orderBy('nombre')->get(['id', 'nombre'])),
            'sucursalesDisponibles' => $abierto && $this->conStock ? $this->sucursalesDisponibles() : collect(),
            'sucursalesPorId' => $abierto && $this->conStock ? $this->sucursalesPorId() : collect(),
            'puedeCosto' => $this->puedeEditarCosto(),
            'puedePrecio' => $this->puedeEditarPrecio(),
        ]);
    }
}
