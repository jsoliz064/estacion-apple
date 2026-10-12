<?php

namespace App\Services;

use App\Enums\ArticuloTipo;
use App\Enums\LineaTipo;
use App\Enums\PagoMomento;
use App\Enums\ProductoEstado;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaDetalle;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de ventas_detalles (los cobros de taller los agrega
 * RepuestosDeReparacionService, que llama este servicio).
 *
 * Una venta lleva equipos (cantidad 1, con garantia), repuestos y accesorios
 * (con cantidad). Las reglas que no pueden perderse:
 *
 *  - El COSTO se relee de la base, nunca del formulario: del equipo bloqueado
 *    (costo_total, que ya incluye regalos y reparaciones) o del articulo. Antes
 *    VentaCreate tomaba el costo del payload de Livewire, que el cliente puede
 *    alterar.
 *  - Orden de bloqueo identico en todos los flujos: equipos por id, despues
 *    stock por (tipo, id). Dos ventas simultaneas con las mismas lineas en otro
 *    orden se bloquearian mutuamente en InnoDB.
 *  - El stock sale de la sucursal de la venta y se congela en la linea:
 *    anularla lo devuelve ahi.
 *  - Ningun metodo abre transaccion: la abre el componente, que tambien
 *    resuelve la idempotencia (GuardadoIdempotenteTrait).
 *  - Los REGALOS del equipo (productos_regalos) entran solos con el, como
 *    lineas de accesorio a precio 0 y costo 0 (registrarRegalos): su costo ya
 *    esta en el costo_total del equipo y su stock salio al regalarlos. Se
 *    releen de la base, no llegan del formulario, y se borran con su equipo.
 *  - Lo cobrado lo escribe PagoService. Si queda saldo, la venta esta a
 *    credito (exige cliente) y sus equipos van a Credito en vez de Vendido; se
 *    decide ANTES de venderlos, para que el historial no anote dos cambios.
 *
 * Formato de una linea que llega de la pantalla:
 *   ['tipo' => 'Producto'|'Repuesto'|'Accesorio', 'id' => int, 'cantidad' => int,
 *    'precio' => float, 'descuento' => float, 'garantia_meses' => ?int]
 */
class VentaService
{
    public function __construct(
        private EstadoProductoService $estados,
        private StockService $stock,
        private RepuestosDeReparacionService $cobros,
        private PagoService $pagos,
        private ReservaService $reservas,
        private PermutaService $permutas,
    ) {}

    /**
     * Registra una venta completa.
     *
     * @param  array  $cabecera  sucursal_id, cliente_id, cliente (texto), descuento, mano_obra, reserva_id
     * @param  array  $lineas    ver el docblock de la clase
     * @param  array  $cobros    cobros de taller (formato de RepuestosDeReparacionService)
     * @param  array  $pagos     lo cobrado al vender (formato de PagoService::registrar)
     * @param  array|null  $permuta  el equipo recibido (formato de PermutaService::recibir)
     */
    public function registrar(array $cabecera, array $lineas, array $cobros, array $pagos, User $user, ?string $clave = null, ?array $permuta = null): Venta
    {
        $lineas = $this->normalizar($lineas);
        $sucursalId = (int) $cabecera['sucursal_id'];

        // La reserva primero: es la primera en el orden de bloqueo. Su equipo
        // tiene que estar en la venta y su cliente es el de la venta.
        $reserva = null;
        if (!empty($cabecera['reserva_id'])) {
            $reserva = $this->reservas->bloquearActiva((int) $cabecera['reserva_id']);

            if (!$lineas->contains(fn($l) => $l['tipo'] === LineaTipo::Producto->value && $l['id'] === (int) $reserva->producto_id)) {
                throw ValidationException::withMessages(['detalles' => 'El equipo reservado tiene que estar en la venta.']);
            }

            $cabecera['cliente_id'] = $reserva->cliente_id;
            $cabecera['cliente'] = $reserva->cliente?->nombre;
        }

        // Se calculan los totales ANTES de crear la cabecera: asi la bitacora de
        // la venta registra una fila con los totales reales, y no un 'creado'
        // en cero seguido de un 'editado'. recalcularTotales() al final lo
        // confirma desde la base (si coincide, el Observer no escribe nada).
        [$equipos, $articulos] = $this->leerCostos($lineas);

        $venta = Venta::create([
            'clave_idempotencia' => $clave,
            'sucursal_id' => $sucursalId,
            'cliente_id' => $cabecera['cliente_id'] ?? null,
            'cliente' => $cabecera['cliente'] ?? null,
            'user_id' => $user->id,
            'descuento' => round((float) ($cabecera['descuento'] ?? 0), 2),
            'mano_obra' => round((float) ($cabecera['mano_obra'] ?? 0), 2),
        ] + $this->totalesPrevios($lineas, $equipos, $articulos, $cabecera));

        // A credito si lo cobrado no cubre el total (con los cobros de taller):
        // los pagos de la pantalla, la seña de la reserva y la permuta.
        $totalPrevisto = (float) $venta->total + $this->cobros->totalPrevisto($cobros);
        $cobrado = round(array_sum(array_map(fn($p) => $this->montoPrevisto($p), $pagos))
            + (float) ($reserva?->sena ?? 0) + (float) ($permuta['valor'] ?? 0), 2);
        $destino = $cobrado < round($totalPrevisto, 2) ? ProductoEstado::Credito : ProductoEstado::Vendido;

        foreach ($lineas->where('tipo', LineaTipo::Producto->value)->sortBy('id') as $linea) {
            $esReservado = $reserva && $linea['id'] === (int) $reserva->producto_id;
            $this->venderEquipo($venta, $linea, $destino, $esReservado ? ProductoEstado::Reserva : null);
        }

        foreach ($lineas->where('tipo', '!=', LineaTipo::Producto->value)->sortBy(fn($l) => $l['tipo'] . ':' . str_pad($l['id'], 12, '0', STR_PAD_LEFT)) as $linea) {
            $this->venderArticulo($venta, $linea, $articulos[$linea['tipo'] . ':' . $linea['id']], $this->asociadoValido($linea, $lineas));
        }

        $this->cobros->registrar($venta, $cobros);

        $this->stock->recalcularTotales();
        $venta->recalcularTotales();

        // Despues de los totales: el saldo se mide contra el total real. La
        // seña y la permuta primero (no dependen del vendedor), lo cobrado a
        // mano al final.
        if ($reserva) {
            $this->reservas->concretar($reserva, $venta, $user);
        }

        if ($permuta) {
            $this->permutas->recibir($venta, $permuta, $user, $clave);
        }

        return $this->pagos->registrar($venta, $pagos, PagoMomento::Venta, $user, $clave);
    }

    /** El monto en Bs de una fila de pago, antes de que PagoService la valide. */
    private function montoPrevisto(array $pago): float
    {
        if (($pago['moneda'] ?? 'BOB') === 'USD') {
            return round((float) ($pago['monto_moneda'] ?? 0) * (float) ($pago['tipo_cambio'] ?? 0), 2);
        }

        return (float) ($pago['monto'] ?? 0);
    }

    /**
     * Edita una venta: cabecera (cliente, descuento, mano de obra) y lineas.
     * La sucursal NO cambia (el stock ya salio de ella) y `user_id` tampoco: el
     * vendedor es quien vendio, no quien corrige.
     *
     * Compara por articulo: lo que ya no esta se anula (devuelve stock o el
     * equipo a Inventario), lo que cambio de cantidad se ajusta con
     * ajustarSalida(), y lo nuevo se vende. Las cantidades "antes" se releen de
     * la base, no del formulario.
     */
    public function actualizar(Venta $venta, array $cabecera, array $lineas, array $cobros): Venta
    {
        $lineas = $this->normalizar($lineas)->keyBy(fn($l) => $l['tipo'] . ':' . $l['id']);
        // Ni los cobros ni los regalos entran en el diff: no llegan del formulario.
        $actuales = $venta->detalles()->whereNull('producto_reparacion_repuesto_id')->whereNull('producto_regalo_id')->get()
            ->keyBy(fn($d) => $d->tipo . ':' . $d->{LineaTipo::from($d->tipo)->columna()});

        // 1. Lo que ya no esta: anular, equipos primero (descobrando antes).
        foreach ($actuales->diffKeys($lineas)->sortBy(fn($d) => $d->producto_id ? 0 : 1) as $detalle) {
            if ($detalle->producto_id) {
                $this->cobros->cancelarCobros($venta, $detalle->producto_id);
                VentaDetalle::borrarRegalosDe($venta->id, $detalle->producto_id);
                $detalle->delete();
                $this->desasociar($venta, $detalle->producto_id);
                $this->estados->cambiar(
                    $detalle->producto_id,
                    array_map(fn($v) => ProductoEstado::from($v), ProductoEstado::vendidos()),
                    ProductoEstado::Inventario,
                    "Producto devuelto al inventario: se quito de la venta #{$venta->id}.",
                    ['venta_id' => $venta->id],
                );
            } else {
                $tipo = $detalle->tipoLinea()->articulo();
                $this->stock->ingresar($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad);
                $detalle->delete();
            }
        }

        // 2. Lo que sigue: precio, descuento, garantia y (articulos) cantidad.
        foreach ($actuales->intersectByKeys($lineas) as $clave => $detalle) {
            $linea = $lineas[$clave];

            if ($detalle->producto_id) {
                $detalle->update($this->importesEquipo($linea, (float) $detalle->costo) + $this->garantia($linea));
                continue;
            }

            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->ajustarSalida($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad, $linea['cantidad']);
            $detalle->update($this->importesArticulo($linea, (float) $detalle->costo)
                + ['producto_asociado_id' => $this->asociadoValido($linea, $lineas)]);
        }

        // 3. Lo nuevo.
        $nuevas = $lineas->diffKeys($actuales);
        [, $articulos] = $this->leerCostos($nuevas);

        // Los equipos nuevos entran como el resto de la venta; si el nuevo total
        // cambia la cuenta, sincronizar() los mueve al final.
        foreach ($nuevas->where('tipo', LineaTipo::Producto->value)->sortBy('id') as $linea) {
            $this->venderEquipo($venta, $linea, $venta->estaPagada() ? ProductoEstado::Vendido : ProductoEstado::Credito);
        }

        foreach ($nuevas->where('tipo', '!=', LineaTipo::Producto->value) as $clave => $linea) {
            $this->venderArticulo($venta, $linea, $articulos[$clave], $this->asociadoValido($linea, $lineas));
        }

        $this->cobros->registrar($venta, $cobros);

        $venta->fill([
            'cliente_id' => $cabecera['cliente_id'] ?? null,
            'cliente' => $cabecera['cliente'] ?? $venta->cliente,
            'descuento' => round((float) ($cabecera['descuento'] ?? 0), 2),
            'mano_obra' => round((float) ($cabecera['mano_obra'] ?? 0), 2),
        ]);

        if ($venta->detalles()->doesntExist()) {
            throw ValidationException::withMessages([
                'detalles' => 'La venta tiene que tener al menos una línea. Para quitarlo todo, anula la venta.',
            ]);
        }

        $this->stock->recalcularTotales();
        $venta->recalcularTotales();

        // Subir el total de una venta pagada la vuelve a credito; bajarlo puede
        // saldarla. recalcularTotales() ya rechazo bajarlo por debajo de lo cobrado.
        return $this->pagos->sincronizar($venta);
    }

    // ------------------------------------------------------------------ lineas

    private function venderEquipo(Venta $venta, array $linea, ProductoEstado $destino, ?ProductoEstado $esperado = null): void
    {
        // vender() bloquea el producto, exige que este disponible (y no dado de
        // baja) y anota en su bitacora desde el mismo $destino que el update.
        $producto = $this->estados->vender(
            $linea['id'],
            ($destino === ProductoEstado::Credito ? 'Vendido a crédito' : 'Vendido') . ". Venta #{$venta->id}"
                . ($venta->nombreCliente() ? ', ' . $venta->nombreCliente() : '')
                . ', Bs ' . number_format(max(0, $linea['precio'] - $linea['descuento']), 2),
            ['venta_id' => $venta->id, 'sucursal_id' => $venta->sucursal_id],
            $destino,
            $esperado,
        );

        VentaDetalle::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto->id,
            'sucursal_id' => $venta->sucursal_id,
            'cantidad' => 1,
            'tipo_venta' => $producto->tipo_venta,
        ] + $this->importesEquipo($linea, (float) $producto->costo_total) + $this->garantia($linea));

        $this->registrarRegalos($venta, $producto);
    }

    /**
     * Una linea por cada accesorio regalado con el equipo, enlazada a el.
     *
     * Precio 0 y costo 0: el costo ya viaja en el costo_total del equipo, que
     * su linea acaba de congelar, y contarlo otra vez duplicaria el costo de
     * la venta. Sin retirar(): el stock salio cuando se regalo. Se releen de
     * la base con el equipo ya bloqueado por vender(), y ProductoRegalosService
     * bloquea el mismo equipo para cambiarlos: no pueden moverse en medio.
     */
    private function registrarRegalos(Venta $venta, Producto $producto): void
    {
        foreach ($producto->regalos()->orderBy('id')->get() as $regalo) {
            VentaDetalle::create([
                'venta_id' => $venta->id,
                'accesorio_id' => $regalo->accesorio_id,
                'producto_regalo_id' => $regalo->id,
                'producto_asociado_id' => $producto->id,
                // La sucursal de donde salio el regalo, solo como dato.
                'sucursal_id' => $regalo->sucursal_id,
                'cantidad' => (int) $regalo->cantidad,
                'costo' => 0,
                'precio' => 0,
                'descuento' => 0,
                'subtotal' => 0,
                'subtotal_costo' => 0,
            ]);
        }
    }

    private function venderArticulo(Venta $venta, array $linea, float $costo, ?int $asociado = null): void
    {
        $tipo = LineaTipo::from($linea['tipo'])->articulo();

        // El WHERE cantidad >= ? de retirar() es la validacion de stock.
        $this->stock->retirar($tipo, $linea['id'], $venta->sucursal_id, $linea['cantidad']);

        VentaDetalle::create([
            'venta_id' => $venta->id,
            $tipo->columna() => $linea['id'],
            'sucursal_id' => $venta->sucursal_id,
            'producto_asociado_id' => $asociado,
        ] + $this->importesArticulo($linea, $costo));
    }

    /**
     * El equipo con el que se vende un accesorio, solo si es un equipo de ESTA
     * venta: el id llega del navegador.
     */
    private function asociadoValido(array $linea, Collection $lineas): ?int
    {
        $id = (int) ($linea['con_producto_id'] ?? 0);

        return $id > 0 && $lineas->contains(fn($l) => $l['tipo'] === LineaTipo::Producto->value && $l['id'] === $id) ? $id : null;
    }

    /** Un equipo que sale de la venta deja de agrupar a sus accesorios. */
    private function desasociar(Venta $venta, int $productoId): void
    {
        VentaDetalle::where('venta_id', $venta->id)->where('producto_asociado_id', $productoId)
            ->whereNull('producto_regalo_id')
            ->update(['producto_asociado_id' => null]);
    }

    private function importesEquipo(array $linea, float $costo): array
    {
        $precio = round($linea['precio'], 2);
        $descuento = round(min($linea['descuento'], $precio), 2);

        return [
            'cantidad' => 1,
            'costo' => $costo,
            'precio' => $precio,
            'descuento' => $descuento,
            'subtotal' => round($precio - $descuento, 2),
            'subtotal_costo' => $costo,
        ];
    }

    private function importesArticulo(array $linea, float $costo): array
    {
        $cantidad = $linea['cantidad'];
        $precio = round($linea['precio'], 2);
        $bruto = round($precio * $cantidad, 2);
        $descuento = round(min($linea['descuento'], $bruto), 2);

        return [
            'cantidad' => $cantidad,
            'costo' => $costo,
            'precio' => $precio,
            'descuento' => $descuento,
            'subtotal' => round($bruto - $descuento, 2),
            'subtotal_costo' => round($costo * $cantidad, 2),
        ];
    }

    private function garantia(array $linea): array
    {
        $meses = $linea['garantia_meses'] ?? null;

        return [
            'garantia_meses' => $meses ?: null,
            'garantia_fecha_exp' => $meses ? now()->addMonths((int) $meses)->toDateString() : null,
        ];
    }

    // ------------------------------------------------------------------ apoyo

    /**
     * Valida y normaliza las lineas que llegan de la pantalla. Un equipo
     * siempre tiene cantidad 1 (tambien lo exige un CHECK en la base).
     */
    private function normalizar(array $lineas): Collection
    {
        $lineas = collect($lineas)->map(function ($l) {
            $tipo = LineaTipo::tryFrom((string) ($l['tipo'] ?? ''));
            $id = (int) ($l['id'] ?? 0);

            if (!$tipo || $id <= 0) {
                throw ValidationException::withMessages(['detalles' => 'Hay una línea de venta inválida. Recarga la pantalla.']);
            }

            $cantidad = $tipo === LineaTipo::Producto ? 1 : (int) ($l['cantidad'] ?? 0);

            if ($cantidad < 1) {
                throw ValidationException::withMessages(['detalles' => 'Cada línea tiene que tener al menos una unidad.']);
            }

            $precio = (float) ($l['precio'] ?? 0);
            $descuento = (float) ($l['descuento'] ?? 0);

            if ($precio < 0 || $descuento < 0) {
                throw ValidationException::withMessages(['detalles' => 'Los precios y descuentos no pueden ser negativos.']);
            }

            return [
                'tipo' => $tipo->value,
                'id' => $id,
                'cantidad' => $cantidad,
                'precio' => $precio,
                'descuento' => $descuento,
                'garantia_meses' => $tipo === LineaTipo::Producto ? ($l['garantia_meses'] ?? null) : null,
                'con_producto_id' => $tipo === LineaTipo::Producto ? null : ((int) ($l['con_producto_id'] ?? 0) ?: null),
            ];
        });

        if ($lineas->isEmpty()) {
            throw ValidationException::withMessages(['detalles' => 'Agrega al menos un producto, repuesto o accesorio a la venta.']);
        }

        // Un mismo articulo dos veces en la venta: se rechaza aqui con mensaje,
        // aunque el UNIQUE vd_venta_articulo_unico tambien lo impediria.
        if ($lineas->unique(fn($l) => $l['tipo'] . ':' . $l['id'])->count() !== $lineas->count()) {
            throw ValidationException::withMessages(['detalles' => 'Hay un producto repetido en la venta: suma la cantidad en una sola línea.']);
        }

        return $lineas->values();
    }

    /**
     * Lee de la base el costo de lo que se va a vender, para los totales previos
     * y para las lineas de articulo. El de los equipos se vuelve a leer, ya
     * bloqueado, en venderEquipo().
     *
     * @return array{0: array<int,float>, 1: array<string,float>}
     */
    private function leerCostos(Collection $lineas): array
    {
        $equipos = Producto::whereKey($lineas->where('tipo', LineaTipo::Producto->value)->pluck('id'))
            ->pluck('costo_total', 'id')->map(fn($c) => (float) $c)->all();

        $articulos = [];
        foreach ([ArticuloTipo::Repuesto, ArticuloTipo::Accesorio] as $tipo) {
            $ids = $lineas->where('tipo', $tipo->value)->pluck('id');

            if ($ids->isEmpty()) {
                continue;
            }

            $costos = ($tipo->modelo())::whereKey($ids)->pluck('costo', 'id');

            foreach ($ids as $id) {
                if (!isset($costos[$id])) {
                    throw ValidationException::withMessages(['detalles' => "Un {$tipo->label()} de la venta ya no existe. Recarga la pantalla."]);
                }
                $articulos[$tipo->value . ':' . $id] = (float) $costos[$id];
            }
        }

        return [$equipos, $articulos];
    }

    private function totalesPrevios(Collection $lineas, array $equipos, array $articulos, array $cabecera): array
    {
        $subtotal = 0.0;
        $costo = 0.0;

        foreach ($lineas as $linea) {
            if ($linea['tipo'] === LineaTipo::Producto->value) {
                $i = $this->importesEquipo($linea, $equipos[$linea['id']] ?? 0);
            } else {
                $i = $this->importesArticulo($linea, $articulos[$linea['tipo'] . ':' . $linea['id']] ?? 0);
            }
            $subtotal += $i['subtotal'];
            $costo += $i['subtotal_costo'];
        }

        $manoObra = round((float) ($cabecera['mano_obra'] ?? 0), 2);

        return [
            'subtotal' => round($subtotal, 2),
            'total' => round($subtotal - (float) ($cabecera['descuento'] ?? 0) + $manoObra, 2),
            'costo_total' => round($costo + $manoObra, 2),
        ];
    }
}
