<?php

namespace App\Livewire\Articulo\Modals;

use App\Enums\ArticuloTipo;
use App\Models\AccesorioCategoria;
use App\Models\ProductoModelo;
use App\Models\RepuestoCategoria;
use App\Traits\ArticuloStockSucursalTrait;
use App\Traits\NormalizaCodigosTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Crear y editar un repuesto o un accesorio. Un solo modal para las dos
 * acciones y los dos tipos: los campos comunes (nombre, SKU, codigo de barras,
 * costo, precio, stock por sucursal) son los mismos, y cada tipo agrega los
 * suyos (repuesto: fabricante, modelo, categoria de pieza, color; accesorio:
 * marca, categoria de accesorio, modelos compatibles).
 *
 * El tipo lo fija quien lo declara (#[Locked]); la pantalla de compra lo
 * declara dos veces, una por tipo, para dar de alta articulos al vuelo.
 */
class ArticuloFormModal extends Component
{
    use ArticuloStockSucursalTrait;

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
        $tabla = $this->articuloTipo()->tabla();

        $reglas = [
            'articulo.nombre' => 'required|string|max:255',
            // Unico en la base, y aqui tambien para dar el error en el campo. El
            // indice es quien lo garantiza de verdad: de ahi el catch de guardar().
            'articulo.sku' => ['nullable', 'string', 'max:50', Rule::unique($tabla, 'sku')->ignore($this->articuloId)],
            'articulo.upc' => 'nullable|string|max:50',
            'articulo.costo' => 'required|numeric|min:0',
            'articulo.precio' => 'required|numeric|min:0',
        ];

        if ($this->esRepuesto()) {
            $reglas += [
                'articulo.fabricante' => 'nullable|string|max:255',
                'articulo.producto_modelo_id' => 'required|integer|exists:productos_modelos,id',
                'articulo.repuesto_categoria_id' => 'nullable|integer|exists:repuestos_categorias,id',
                'articulo.color' => ['nullable', 'string', 'max:50'],
                // Sintaxis de array: una regex no puede ir en un string con |.
                'articulo.color_hex' => ['nullable', 'string', 'regex:/^#[A-Fa-f0-9]{6}$/'],
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
            'articulo.sku.unique' => 'Ese SKU ya lo tiene otro artículo',
            'articulo.costo.required' => 'Debe ingresar el costo',
            'articulo.precio.required' => 'Debe ingresar el precio',
            'articulo.producto_modelo_id.required' => 'Debe seleccionar el modelo al que corresponde el repuesto',
            'articulo.color_hex.regex' => 'El color debe ser un hexadecimal válido (ej: #1F2937).',
            'stockSucursales.*.required' => 'Indique la cantidad de cada sucursal.',
            'stockSucursales.*.min' => 'El stock de una sucursal no puede ser negativo.',
            'minimosSucursales.*.min' => 'El mínimo no puede ser negativo.',
        ];
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

        // Un SKU en blanco es NULL, antes de validar el unique.
        $this->articulo['sku'] = NormalizaCodigosTrait::normalizarCodigo($this->articulo['sku'] ?? null);
        $this->validate();

        $campos = $this->esRepuesto()
            ? ['nombre', 'sku', 'upc', 'costo', 'precio', 'fabricante', 'producto_modelo_id', 'repuesto_categoria_id', 'color', 'color_hex']
            : ['nombre', 'sku', 'upc', 'costo', 'precio', 'marca', 'accesorio_categoria_id'];

        $datos = collect($campos)->mapWithKeys(fn($c) => [$c => ($this->articulo[$c] ?? null) === '' ? null : ($this->articulo[$c] ?? null)])->all();

        try {
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
        } catch (QueryException $e) {
            // El unique del SKU es la garantia real: dos pestanas a la vez pasan
            // las dos la regla de validacion.
            if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), '_sku_unico')) {
                $this->addError('articulo.sku', 'Ese SKU ya lo tiene otro artículo.');

                return;
            }

            throw $e;
        }

        $creado = !$this->articuloId;
        $this->dispatch('refreshArticuloTable');
        // Para las pantallas que dan de alta al vuelo (compra): agregan la linea.
        if ($creado) {
            $this->dispatch('articuloCreado', tipo: $tipo->value, id: $articulo->id);
        }
        toastr()->success($tipo->label() . ($creado ? ' creado' : ' actualizado') . ' exitosamente');
        $this->resetExcept(['tipo', 'conStock']);
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
        ]);
    }
}
