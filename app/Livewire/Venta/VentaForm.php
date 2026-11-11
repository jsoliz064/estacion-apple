<?php

namespace App\Livewire\Venta;

use App\Enums\LineaTipo;
use App\Enums\Moneda;
use App\Enums\ProductoAlmacenamiento;
use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Models\Cliente;
use App\Models\Color;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\ProductoRegalo;
use App\Models\ProductoModelo;
use App\Models\Reserva;
use App\Models\Sucursal;
use App\Models\TipoCambio;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Services\PagoService;
use App\Services\RepuestosDeReparacionService;
use App\Services\VentaService;
use App\Traits\CarritoBuscadorTrait;
use App\Traits\ClienteBuscadorTrait;
use App\Traits\FilasDePagoFormTrait;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Support\Dinero;

/**
 * Registrar o editar una venta: equipos, repuestos y accesorios en el mismo
 * documento, en USD. Lo escribe VentaService.
 *
 * Un solo buscador (IMEI, codigo de barras o nombre) y el catalogo. Los
 * equipos llevan garantia y, si tuvieron reparaciones, se les pueden cobrar las
 * piezas montadas (RepuestosDeReparacionService).
 *
 * Los importes que se ven aqui son una vista previa: el costo y los totales
 * reales los calcula el servicio releyendo la base.
 *
 * EL COBRO: al crear, lo cobrado va en $pagos (uno o varios metodos). Si no
 * cubre el total, la venta queda a credito y exige cliente con ficha; lo
 * decide PagoService. Al editar, los pagos se ven en solo lectura: los cobros
 * nuevos van por el modal de cobro (CobroModal).
 *
 * Una fila de pago puede ser en USD (dolares y tipo de cambio). La venta que
 * llega de una reserva (?reserva=) trae el equipo, el cliente y la seña, que
 * entra como pago. La permuta (equipo recibido) es otro pago, que se carga en
 * su propio bloque (PermutaService).
 */
class VentaForm extends Component
{
    use CarritoBuscadorTrait;
    use ClienteBuscadorTrait;
    use GuardadoIdempotenteTrait;
    use FilasDePagoFormTrait;

    /** Null al crear. #[Locked]: decide que venta se reescribe. */
    #[Locked]
    public ?int $ventaId = null;

    /** sucursal_id, cliente_id, cliente, descuento, mano_obra */
    public array $venta = [];

    /**
     * [['tipo','id','descripcion','codigo','serial','cantidad','precio','descuento',
     *   'garantia_meses','stock','repuestos_elegibles'], ...]
     * codigo: el IMEI del equipo o el UPC del articulo; serial: el «Serial o
     * UPC» del equipo (null en los articulos).
     */
    public array $lineas = [];

    /** Cobros de taller por equipo: producto_id => [lineas]. */
    public array $repuestosVenta = [];

    /** Cobros de taller ya registrados en la venta (edicion, solo lectura). */
    public array $cobrosExistentes = [];

    /**
     * Los accesorios regalados con cada equipo: producto_id => [['nombre',
     * 'cantidad'], ...]. Solo para mostrarlos (precio 0, sin quitar): no viajan
     * al guardar. VentaService los relee de la base y crea sus lineas.
     */
    public array $regalos = [];

    /** La reserva que se concreta (?reserva=). #[Locked]: decide que equipo y que seña. */
    #[Locked]
    public ?int $reservaId = null;

    /** La seña de esa reserva, para mostrarla y contarla como cobrada: ['monto','metodo']. */
    #[Locked]
    public array $sena = [];

    /** El equipo recibido en permuta (vacio si no hay). Lo valida PermutaService. */
    public array $permuta = [];
    public bool $conPermuta = false;

    /** Pagos ya registrados (edicion, solo lectura): [['fecha','metodo','monto'], ...]. */
    public array $pagosExistentes = [];

    /**
     * El descuento y la mano de obra de la cabecera se pueden escribir en Bs
     * (pedido de la tienda: el vendedor dice «te lo dejo en 7800 Bs» y antes
     * tenia que calcular a mano cuantos USD de descuento eran 200 Bs). Solo es
     * la pantalla: venta.descuento y venta.mano_obra siguen en USD, que es lo
     * que se guarda; en Bs, USD = Bs / tcVenta().
     */
    public bool $descuentoEnBs = false;
    public $descuentoBs = '';
    public bool $manoObraEnBs = false;
    public $manoObraBs = '';

    /** El TC congelado de la venta que se edita (al crear, null: el del dia). */
    #[Locked]
    public ?float $tcVentaGuardada = null;

    public function mount(?int $ventaId = null): void
    {
        if ($ventaId) {
            abort_unless(Auth::user()?->can('venta.edit'), 403);
            $this->cargarVenta($ventaId);

            return;
        }

        abort_unless(Auth::user()?->can('venta.create'), 403);

        // Una clave por apertura: un reintento tras cortarse la red trae la
        // misma y no crea una segunda venta.
        $this->nuevaClaveIdempotencia();
        $this->venta = ['sucursal_id' => null, 'cliente_id' => null, 'cliente' => null, 'descuento' => 0, 'mano_obra' => 0];
        $this->pagos = [$this->filaPago(MetodoPago::activos()->value('id'))];

        // Concretar una reserva: el equipo reservado, su cliente (fijo) y la seña.
        $reservaId = (int) request()->query('reserva', 0);
        if ($reservaId && $reserva = Reserva::activas()->with(['producto.modelo', 'cliente', 'metodo'])->find($reservaId)) {
            $sucursal = Sucursal::activas()->find($reserva->producto?->sucursal_id);
            if ($sucursal && $reserva->producto?->estado === ProductoEstado::Reserva->value) {
                $this->reservaId = $reserva->id;
                $this->sena = ['monto' => (float) $reserva->sena, 'metodo' => $reserva->metodo?->nombre];
                $this->venta['sucursal_id'] = $sucursal->id;
                $this->venta['cliente_id'] = $reserva->cliente_id;
                $this->venta['cliente'] = $reserva->cliente?->nombre;
                $this->agregarLineaEquipo($reserva->producto);
            }

            return;
        }

        // "Vender" desde la ficha del equipo: llega con ?producto= y la sucursal
        // del equipo ya puesta.
        $productoId = (int) request()->query('producto', 0);
        if ($productoId && $producto = Producto::disponibles()->find($productoId)) {
            $sucursal = Sucursal::activas()->find($producto->sucursal_id);
            if ($sucursal) {
                $this->venta['sucursal_id'] = $sucursal->id;
                $this->agregarLinea(LineaTipo::Producto->value, $producto->id);
            }
        }
    }

    private function cargarVenta(int $ventaId): void
    {
        $venta = Venta::with(['detalles.producto.modelo', 'detalles.repuesto', 'detalles.accesorio', 'pagos.metodo', 'pagos.producto.modelo'])->findOrFail($ventaId);

        $this->pagosExistentes = $venta->pagos->sortBy('id')->map(fn($p) => [
            'fecha' => $p->fecha->format('d/m/Y H:i'),
            'metodo' => $p->descripcion(),
            'monto' => (float) $p->monto,
        ])->values()->all();

        $this->ventaId = $venta->id;
        $this->tcVentaGuardada = (float) $venta->tipo_cambio ?: null;
        $this->venta = [
            'sucursal_id' => $venta->sucursal_id,
            'cliente_id' => $venta->cliente_id,
            'cliente' => $venta->cliente,
            'descuento' => (float) $venta->descuento,
            'mano_obra' => (float) $venta->mano_obra,
        ];

        foreach ($venta->detalles as $d) {
            if ($d->esRegalo()) {
                $this->regalos[$d->producto_asociado_id][] = [
                    'nombre' => $d->accesorio?->nombre,
                    'cantidad' => (int) $d->cantidad,
                ];
                continue;
            }

            if ($d->esCobro()) {
                $this->cobrosExistentes[] = [
                    'nombre' => $d->repuesto?->nombre,
                    'cantidad' => (int) $d->cantidad,
                    'subtotal' => (float) $d->subtotal,
                ];
                continue;
            }

            $articulo = $d->articulo();
            $this->lineas[] = [
                'tipo' => $d->tipo,
                'id' => (int) $articulo->id,
                // El equipo con el mismo texto que al agregarlo: descripcion()
                // ya trae el IMEI, y la linea lo pintaba dos veces.
                'descripcion' => $d->producto_id ? $this->descripcionEquipo($articulo) : $d->descripcion(),
                'codigo' => $d->producto_id ? $articulo->imei : $articulo->upc,
                'serial' => $d->producto_id ? $articulo->upc : null,
                'cantidad' => (int) $d->cantidad,
                'precio' => (float) $d->precio,
                'descuento' => (float) $d->descuento,
                'garantia_meses' => $d->garantia_meses,
                // En edicion, lo que ya tiene la linea tambien esta disponible.
                'stock' => $d->producto_id ? 1 : $articulo->stockEn($venta->sucursal_id) + (int) $d->cantidad,
                'repuestos_elegibles' => $d->producto_id ? app(RepuestosDeReparacionService::class)->contarElegibles($articulo->id) : 0,
                'con_producto_id' => $d->producto_asociado_id,
            ];
        }
    }

    public function esEdicion(): bool
    {
        return $this->ventaId !== null;
    }

    // ----------------------------------------------------------- cliente

    /** cliente_id es el enlace; `cliente` el nombre CONGELADO en el documento. */
    protected function fijarCliente(?Cliente $cliente): void
    {
        // La venta de una reserva es para SU cliente.
        if ($this->reservaId) {
            toastr()->info('La venta de una reserva es para el cliente que reservó.');

            return;
        }

        $this->venta['cliente_id'] = $cliente?->id;
        $this->venta['cliente'] = $cliente?->nombre;
    }

    public function clienteIdElegido(): ?int
    {
        return !empty($this->venta['cliente_id']) ? (int) $this->venta['cliente_id'] : null;
    }

    // ----------------------------------------------------------- buscador

    protected function tiposBuscables(): array
    {
        return LineaTipo::cases();
    }

    public function sucursalDelDocumento(): ?int
    {
        return !empty($this->venta['sucursal_id']) ? (int) $this->venta['sucursal_id'] : null;
    }

    public function motivoSinSucursal(): string
    {
        return 'Elige primero la sucursal de la venta.';
    }

    protected function clavesEnCarrito(): array
    {
        return array_map(fn($l) => $l['tipo'] . ':' . $l['id'], $this->lineas);
    }

    protected function agregarLinea(string $tipo, int $id): void
    {
        $lineaTipo = LineaTipo::from($tipo);
        $sucursalId = $this->sucursalDelDocumento();

        if ($lineaTipo === LineaTipo::Producto) {
            $producto = Producto::with('modelo')->disponibles()->find($id);

            if (!$producto) {
                toastr()->warning('Ese equipo ya no está disponible para vender.');

                return;
            }

            $this->agregarLineaEquipo($producto);

            return;
        }

        $this->agregarLineaArticulo($lineaTipo, $id, $sucursalId);
    }

    private function agregarLineaEquipo(Producto $producto): void
    {
        array_unshift($this->lineas, [
            'tipo' => LineaTipo::Producto->value,
            'id' => $producto->id,
            'descripcion' => $this->descripcionEquipo($producto),
            'codigo' => $producto->imei,
            'serial' => $producto->upc,
            'cantidad' => 1,
            'precio' => (float) $producto->precio,
            'descuento' => 0,
            'garantia_meses' => 3,
            'stock' => 1,
            // Se cuenta una vez al agregar: pintarlo por render seria una
            // consulta por fila en cada peticion.
            'repuestos_elegibles' => app(RepuestosDeReparacionService::class)->contarElegibles($producto->id),
            'con_producto_id' => null,
        ]);

        $regalos = $this->regalosDe($producto->id);

        if ($regalos !== []) {
            $this->regalos[$producto->id] = $regalos;
        }
    }

    private function descripcionEquipo(Producto $producto): string
    {
        return trim(($producto->modelo?->nombre ?? 'Equipo') . ' ' . $producto->almacenamiento . ' ' . $producto->color);
    }

    /** Los regalos del equipo, para mostrarlos bajo su linea. */
    private function regalosDe(int $productoId): array
    {
        return ProductoRegalo::with('accesorio:id,nombre')->where('producto_id', $productoId)->orderBy('id')->get()
            ->map(fn($r) => ['nombre' => $r->accesorio?->nombre, 'cantidad' => (int) $r->cantidad])
            ->all();
    }

    private function agregarLineaArticulo(LineaTipo $lineaTipo, int $id, ?int $sucursalId): void
    {
        $tipo = $lineaTipo->value;
        $articulo = $lineaTipo->articulo()->buscar($id);

        if (!$articulo) {
            return;
        }

        $stock = $articulo->stockEn($sucursalId);

        if ($stock < 1) {
            toastr()->warning("No hay stock de «{$articulo->nombre}» en esta sucursal.");

            return;
        }

        array_unshift($this->lineas, [
            'tipo' => $tipo,
            'id' => $articulo->id,
            'descripcion' => $articulo->nombre,
            'codigo' => $articulo->upc,
            'cantidad' => 1,
            'precio' => (float) $articulo->precio,
            'descuento' => 0,
            'garantia_meses' => null,
            'stock' => $stock,
            'repuestos_elegibles' => 0,
            // Con un solo equipo en la venta, lo normal es que vaya con el.
            'con_producto_id' => count($equipos = $this->equiposEnLaVenta()) === 1 ? array_key_first($equipos) : null,
        ]);
    }

    /** [producto_id => descripcion] de los equipos de la venta, para "Con el equipo". */
    public function equiposEnLaVenta(): array
    {
        return collect($this->lineas)->where('tipo', LineaTipo::Producto->value)
            ->mapWithKeys(fn($l) => [$l['id'] => $l['descripcion'] . ' · ' . substr((string) $l['codigo'], -5)])
            ->all();
    }

    /** Lo que llega del catalogo de equipos (ProductoSelectorModal). */
    #[On('productosSeleccionados')]
    public function agregarProductos(array $ids): void
    {
        foreach ($ids as $id) {
            $this->seleccionarResultado(LineaTipo::Producto->value, (int) $id);
        }
    }

    public function abrirSelectorEquipos(): void
    {
        if ($this->sucursalDelDocumento() === null) {
            toastr()->warning($this->motivoSinSucursal());

            return;
        }

        $this->dispatch(
            'openProductoSelectorModal',
            excluidos: collect($this->lineas)->where('tipo', LineaTipo::Producto->value)->pluck('id')->values()->all(),
            sucursalId: $this->sucursalDelDocumento(),
        );
    }

    public function quitarLinea(int $index): void
    {
        if (!isset($this->lineas[$index])) {
            return;
        }

        // Si el equipo sale, sus cobros de taller se van con el y sus
        // accesorios quedan sueltos.
        if ($this->lineas[$index]['tipo'] === LineaTipo::Producto->value) {
            $productoId = $this->lineas[$index]['id'];

            if ($this->reservaId && $productoId === (int) Reserva::whereKey($this->reservaId)->value('producto_id')) {
                toastr()->warning('El equipo reservado no se quita: es la venta de la reserva.');

                return;
            }

            unset($this->repuestosVenta[$productoId], $this->regalos[$productoId]);
            foreach ($this->lineas as $i => $l) {
                if ((int) ($l['con_producto_id'] ?? 0) === (int) $productoId) {
                    $this->lineas[$i]['con_producto_id'] = null;
                }
            }
        }

        unset($this->lineas[$index]);
        $this->lineas = array_values($this->lineas);
    }

    /** La sucursal no cambia con lineas cargadas (ni en edicion): el stock sale de ella. */
    public function updatedVentaSucursalId(): void
    {
        $this->busqueda = '';
        $this->resultados = [];
    }

    // ------------------------------------------------- cobros de taller

    public function abrirRepuestosDe($productoId): void
    {
        $this->dispatch(
            'openProductoRepuestosVentaModal',
            productoId: (int) $productoId,
            yaElegidos: $this->repuestosVenta[$productoId] ?? [],
        );
    }

    #[On('repuestosDeReparacionSeleccionados')]
    public function guardarRepuestosDe($productoId, array $lineas): void
    {
        if (empty($lineas)) {
            unset($this->repuestosVenta[$productoId]);

            return;
        }

        $this->repuestosVenta[$productoId] = $lineas;
    }

    // ------------------------------------------------------- totales

    public function subtotalLinea(array $l): float
    {
        $bruto = (float) $l['precio'] * max(1, (int) $l['cantidad']);

        return round($bruto - min((float) $l['descuento'], $bruto), 2);
    }

    public function totalCobrosNuevos(): float
    {
        $total = 0;
        foreach ($this->repuestosVenta as $lineas) {
            foreach ($lineas as $linea) {
                $total += (float) ($linea['subtotal'] ?? 0);
            }
        }

        return round($total, 2);
    }

    public function totales(): array
    {
        $subtotal = array_sum(array_map(fn($l) => $this->subtotalLinea($l), $this->lineas))
            + $this->totalCobrosNuevos()
            + array_sum(array_column($this->cobrosExistentes, 'subtotal'));

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal - (float) ($this->venta['descuento'] ?: 0) + (float) ($this->venta['mano_obra'] ?: 0), 2),
        ];
    }

    // ------------------------------------------------------- cobro

    // ------------------------------------------------------- permuta

    public function abrirPermuta(): void
    {
        $this->conPermuta = true;
        $this->montoTocado = false;
        $this->permuta = [
            'producto_modelo_id' => '', 'imei' => '', 'almacenamiento' => '128GB', 'color' => '',
            'estado_grado' => ProductoGrado::SemiNuevo->value, 'bateria_porcentaje' => 85, 'valor' => '',
        ];
    }

    public function quitarPermuta(): void
    {
        $this->conPermuta = false;
        $this->permuta = [];
        $this->resetErrorBag();
    }

    public function valorPermuta(): float
    {
        return $this->conPermuta ? round((float) ($this->permuta['valor'] ?? 0 ?: 0), 2) : 0.0;
    }

    /** La seña y la permuta: lo cobrado que no es una fila de pago. */
    public function cobradoFijo(): float
    {
        return round((float) ($this->sena['monto'] ?? 0) + $this->valorPermuta(), 2);
    }

    public function cobradoPrevisto(): float
    {
        if ($this->esEdicion()) {
            return round(array_sum(array_column($this->pagosExistentes, 'monto')), 2);
        }

        return round($this->sumaFilas() + $this->cobradoFijo(), 2);
    }

    public function saldoPrevisto(): float
    {
        return round($this->totales()['total'] - $this->cobradoPrevisto(), 2);
    }

    /** El unico pago (en USD) sigue a lo que falta mientras nadie lo toque. */
    private function seguirTotal(): void
    {
        if (!$this->esEdicion()) {
            $this->seguirMonto($this->totales()['total'] - $this->cobradoFijo());
        }
    }

    // ------------------------------------------- descuento y mano de obra en Bs

    /** Los campos de la cabecera que se escriben en USD o Bs: [enBs, Bs]. */
    private const MONTOS_CABECERA = [
        'descuento' => ['descuentoEnBs', 'descuentoBs'],
        'mano_obra' => ['manoObraEnBs', 'manoObraBs'],
    ];

    /** El TC de los Bs de la cabecera: el de la venta al editar, el del dia al crear (el que congelara). */
    public function tcVenta(): float
    {
        return $this->tcVentaGuardada ?: TipoCambio::actual();
    }

    public function monedaCabecera(string $campo, string $moneda): void
    {
        [$enBs, $bs] = self::MONTOS_CABECERA[$campo] ?? abort(400);
        $this->$enBs = $moneda === Moneda::alterna()->value;

        // Pasar a Bs propone en Bs lo que ya decia en USD.
        if ($this->$enBs) {
            $usd = (float) ($this->venta[$campo] ?: 0);
            $this->$bs = $usd > 0 ? round($usd * $this->tcVenta(), 2) : '';
        }
    }

    public function updatedDescuentoBs(): void
    {
        $this->sincronizarMontoCabecera('descuento');
    }

    public function updatedManoObraBs(): void
    {
        $this->sincronizarMontoCabecera('mano_obra');
    }

    private function sincronizarMontoCabecera(string $campo): void
    {
        [$enBs, $bs] = self::MONTOS_CABECERA[$campo];

        if ($this->$enBs) {
            $this->venta[$campo] = is_numeric($this->$bs) ? round((float) $this->$bs / $this->tcVenta(), 2) : 0;
        }
    }

    // ------------------------------------------------------- precio por linea

    public function puedeEditarPrecio(): bool
    {
        return (bool) Auth::user()?->can('venta.editar-precio');
    }

    /**
     * Sin venta.editar-precio, el precio de cada linea es el del articulo (o,
     * al editar, el que ya tenia la linea: no se le cambia a quien no puede la
     * venta que hizo otro). La pantalla bloquea el campo; esto cubre una
     * peticion armada a mano. El descuento de la linea sigue libre.
     */
    private function exigirPermisoDePrecio(): void
    {
        if ($this->puedeEditarPrecio()) {
            return;
        }

        $guardadas = $this->esEdicion()
            ? VentaDetalle::where('venta_id', $this->ventaId)->get()
                ->reject(fn($d) => $d->esRegalo() || $d->esCobro())
                ->keyBy(fn($d) => $d->tipo . '-' . ($d->producto_id ?? $d->repuesto_id ?? $d->accesorio_id))
            : collect();
        $errores = [];

        foreach ($this->lineas as $i => $l) {
            $guardada = $guardadas->get($l['tipo'] . '-' . $l['id']);
            $tipo = LineaTipo::from($l['tipo'])->articulo();
            $articulo = $guardada ? null : ($tipo ? $tipo->buscar((int) $l['id']) : Producto::find((int) $l['id']));
            $precio = (float) ($guardada?->precio ?? $articulo?->precio ?? 0);

            if (round((float) $l['precio'], 2) !== round($precio, 2)) {
                $errores["lineas.$i.precio"] = "No tienes permiso para cambiar el precio de «{$l['descripcion']}» (" . Dinero::usd($precio) . '). Puedes darle un descuento.';
            }
        }

        if ($errores) {
            throw ValidationException::withMessages($errores);
        }
    }

    // ------------------------------------------------------- guardar

    protected function rules(): array
    {
        return [
            'venta.sucursal_id' => $this->esEdicion() ? 'nullable' : 'required|integer|exists:sucursales,id,activa,1',
            'venta.descuento' => 'nullable|numeric|min:0',
            'venta.mano_obra' => 'nullable|numeric|min:0',
            'lineas' => 'required|array|min:1',
            'lineas.*.cantidad' => 'required|integer|min:1',
            'lineas.*.precio' => 'required|numeric|min:0',
            'lineas.*.descuento' => 'nullable|numeric|min:0',
            'lineas.*.garantia_meses' => 'nullable|integer|min:0|max:60',
            'pagos.*.monto' => 'nullable|numeric|min:0',
        ];
    }

    protected function messages(): array
    {
        return [
            'venta.sucursal_id.required' => 'Selecciona la sucursal de la venta.',
            'venta.sucursal_id.exists' => 'La sucursal elegida no existe o está desactivada.',
            'lineas.required' => 'Agrega al menos un equipo, repuesto o accesorio.',
            'lineas.min' => 'Agrega al menos un equipo, repuesto o accesorio.',
            'lineas.*.cantidad.min' => 'La cantidad debe ser al menos 1.',
            'pagos.*.monto.min' => 'Un pago no puede ser negativo.',
        ];
    }

    public function guardar()
    {
        $this->sincronizarMontoCabecera('descuento');
        $this->sincronizarMontoCabecera('mano_obra');
        $this->seguirTotal();
        $this->validate();
        $this->exigirPermisoDePrecio();

        $cabecera = [
            'sucursal_id' => $this->sucursalDelDocumento(),
            'cliente_id' => $this->venta['cliente_id'] ?? null,
            'cliente' => $this->venta['cliente'] ?? null,
            'descuento' => (float) ($this->venta['descuento'] ?: 0),
            'mano_obra' => (float) ($this->venta['mano_obra'] ?: 0),
            'reserva_id' => $this->reservaId,
        ];
        $lineas = array_map(fn($l) => [
            'tipo' => $l['tipo'], 'id' => $l['id'], 'cantidad' => (int) $l['cantidad'],
            'precio' => (float) $l['precio'], 'descuento' => (float) ($l['descuento'] ?: 0),
            'garantia_meses' => $l['garantia_meses'] !== '' ? $l['garantia_meses'] : null,
            'con_producto_id' => $l['con_producto_id'] ?? null,
        ], $this->lineas);

        $servicio = app(VentaService::class);

        if ($this->esEdicion()) {
            abort_unless(Auth::user()?->can('venta.edit'), 403);

            DB::transaction(fn() => $servicio->actualizar(Venta::lockForUpdate()->findOrFail($this->ventaId), $cabecera, $lineas, $this->repuestosVenta));
            toastr()->success('Venta actualizada');

            return redirect()->route('ventas.detalles', $this->ventaId);
        }

        abort_unless(Auth::user()?->can('venta.create'), 403);

        // El reintento: la primera peticion ya cerro. Se responde con la venta
        // que existe en vez de crear una segunda.
        if ($ya = $this->yaGuardado(Venta::class)) {
            $this->avisarYaGuardado($ya, 'venta');

            return redirect()->route('ventas.detalles', $ya->id);
        }

        try {
            $venta = DB::transaction(fn() => $servicio->registrar(
                $cabecera, $lineas, $this->repuestosVenta, $this->pagos, Auth::user(), $this->claveIdempotencia,
                $this->conPermuta ? $this->permuta : null,
            ));
        } catch (QueryException $e) {
            // Dos peticiones a la vez con la misma clave: la otra commiteo y la
            // nuestra ya revirtio entera, asi que releer es seguro.
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Venta::class)) {
                $this->avisarYaGuardado($ya, 'venta');

                return redirect()->route('ventas.detalles', $ya->id);
            }

            throw $e;
        }

        toastr()->success($venta->aCredito()
            ? 'Venta registrada a crédito: saldo ' . Dinero::usd($venta->saldoPendiente())
            : 'Venta registrada exitosamente');

        return redirect()->route('ventas.detalles', $venta->id);
    }

    public function render()
    {
        $this->seguirTotal();

        // La deuda que ya tiene el cliente: se avisa, no se bloquea (docs/04).
        $deudaCliente = 0.0;
        if ($id = $this->clienteIdElegido()) {
            $deudaCliente = round((float) Venta::where('cliente_id', $id)->conSaldo()
                ->when($this->ventaId, fn($q) => $q->whereKeyNot($this->ventaId))
                ->sum('saldo'), 2);
        }

        return view('livewire.venta.venta-form', [
            'sucursales' => $this->esEdicion()
                ? Sucursal::paraSelect($this->sucursalDelDocumento())
                : Sucursal::activas()->orderBy('nombre')->get(),
            'metodos' => $this->esEdicion() ? collect() : MetodoPago::activos()->get(),
            'deudaCliente' => $deudaCliente,
            'puedeEditarPrecio' => $this->puedeEditarPrecio(),
            'equiposVenta' => $this->equiposEnLaVenta(),
            'modelosPermuta' => $this->conPermuta ? ProductoModelo::orderBy('nombre')->get(['id', 'nombre']) : collect(),
            'almacenamientos' => ProductoAlmacenamiento::cases(),
            // Los del modelo recibido (Color::paraModelo); cambian al elegirlo.
            'colores' => $this->conPermuta ? Color::paraModelo(((int) ($this->permuta['producto_modelo_id'] ?? 0)) ?: null) : collect(),
            'grados' => ProductoGrado::cases(),
        ]);
    }
}
