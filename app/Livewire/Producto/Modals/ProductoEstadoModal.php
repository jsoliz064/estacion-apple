<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ArticuloTipo;
use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\ProductoReparacion;
use App\Models\ProductoReparacionRepuesto;
use App\Models\Repuesto;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use App\Models\Tecnicos;
use App\Services\AnulacionVentaService;
use App\Services\EstadoProductoService;
use App\Services\StockService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use App\Traits\RepuestosReparacionFormTrait;
use Livewire\Component;

/**
 * Cambiar el estado de un equipo a mano: Inventario, Reparacion (con tecnico y
 * piezas), Fuera (salio del local), Roto. Todo en Bs. Reserva la escribe
 * solo ReservaService: aqui se ve, se concreta o se cancela.
 *
 * NO vende: la venta se hace en la pantalla de ventas (el boton "Vender" lleva
 * a ventas/crear?producto=). Antes este modal era una tercera puerta de venta
 * con su propia copia de la logica. Con el equipo vendido, muestra la venta y
 * permite anular su linea (AnulacionVentaService).
 *
 * Fuera y Roto NO son bajas: la baja es aparte (ProductoBajaModal).
 */
class ProductoEstadoModal extends Component
{
    use RepuestosReparacionFormTrait;

    public $openModal = false;

    /**
     * #[Locked] en los dos: el id del producto y el estado del que partimos son
     * la identidad de la operacion. Sin esto, un payload podia pedir el cambio
     * sobre otro telefono o declarar un origen falso para saltarse la
     * precondicion de EstadoProductoService. OJO: #[Locked] NO protege las
     * claves de los arrays de abajo ($reparacion['id']): se revalidan.
     */
    #[Locked]
    public $producto;

    /** El estado con el que se abrio el modal. La precondicion lo exige. */
    #[Locked]
    public $estadoOrigen;

    public $estado;

    /** La nota de Fuera o Roto (a quien se le dio, que le pasa). */
    public $nota = '';

    public $reparacion = [];
    public $tecnico;
    public $tecnicos;


    /**
     * Las piezas de una reparacion NUEVA. Cada linea lleva su sucursal_id (la
     * que se eligio en el selector) y se CONGELA: al terminar, el equipo se
     * muda al Almacen, y quitar la pieza despues tiene que devolver el stock a
     * la sucursal original. Las de una reparacion abierta se editan en
     * ReparacionEditModal.
     */
    public $repuestos = [];

    /** Estados que llevan nota obligatoria. */
    private const CON_NOTA = ['Fuera', 'Roto'];

    public function render()
    {
        return view('livewire.producto.modals.producto-estado-modal', [
            'productoEstados' => ProductoEstado::toSelectArrayPermission(),
            'lineaVenta' => $this->openModal && $this->producto ? $this->producto->ventaDetalle()->with('venta.fichaCliente')->first() : null,
            'reserva' => $this->openModal && $this->esReservado() ? $this->producto->reservaActiva()->with(['cliente', 'metodo'])->first() : null,
            'reclamo' => $this->openModal && $this->enReclamo() ? $this->producto->reclamoAbierto()->with('compra.proveedor')->first() : null,
        ]);
    }

    #[On('openProductoEstadoModal')]
    public function openModal($id)
    {
        $producto = Producto::findOrFail($id);
        $this->resetErrorBag();
        $this->tecnicos = Tecnicos::all();
        $this->producto = $producto;
        $this->estado = $producto->estado;
        $this->repuestos = [];
        $this->nota = '';

        // El estado de partida se congela al abrir: es contra este que se
        // compara al guardar, no contra el que haya en la base en ese momento.
        $this->estadoOrigen = $producto->estado;

        if (in_array($producto->estado, self::CON_NOTA, true)) {
            $this->nota = $this->notaVigente()?->descripcion ?? '';
        }

        if ($producto->estado == ProductoEstado::Reparacion->value) {
            $productoReparacion = $producto->ultimaReparacion();
            $this->tecnico = $productoReparacion?->tecnico;
            $this->reparacion = $productoReparacion ? $productoReparacion->toArray() : $this->initialReparacion();
            $this->reparacion['garantia_tecnico'] = (bool) ($this->reparacion['garantia_tecnico'] ?? false);
        }

        $this->openModal = true;
    }

    public function esVendido(): bool
    {
        return $this->producto && in_array($this->producto->estado, ProductoEstado::vendidos(), true);
    }

    public function enReclamo(): bool
    {
        return $this->producto && $this->producto->estado === ProductoEstado::Reclamo->value;
    }

    public function esReservado(): bool
    {
        return $this->producto && $this->producto->estado === ProductoEstado::Reserva->value;
    }

    /** "Reservar" abre el modal de reserva con este equipo (cliente, seña y metodo). */
    public function reservar(): void
    {
        abort_unless(Auth::user()?->can('reserva.create'), 403);

        $this->dispatch('openReservaCreateModal', productoId: $this->producto->id);
        $this->closeModal();
    }

    public function cancelarReserva(): void
    {
        abort_unless(Auth::user()?->can('reserva.cancelar'), 403);

        if ($reserva = $this->producto->reservaActiva()->first()) {
            $this->dispatch('openReservaCancelarModal', $reserva->id);
        }
        $this->closeModal();
    }

    /** Concretar: la venta con el equipo, el cliente y la seña ya cargados. */
    public function concretarReserva()
    {
        abort_unless(Auth::user()?->can('venta.create'), 403);

        $reserva = $this->producto->reservaActiva()->first();

        return $reserva ? redirect()->route('ventas.crear', ['reserva' => $reserva->id]) : null;
    }

    public function updatedEstado()
    {
        if ($this->estado == ProductoEstado::Reparacion->value && empty($this->reparacion['id'])) {
            $this->reparacion = $this->initialReparacion();
        }
    }

    /** "Vender" lleva a la pantalla de ventas con el equipo ya cargado. */
    public function vender()
    {
        abort_unless(Auth::user()?->can('venta.create'), 403);

        return redirect()->route('ventas.crear', ['producto' => $this->producto->id]);
    }

    // REPARACION (en Bs)

    public function initialReparacion()
    {
        return [
            'costo' => 0,
            'costo_repuestos' => 0,
            'costo_total' => 0,
            'fecha_entrega' => now()->format('Y-m-d'),
            'garantia_tecnico' => false,
        ];
    }

    protected function equipoDeLaReparacion(): ?Producto
    {
        return $this->producto;
    }

    public function updatedRepuestos()
    {
        $this->calcularTotalRepuestos();
    }

    public function updatedReparacionCosto()
    {
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionCostoRepuestos()
    {
        $this->calcularTotalReparacion();
    }

    public function calcularTotalRepuestos()
    {
        $total = 0;

        foreach ($this->repuestos as $index => $repuesto) {
            $costo = is_numeric($repuesto['costo']) ? (float) $repuesto['costo'] : 0;
            $cantidad = max(1, (int) $repuesto['cantidad']);
            $this->repuestos[$index]['cantidad'] = $cantidad;
            $this->repuestos[$index]['subtotal_costo'] = round($costo * $cantidad, 2);
            $total += $costo * $cantidad;
        }

        $this->reparacion['costo_repuestos'] = round($total, 2);
        $this->calcularTotalReparacion();
    }

    /** Todo en Bs: costo_total = mano de obra del tecnico + repuestos. */
    public function calcularTotalReparacion()
    {
        $this->reparacion['costo_total'] = round(
            (float) ($this->reparacion['costo'] ?? 0) + (float) ($this->reparacion['costo_repuestos'] ?? 0),
            2
        );
    }

    public function eliminarRepuesto($index)
    {
        unset($this->repuestos[$index]);
        $this->repuestos = array_values($this->repuestos);
        $this->calcularTotalRepuestos();
    }

    /** La ultima nota con el evento del estado actual (Fuera, Roto, Reserva). */
    protected function notaVigente(): ?Bitacora
    {
        if (!$this->producto) {
            return null;
        }

        return $this->producto->bitacoras()
            ->where('evento', $this->producto->estado)
            ->whereNotNull('descripcion')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Que el modal siga teniendo un producto con el que trabajar: los metodos
     * de guardado hacen reset() al terminar, y un reintento tardio llegaria con
     * $producto en null.
     */
    private function productoEnPantalla(): bool
    {
        if ($this->producto) {
            return true;
        }

        toastr()->info('Esta ventana ya se cerró. Vuelve a abrirla para ver cómo quedó el producto.');
        $this->dispatch('refreshProductoTable');

        return false;
    }

    public function update()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        if (in_array($this->estado, ProductoEstado::soloPorDocumento(), true)) {
            throw ValidationException::withMessages(['estado' => 'Ese estado lo pone una venta o una reserva. Usa «Vender» o «Reservar».']);
        }

        // Sacarlo de Reserva a mano dejaria la reserva activa con su seña.
        if ($this->estadoOrigen === ProductoEstado::Reserva->value) {
            throw ValidationException::withMessages(['estado' => 'El equipo está reservado: concreta la venta o cancela la reserva.']);
        }

        // Y de Reclamo, el reclamo abierto sin cerrar.
        if ($this->estadoOrigen === ProductoEstado::Reclamo->value) {
            throw ValidationException::withMessages(['estado' => 'El equipo está en reclamo al proveedor: cierra el reclamo desde la compra.']);
        }

        $stock = app(StockService::class);
        $estados = app(EstadoProductoService::class);

        // Un cambio de estado de verdad, o solo una correccion de la nota del
        // estado en que ya esta.
        $esTransicion = $this->estadoOrigen !== $this->estado;

        DB::transaction(function () use ($stock, $estados, $esTransicion) {
            // Las ramas preparan la nota; quien la escribe es EstadoProductoService,
            // de una sola pieza con el estado, para que no digan cosas distintas.
            $descripcion = null;
            $enlaces = [];

            if (in_array($this->estado, self::CON_NOTA, true)) {
                $this->validate(['nota' => 'required|string|max:255'], ['nota.required' => 'Escribe la nota: a quién se le dio, qué le pasa o quién lo reserva.']);

                // Corregir la nota es un hecho mas (la bitacora es inmutable). Se
                // compara contra la nota de la BASE: guardar sin tocarla no debe
                // dejar una fila repetida.
                if ($esTransicion || $this->nota !== $this->notaVigente()?->descripcion) {
                    $descripcion = $this->nota;
                }
            }

            if ($this->estado == ProductoEstado::Reparacion->value) {
                $this->guardarReparacion($stock, $descripcion, $enlaces);
            }

            if ($this->estado == ProductoEstado::Inventario->value) {
                $descripcion = 'Producto movido a inventario';
            }

            if ($esTransicion) {
                // Aqui SI se exige el permiso: el usuario ELIGIO el estado de un
                // select ya filtrado por permisos.
                $estados->cambiar(
                    $this->producto->id,
                    ProductoEstado::from($this->estadoOrigen),
                    ProductoEstado::from($this->estado),
                    $descripcion ?? 'Cambio de estado',
                    $enlaces,
                    exigirPermiso: true,
                );
            } elseif ($descripcion !== null) {
                // No se mueve de estado pero queda nota.
                Bitacora::registrar($this->producto, $this->estado, $descripcion, $enlaces);
            }
        });

        $this->dispatch('refreshProductoTable');
        toastr()->success('Estado actualizado exitosamente');
        $this->reset();
    }

    private function guardarReparacion(StockService $stock, ?string &$descripcion, array &$enlaces): void
    {
        $this->calcularTotalReparacion();
        $this->validate([
            'reparacion.costo' => 'required|numeric|min:0',
            'reparacion.costo_repuestos' => 'required|numeric|min:0',
            'reparacion.tecnico_id' => 'required',
            'reparacion.fecha_entrega' => 'required',
        ], [
            'reparacion.tecnico_id.required' => 'Elige el técnico.',
        ]);

        $datos = [
            'tecnico_id' => $this->reparacion['tecnico_id'],
            'costo' => $this->reparacion['costo'],
            'costo_repuestos' => $this->reparacion['costo_repuestos'],
            'costo_total' => $this->reparacion['costo_total'],
            'repuestos_tecnico' => $this->reparacion['repuestos_tecnico'] ?? null,
            'repuestos_propios' => $this->reparacion['repuestos_propios'] ?? null,
            'repuestos_devolver' => $this->reparacion['repuestos_devolver'] ?? null,
            'fecha_entrega' => $this->reparacion['fecha_entrega'],
            'garantia_tecnico' => (bool) ($this->reparacion['garantia_tecnico'] ?? false),
        ];

        // El id es del payload: la reparacion tiene que ser de este producto.
        $productoReparacion = isset($this->reparacion['id'])
            ? ProductoReparacion::whereKey($this->reparacion['id'])->where('producto_id', $this->producto->id)->first()
            : null;

        if ($productoReparacion) {
            $productoReparacion->update($datos);
        } else {
            $tecnico = Tecnicos::findOrFail($this->reparacion['tecnico_id']);
            $productoReparacion = ProductoReparacion::create($datos + ['producto_id' => $this->producto->id]);

            // Ordenado por repuesto_id para no bloquear en InnoDB.
            foreach (collect($this->repuestos)->sortBy('repuesto_id') as $linea) {
                $sucursalId = $this->sucursalDeLinea($linea);

                ProductoReparacionRepuesto::create([
                    'producto_reparacion_id' => $productoReparacion->id,
                    'repuesto_id' => $linea['repuesto_id'],
                    // Congelada: de aqui salio la pieza, y es donde volvera.
                    'sucursal_id' => $sucursalId,
                    'costo' => $linea['costo'],
                    'cantidad' => $linea['cantidad'],
                    'subtotal_costo' => $linea['subtotal_costo'],
                ]);

                $stock->retirar(ArticuloTipo::Repuesto, (int) $linea['repuesto_id'], $sucursalId, (int) $linea['cantidad']);
            }

            $stock->recalcularTotales();

            $descripcion = "Producto en reparacion con el tecnico {$tecnico->nombre}";
            $enlaces = ['producto_reparacion_id' => $productoReparacion->id];
        }

        $this->producto->refresh();
        $this->producto->recalcularCosto();
    }

    public function finalizarReparacion()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        $estados = app(EstadoProductoService::class);

        DB::transaction(function () use ($estados) {
            // La reparacion tiene que ser de ESTE producto.
            $productoReparacion = ProductoReparacion::whereKey($this->reparacion['id'] ?? null)
                ->where('producto_id', $this->producto->id)
                ->first();

            if (!$productoReparacion) {
                throw ValidationException::withMessages(['detalles' => 'Esa reparación no es de este producto. Recarga la pantalla.']);
            }

            $productoReparacion->update(['estado' => 'Terminado', 'fecha_recogida' => now()->format('Y-m-d')]);

            // Precondicion: si otro ya lo saco de Reparacion, esto no se repite.
            $estados->cambiar(
                $this->producto->id,
                ProductoEstado::Reparacion,
                ProductoEstado::Inventario,
                "Reparacion finalizada con el tecnico {$productoReparacion->tecnico?->nombre}",
                ['producto_reparacion_id' => $productoReparacion->id],
            );
        });

        $this->dispatch('refreshProductoTable');
        toastr()->success('Reparación finalizada exitosamente');
        $this->reset();
    }

    /** El equipo que salio del local vuelve: Fuera -> Inventario. */
    public function finalizarFuera()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        DB::transaction(fn() => app(EstadoProductoService::class)->cambiar(
            $this->producto->id,
            ProductoEstado::Fuera,
            ProductoEstado::Inventario,
            'Producto devuelto al local',
        ));

        $this->dispatch('refreshProductoTable');
        toastr()->success('Producto devuelto al inventario');
        $this->reset();
    }

    /** Anula la linea de venta del equipo: vuelve a Inventario (AnulacionVentaService). */
    public function cancelarVenta()
    {
        if (!$this->productoEnPantalla()) {
            return;
        }

        abort_unless(Auth::user()?->can('venta.detalle.delete'), 403);

        DB::transaction(function () {
            // La linea se deduce del PRODUCTO y no del payload: un id ajeno
            // habria anulado la venta de otro cliente.
            $linea = $this->producto->ventaDetalle()->first();

            if (!$linea) {
                throw ValidationException::withMessages(['detalles' => 'Este producto no tiene una venta que anular. Recarga la pantalla.']);
            }

            app(AnulacionVentaService::class)->anularLinea($linea);
        });

        $this->dispatch('refreshProductoTable');
        toastr()->success('Venta del equipo anulada: vuelve al inventario.');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
