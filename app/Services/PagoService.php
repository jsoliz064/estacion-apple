<?php

namespace App\Services;

use App\Enums\BitacoraEvento;
use App\Enums\Moneda;
use App\Enums\PagoMomento;
use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\Concerns\FilasDePago;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de ventas_pagos, de ventas.pagado y de ventas.pagada_at.
 *
 * Una venta con saldo esta A CREDITO: tiene que tener cliente con ficha y sus
 * equipos estan en Credito. Cuando el saldo llega a cero queda PAGADA:
 * pagada_at guarda cuando (la comision del vendedor se gana ahi, etapa 7) y los
 * equipos pasan a Vendido. sincronizar() es el unico sitio que decide eso, y lo
 * llaman todos los flujos que mueven el total o lo cobrado: registrar y anular
 * un pago, editar la venta y anular una linea.
 *
 * Tres clases de pago, todas con `monto` en Bs:
 *  - el que se cobra a mano (registrar): en Bs o en USD con su tipo de cambio;
 *  - la PERMUTA (registrarPermuta): el equipo recibido es el pago, con el
 *    metodo de sistema «Permuta»;
 *  - la SEÑA de una reserva (registrarSena), que entra al concretarla.
 * Las dos ultimas no se anulan sueltas: se deshacen anulando la venta.
 *
 * Se llama "pago" y no "cobro" porque "cobro" ya es, en el codigo, el cobro de
 * las piezas de una reparacion (RepuestosDeReparacionService).
 *
 * ORDEN DE BLOQUEO: la reserva (si la hay), la venta, sus equipos (por id) y el
 * stock. El mismo en todos los flujos.
 *
 * Ningun metodo abre transaccion: la abre el componente.
 */
class PagoService
{
    use FilasDePago;

    public function __construct(private EstadoProductoService $estados) {}

    /**
     * Registra uno o varios pagos cobrados a mano.
     *
     * @param  array  $pagos  [['metodo_pago_id', 'monto' (Bs), 'moneda' (BOB|USD),
     *                         'monto_moneda' (USD), 'tipo_cambio', 'nota'], ...]
     *                        En USD, el monto en Bs se calcula aqui (USD x TC).
     *
     * @throws ValidationException si un metodo no esta activo, un monto no es
     *         positivo o la suma supera el saldo (leido despues del bloqueo).
     */
    public function registrar(Venta $venta, array $pagos, PagoMomento $momento, User $user, ?string $clave = null): Venta
    {
        $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
        $pagos = $this->normalizar($pagos);

        if ($pagos === []) {
            return $this->sincronizar($venta);
        }

        $suma = round(array_sum(array_column($pagos, 'monto')), 2);
        $this->exigirSaldo($venta, $suma);

        $metodos = MetodoPago::whereKey(array_column($pagos, 'metodo_pago_id'))->get()->keyBy('id');
        $frases = [];

        foreach ($pagos as $pago) {
            $metodo = $metodos->get($pago['metodo_pago_id']);

            // Los de sistema (Permuta) no se cobran a mano.
            if (!$metodo || !$metodo->activo || $metodo->sistema) {
                throw ValidationException::withMessages(['pagos' => 'Uno de los métodos de pago no existe o está desactivado.']);
            }

            $this->insertar($venta, $metodo->id, $pago, $momento, $user, $clave);
            $frases[] = $this->frase($pago, $metodo->nombre);
        }

        $restante = round($venta->saldoPendiente() - $suma, 2);
        $venta->anotar(
            BitacoraEvento::Pago->value,
            ($momento === PagoMomento::Venta ? 'Cobrado al vender: ' : 'Cobro: ') . implode(' + ', $frases)
                . '. ' . ($restante > 0 ? 'Saldo Bs ' . number_format($restante, 2) . '.' : 'Venta pagada.'),
            ['venta_id' => $venta->id],
        );

        return $this->sincronizar($venta);
    }

    /** El equipo recibido en permuta como pago de la venta (PermutaService lo crea antes). */
    public function registrarPermuta(Venta $venta, Producto $recibido, float $valor, User $user, ?string $clave = null): Venta
    {
        $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
        $valor = round($valor, 2);
        $this->exigirSaldo($venta, $valor);

        $metodoId = MetodoPago::permutaId() ?? throw new \LogicException('Falta el método de sistema «Permuta» (MetodoPagoSeeder).');

        $this->insertar($venta, $metodoId, [
            'monto' => $valor, 'moneda' => Moneda::BOB->value, 'monto_moneda' => null, 'tipo_cambio' => null,
            'nota' => null, 'producto_id' => $recibido->id,
        ], PagoMomento::Venta, $user, $clave);

        $venta->anotar(
            BitacoraEvento::Pago->value,
            'Permuta: recibido el equipo IMEI ' . $recibido->imei . ' por Bs ' . number_format($valor, 2) . '.',
            ['venta_id' => $venta->id],
        );

        return $this->sincronizar($venta);
    }

    /** La seña de una reserva que se concreta en esta venta (ReservaService::concretar). */
    public function registrarSena(Venta $venta, Reserva $reserva, User $user): Venta
    {
        $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
        $this->exigirSaldo($venta, (float) $reserva->sena);

        // El metodo de la seña aunque hoy este desactivado: el dinero entro
        // cuando entro. La fecha es la de la reserva.
        $this->insertar($venta, $reserva->metodo_pago_id, [
            'monto' => round((float) $reserva->sena, 2), 'moneda' => Moneda::BOB->value, 'monto_moneda' => null,
            'tipo_cambio' => null, 'nota' => 'Seña de la reserva #' . $reserva->id, 'producto_id' => null,
            'fecha' => $reserva->created_at,
        ], PagoMomento::Sena, $user, null);

        $venta->anotar(
            BitacoraEvento::Pago->value,
            'Seña de la reserva #' . $reserva->id . ': Bs ' . number_format((float) $reserva->sena, 2) . '.',
            ['venta_id' => $venta->id],
        );

        return $this->sincronizar($venta);
    }

    /** Anula un pago cobrado a mano: la venta vuelve a tener ese saldo. */
    public function anular(VentaPago $pago, User $user): Venta
    {
        $venta = Venta::whereKey($pago->venta_id)->lockForUpdate()->firstOrFail();
        $pago = VentaPago::with('metodo')->whereKey($pago->id)->where('venta_id', $venta->id)->first();

        if (!$pago) {
            throw ValidationException::withMessages(['pagos' => 'Ese pago ya no existe: alguien lo anuló. Recarga la pantalla.']);
        }

        // La permuta y la seña son parte del trato: deshacerlas sueltas dejaria
        // un equipo recibido sin pago o una reserva concretada sin seña.
        if ($pago->esPermuta() || $pago->esSena()) {
            throw ValidationException::withMessages([
                'pagos' => ($pago->esPermuta() ? 'La permuta' : 'La seña') . ' no se anula sola: para deshacerla, anula la venta.',
            ]);
        }

        $venta->anotar(
            BitacoraEvento::PagoAnulado->value,
            'Pago anulado por ' . $user->name . ': ' . $this->frase([
                'monto' => (float) $pago->monto, 'moneda' => $pago->moneda->value,
                'monto_moneda' => $pago->monto_moneda, 'tipo_cambio' => $pago->tipo_cambio,
            ], $pago->metodo?->nombre ?? 'método') . ' del ' . $pago->fecha->format('d/m/Y H:i') . '.',
            ['venta_id' => $venta->id],
        );
        $pago->delete();

        return $this->sincronizar($venta);
    }

    /**
     * Borra todos los pagos de una venta que se va a anular entera. Uno por uno
     * en la bitacora: se entiende que el dinero se devolvio. La permuta devuelve
     * el equipo recibido (o impide anular si ya se vendio) y la reserva
     * concretada queda cancelada con la seña devuelta.
     */
    public function anularTodos(Venta $venta, string $motivo, ?User $user = null): void
    {
        $user ??= auth()->user();

        foreach ($venta->pagos()->with('metodo')->orderBy('id')->get() as $pago) {
            // registrar() y no anotar(): la venta se borra a continuacion y no
            // habra un save() que lleve la nota.
            Bitacora::registrar(
                $venta,
                BitacoraEvento::PagoAnulado->value,
                'Pago de Bs ' . number_format((float) $pago->monto, 2) . ' en ' . ($pago->metodo?->nombre ?? 'método') . " anulado: {$motivo}",
                ['venta_id' => $venta->id],
            );

            $productoRecibido = $pago->producto_id;
            $pago->delete();

            if ($productoRecibido) {
                app(PermutaService::class)->devolver($productoRecibido, $venta);
            }
        }

        // Lazy: ReservaService usa este servicio para la seña.
        app(ReservaService::class)->deshacerConcrecion($venta, $user);
    }

    /**
     * Recalcula lo cobrado desde la base y deja la venta y sus equipos en el
     * estado que les toca. Es idempotente: si nada cambio, no escribe nada.
     */
    public function sincronizar(Venta $venta): Venta
    {
        $pagado = round((float) $venta->pagos()->sum('monto'), 2);
        $total = round((float) $venta->total, 2);

        if ($pagado > $total) {
            throw ValidationException::withMessages([
                'pagos' => "La venta #{$venta->id} quedaría con Bs " . number_format($pagado, 2)
                    . ' cobrados sobre un total de Bs ' . number_format($total, 2) . '. Anula un pago antes.',
            ]);
        }

        $aCredito = $pagado < $total;

        if ($aCredito && !$venta->cliente_id) {
            throw ValidationException::withMessages([
                'cliente' => 'Quedan Bs ' . number_format($total - $pagado, 2)
                    . ' por cobrar: una venta a crédito necesita un cliente con ficha.',
            ]);
        }

        $venta->pagado = $pagado;
        $venta->pagada_at = $aCredito ? null : ($venta->pagada_at ?? now());
        $venta->save();

        $this->moverEquipos($venta, $aCredito ? ProductoEstado::Credito : ProductoEstado::Vendido);

        // La comision del vendedor se gana con pagada_at (docs/05).
        app(ComisionService::class)->sincronizarVenta($venta);

        return $venta;
    }

    /** El ultimo tipo de cambio usado, para proponerlo (6,96 si nunca se cobro en USD). */
    public static function ultimoTipoCambio(): float
    {
        return (float) (VentaPago::where('moneda', Moneda::USD->value)->latest('id')->value('tipo_cambio') ?? 6.96);
    }

    // ------------------------------------------------------------------ apoyo

    private function exigirSaldo(Venta $venta, float $suma): void
    {
        $saldo = $venta->saldoPendiente();

        if (round($suma, 2) > $saldo) {
            throw ValidationException::withMessages([
                'pagos' => 'El cobro de Bs ' . number_format($suma, 2) . ' supera el saldo de la venta #' . $venta->id
                    . ' (Bs ' . number_format($saldo, 2) . ').',
            ]);
        }
    }

    private function insertar(Venta $venta, int $metodoId, array $pago, PagoMomento $momento, User $user, ?string $clave): void
    {
        VentaPago::create([
            'venta_id' => $venta->id,
            'metodo_pago_id' => $metodoId,
            'monto' => $pago['monto'],
            'moneda' => $pago['moneda'] ?? Moneda::BOB->value,
            'monto_moneda' => $pago['monto_moneda'] ?? null,
            'tipo_cambio' => $pago['tipo_cambio'] ?? null,
            'producto_id' => $pago['producto_id'] ?? null,
            'momento' => $momento->value,
            'fecha' => $pago['fecha'] ?? now(),
            'nota' => $pago['nota'] ?? null,
            'user_id' => $user->id,
            'clave_idempotencia' => $clave,
        ]);
    }

    /** Los equipos de la venta a Credito o a Vendido, solo los que no lo esten ya. */
    private function moverEquipos(Venta $venta, ProductoEstado $destino): void
    {
        $origen = $destino === ProductoEstado::Credito ? ProductoEstado::Vendido : ProductoEstado::Credito;

        $ids = $venta->detalles()->whereNotNull('producto_id')
            ->whereHas('producto', fn($q) => $q->where('estado', $origen->value))
            ->orderBy('producto_id')
            ->pluck('producto_id');

        foreach ($ids as $productoId) {
            $this->estados->cambiar(
                $productoId,
                $origen,
                $destino,
                $destino === ProductoEstado::Vendido
                    ? "Venta #{$venta->id} pagada por completo."
                    : "Venta #{$venta->id} a crédito: saldo Bs " . number_format($venta->saldoPendiente(), 2) . '.',
                ['venta_id' => $venta->id],
            );
        }
    }
}
