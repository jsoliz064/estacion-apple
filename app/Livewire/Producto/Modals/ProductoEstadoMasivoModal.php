<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ProductoEstado;
use App\Enums\ProductoTipoVenta;
use App\Models\Producto;
use App\Services\EstadoProductoService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use App\Traits\EligePorCodigoTrait;
use Livewire\Component;
use Livewire\Attributes\On;

/**
 * Cambio masivo de productos: de estado, o de tipo de venta.
 *
 * Dos modos porque la oferta dejo de ser un estado y volvio a ser
 * `productos.tipo_venta`: poner un lote entero en Oferta (o sacarlo) es la
 * misma operacion de siempre, sobre otra columna. El estado pasa por
 * EstadoProductoService; el tipo de venta no tiene servicio propio y se
 * escribe aqui con la misma forma (lectura bloqueada, precondicion, anotar
 * antes del save).
 */
class ProductoEstadoMasivoModal extends Component
{
    use EligePorCodigoTrait;
    /** El @can de la vista solo esconde el boton; esto protege el dato. */
    private const PERMISO = 'producto.estado-masivo';

    /**
     * Minimo de digitos para consultar. En el mostrador se teclean los ultimos
     * del IMEI; con uno o dos hay decenas de coincidencias y la lista, cortada
     * a diez, sale ruido.
     */
    private const MIN_BUSQUEDA = 3;

    public $openModal = false;

    public $searchImei = '';
    public $filteredProductos = [];

    /**
     * Por que la lista salio vacia. Un unico "No se encontraron resultados" no
     * distingue un IMEI que no existe de uno que si existe pero esta fuera del
     * origen elegido, que es el caso que hace perder tiempo.
     */
    public $motivoBusqueda = '';

    public $descripcion = '';
    public $productos = [];

    /** 'estado' o 'tipo_venta'. Cambiarlo vacia la seleccion. */
    public $modo = 'estado';

    public $origen = '';
    public $destino = '';

    public $totalEnOrigen = 0;
    public $seleccionarTodos = false;
    public $compraId = null;

    public function render()
    {
        return view('livewire.producto.modals.producto-estado-masivo-modal', [
            'opciones' => $this->opciones(),
        ]);
    }

    #[On('openProductoEstadoMasivoModal')]
    public function openModal($compraId = null)
    {
        abort_unless(Auth::user()?->can(self::PERMISO), 403);

        $this->compraId = $compraId;
        $this->openModal = true;
    }

    /** @return array<string,string> valor => etiqueta */
    private function opciones(): array
    {
        if ($this->modo === 'tipo_venta') {
            return ProductoTipoVenta::toSelectArray()->toArray();
        }

        // Subconjunto deliberado: Vendido, Credito, Reserva y Reparacion quedan
        // fuera porque arrastran efectos (la venta, la reserva con su seña, la
        // reparacion con sus repuestos) que este modal no ejecuta. Se leen del
        // enum para no repetir cadenas sueltas.
        return collect([
            ProductoEstado::Inventario,
            ProductoEstado::Fuera,
            ProductoEstado::Roto,
        ])->mapWithKeys(fn($caso) => [$caso->value => $caso->label()])->toArray();
    }

    /** El conjunto de origen. Un solo sitio para las tres consultas. */
    private function baseQuery(): Builder
    {
        // Nunca los dados de baja: estan archivados.
        $query = Producto::query()->vigentes();

        if ($this->modo === 'tipo_venta') {
            // Lo vendido ya congelo su tipo de venta en la linea: cambiarlo en
            // el equipo no diria nada verdadero.
            $query->where('productos.tipo_venta', $this->origen)
                ->whereNotIn('productos.estado', ProductoEstado::vendidos());
        } else {
            $query->where('productos.estado', $this->origen);
        }

        if ($this->compraId) {
            // La compra sale de la linea: ya no hay productos.compra_id.
            $query->whereHas('compraDetalle', fn($q) => $q->where('compra_id', $this->compraId));
        }

        return $query;
    }

    /** Fila de la tabla de elegidos. */
    private function aDetalle(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'imei' => $producto->imei,
            'descripcion' => $producto->descripcion,
            'estado' => $this->modo === 'tipo_venta' ? $producto->tipo_venta : ProductoEstado::labelDe($producto->estado),
        ];
    }

    public function updatedModo()
    {
        if (!in_array($this->modo, ['estado', 'tipo_venta'], true)) {
            $this->modo = 'estado';
        }
        $this->origen = '';
        $this->destino = '';
        $this->updatedOrigen();
    }

    // ==================================================================
    // Seleccion
    // ==================================================================

    public function updatedOrigen()
    {
        $this->productos = [];
        $this->searchImei = '';
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';
        $this->seleccionarTodos = false;

        $this->totalEnOrigen = empty($this->origen) ? 0 : $this->baseQuery()->count();
    }

    public function updatedSeleccionarTodos($value)
    {
        if ($value && !empty($this->origen)) {
            $this->productos = $this->baseQuery()->get()
                ->map(fn($producto) => $this->aDetalle($producto))
                ->toArray();
        } else {
            $this->productos = [];
        }
    }

    private function puedeBuscar(): bool
    {
        return !empty($this->origen);
    }

    public function updatedSearchImei($value)
    {
        // Se vacia lo primero y siempre: al borrar caracteres se quedaban en
        // pantalla los resultados del termino anterior.
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';

        $termino = trim((string) $value);

        if ($termino === '') {
            return;
        }

        if (empty($this->origen)) {
            $this->motivoBusqueda = 'Elige primero un origen.';
            return;
        }

        if (mb_strlen($termino) < self::MIN_BUSQUEDA) {
            $this->motivoBusqueda = 'Escribe al menos ' . self::MIN_BUSQUEDA . ' dígitos del IMEI.';
            return;
        }

        $idsExistentes = collect($this->productos)->pluck('id')->toArray();

        $this->filteredProductos = $this->baseQuery()
            // La vista pinta modelo->nombre en cada fila: sin esto son diez
            // consultas mas por cada tecleo.
            ->with('modelo:id,nombre')
            ->buscarPorImei($termino)
            ->whereNotIn('id', $idsExistentes)
            ->take(10)
            ->get();

        if ($this->filteredProductos->isEmpty()) {
            $this->motivoBusqueda = $this->motivoSinResultados($termino, $idsExistentes);
        }
    }

    /**
     * Por que no salio nada. Una consulta mas, y solo cuando la lista viene
     * vacia. Los motivos son, uno a uno, lo que descartan baseQuery() y el
     * whereNotIn de arriba, asi que el mensaje no puede contradecir a la
     * busqueda.
     */
    private function motivoSinResultados(string $termino, array $idsExistentes): string
    {
        $producto = Producto::buscarPorImei($termino)->first();

        if (!$producto) {
            return "Ningún IMEI ni SKU coincide con \"{$termino}\".";
        }

        if (in_array($producto->id, $idsExistentes)) {
            return "El IMEI {$producto->imei} ya está en la lista de abajo.";
        }

        if ($this->compraId && $producto->compraDetalle?->compra_id != $this->compraId) {
            return "El IMEI {$producto->imei} no pertenece a esta compra.";
        }

        if ($producto->estaDadoDeBaja()) {
            return "El IMEI {$producto->imei} está dado de baja.";
        }

        if ($this->modo === 'tipo_venta') {
            return in_array($producto->estado, ProductoEstado::vendidos(), true)
                ? "El IMEI {$producto->imei} ya está vendido."
                : "El IMEI {$producto->imei} existe, pero su tipo de venta es \"{$producto->tipo_venta}\" y el origen es \"{$this->origen}\".";
        }

        return "El IMEI {$producto->imei} existe, pero su estado es \"" . ProductoEstado::labelDe($producto->estado) . "\" y el origen es \"" . ProductoEstado::labelDe($this->origen) . "\".";
    }

    /**
     * Enter en el buscador: lo que manda la pistola (o la camara) al leer el
     * IMEI o el SKU de un equipo. Con una coincidencia exacta lo agrega y deja
     * el campo listo para el siguiente; si no, muestra la lista y el motivo.
     */
    public function elegirPorCodigo(?string $codigo = null): void
    {
        $codigo = trim((string) $codigo);
        $idsExistentes = collect($this->productos)->pluck('id')->toArray();

        $producto = $codigo === '' || !$this->puedeBuscar() ? null : $this->unicoPorCodigo(
            $this->baseQuery()
                ->whereNotIn('id', $idsExistentes)
                ->where(fn($q) => $q->where('imei', $codigo)->orWhere('sku', $codigo))
                ->limit(2)
                ->get(),
            $codigo,
            ['imei', 'sku'],
        );

        if ($producto) {
            $this->selectProducto($producto->imei);

            return;
        }

        $this->searchImei = $codigo;
        $this->updatedSearchImei($codigo);

        if ($codigo !== '' && $this->motivoBusqueda !== '') {
            toastr()->warning($this->motivoBusqueda);
        }
    }

    public function selectProducto($imei)
    {
        // Se revalida contra el conjunto de origen. Antes era un
        // `where('imei', ...)` a secas, asi que buscar, cambiar el origen y
        // pulsar una sugerencia vieja colaba un producto que no pertenecia.
        $productoFound = $this->baseQuery()->where('imei', $imei)->first();

        if (!$productoFound)
            return;

        foreach ($this->productos as $producto) {
            if ($producto['id'] === $productoFound->id)
                return;
        }

        array_unshift($this->productos, $this->aDetalle($productoFound));

        $this->searchImei = '';
        $this->filteredProductos = [];
        $this->motivoBusqueda = '';
    }

    public function eliminarDetalle($index)
    {
        unset($this->productos[$index]);
        $this->productos = array_values($this->productos);

        // Ya no estan todos.
        $this->seleccionarTodos = false;
    }

    // ==================================================================
    // Guardado
    // ==================================================================

    public function store()
    {
        // La unica capa que protege el dato de verdad.
        abort_unless(Auth::user()?->can(self::PERMISO), 403);

        $valores = array_keys($this->opciones());

        $this->validate([
            'productos' => 'required|array|min:1',
            'descripcion' => 'required|string|min:1|max:255',
            // Rule::in y no `in:...`: en cuanto un valor lleve una coma, la
            // regla en cadena se partiria en silencio.
            'origen' => ['required', Rule::in($valores)],
            'destino' => ['required', Rule::in($valores)],
        ], [
            'origen.required' => 'Debe seleccionar un origen.',
            'destino.required' => 'Debe seleccionar un destino.',
        ]);

        if ($this->origen === $this->destino) {
            $this->addError('destino', 'El destino debe ser diferente al origen.');
            return;
        }

        if ($this->modo === 'tipo_venta') {
            $this->cambiarTiposDeVenta();
            return;
        }

        $estados = app(EstadoProductoService::class);

        // Una sola transaccion para el lote entero, no una por producto: con
        // una por iteracion, fallar en el quinto de diez dejaria cuatro
        // cambiados y seis sin tocar, y el usuario viendo un error generico sin
        // saber cuales se aplicaron.
        DB::transaction(function () use ($estados) {
            foreach ($this->productos as $detalle) {
                // La precondicion exige que siga en el origen ELEGIDO. Si uno
                // se movio mientras el modal estaba abierto, revienta el lote
                // entero con un mensaje que lo nombra, en lugar de saltarselo en
                // silencio: el usuario eligio ese conjunto y tiene derecho a
                // saber que ya no es el que creia.
                //
                // Sin exigirPermiso: este modal tiene su propia puerta
                // (producto.estado-masivo) y su lista de destinos es un
                // subconjunto fijo, no la filtrada por permisos del select del
                // modal individual.
                $estados->cambiar(
                    (int) $detalle['id'],
                    ProductoEstado::from($this->origen),
                    ProductoEstado::from($this->destino),
                    $this->descripcion,
                );
            }
        });

        toastr()->success('Productos cambiados de estado exitosamente');

        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }

    /**
     * El mismo contrato que EstadoProductoService::cambiar(), sobre tipo_venta:
     * lectura bloqueada, exigir que siga en el origen elegido (si no, revienta
     * el lote entero nombrando el equipo) y anotar() antes del save para que
     * el Observer escriba UNA fila con la frase y el diff.
     */
    private function cambiarTiposDeVenta(): void
    {
        $destino = ProductoTipoVenta::from($this->destino);

        DB::transaction(function () use ($destino) {
            foreach ($this->productos as $detalle) {
                $producto = Producto::whereKey((int) $detalle['id'])->lockForUpdate()->firstOrFail();

                if ($producto->estaDadoDeBaja()
                    || in_array($producto->estado, ProductoEstado::vendidos(), true)
                    || $producto->tipo_venta !== $this->origen) {
                    throw ValidationException::withMessages([
                        'productos' => "El equipo {$producto->imei} cambió mientras el modal estaba abierto"
                            . " (tipo de venta \"{$producto->tipo_venta}\", estado " . ProductoEstado::labelDe($producto->estado) . '). No se aplicó ningún cambio.',
                    ]);
                }

                $producto->anotar('editado', "Tipo de venta: {$this->origen} → {$destino->value}. {$this->descripcion}");
                $producto->update(['tipo_venta' => $destino->value]);
            }
        });

        toastr()->success('Tipo de venta actualizado');

        $this->dispatch('refreshProductoTable');
        $this->closeModal();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
