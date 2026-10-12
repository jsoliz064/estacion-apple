<?php

namespace App\Services;

use App\Enums\ProductoEstado;
use App\Enums\ReservaEstado;
use App\Enums\SenaDestino;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\Reserva;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de `reservas` y el unico que pone o saca un equipo del
 * estado Reserva (ProductoEstado::soloPorDocumento() lo saco del selector).
 *
 * Un cliente aparta un equipo dejando una seña. Decisiones del usuario:
 *  - la reserva NO vence: queda hasta que se concreta o se cancela;
 *  - al cancelar se elige si la seña se devuelve o la retiene el negocio.
 *
 * Al concretarse en una venta, la seña entra como pago (PagoService, momento
 * Sena). Anular esa venta deshace la reserva: queda cancelada con la seña
 * devuelta, porque el dinero se devuelve con la venta.
 *
 * ORDEN DE BLOQUEO: reserva → venta → equipos → stock.
 *
 * No abre transaccion: la abre el componente.
 */
class ReservaService
{
    public function __construct(
        private EstadoProductoService $estados,
        private PagoService $pagos,
    ) {}

    public function crear(int $productoId, int $clienteId, float $sena, int $metodoId, ?string $nota, User $user, ?string $clave = null): Reserva
    {
        $sena = round($sena, 2);
        $cliente = Cliente::find($clienteId);
        $metodo = MetodoPago::activos()->find($metodoId);

        if (!$cliente) {
            throw ValidationException::withMessages(['cliente' => 'Elige el cliente que reserva.']);
        }
        if ($sena <= 0) {
            throw ValidationException::withMessages(['sena' => 'La seña tiene que ser mayor a cero.']);
        }
        if (!$metodo) {
            throw ValidationException::withMessages(['metodo_pago_id' => 'Elige un método de pago activo para la seña.']);
        }

        // Bloquea el equipo y exige que siga en Inventario (y sin baja).
        $this->estados->cambiar(
            $productoId,
            array_map(fn($v) => ProductoEstado::from($v), ProductoEstado::disponibles()),
            ProductoEstado::Reserva,
            "Reservado para {$cliente->nombre}: seña Bs " . number_format($sena, 2) . " en {$metodo->nombre}.",
        );

        return Reserva::create([
            'producto_id' => $productoId,
            'cliente_id' => $cliente->id,
            'sena' => $sena,
            'metodo_pago_id' => $metodo->id,
            'estado' => ReservaEstado::Activa->value,
            'nota' => $this->limpiar($nota),
            'user_id' => $user->id,
            'clave_idempotencia' => $clave,
        ]);
    }

    public function cancelar(Reserva $reserva, SenaDestino $destino, ?string $nota, User $user): Reserva
    {
        $reserva = $this->bloquearActiva($reserva->id);

        $reserva->update([
            'estado' => ReservaEstado::Cancelada->value,
            'sena_destino' => $destino->value,
            'cerrada_por' => $user->id,
            'cerrada_at' => now(),
            'nota' => $this->limpiar(trim(($reserva->nota ? $reserva->nota . ' · ' : '') . ($nota ?? ''))),
        ]);

        $this->estados->cambiar(
            $reserva->producto_id,
            ProductoEstado::Reserva,
            ProductoEstado::Inventario,
            "Reserva #{$reserva->id} cancelada: " . mb_strtolower($destino->label()) . ' (Bs ' . number_format((float) $reserva->sena, 2) . ').',
        );

        return $reserva;
    }

    /**
     * Relee y bloquea la reserva de una venta en curso (VentaService, ANTES de
     * crear la venta: es la primera en el orden de bloqueo).
     */
    public function bloquearActiva(int $reservaId): Reserva
    {
        $reserva = Reserva::with('cliente')->whereKey($reservaId)->lockForUpdate()->first();

        if (!$reserva || !$reserva->estaActiva()) {
            throw ValidationException::withMessages([
                'reserva' => 'La reserva ya no está activa: alguien la concretó o la canceló. Recarga la pantalla.',
            ]);
        }

        return $reserva;
    }

    /** La reserva se concreta en la venta: la seña entra como pago. */
    public function concretar(Reserva $reserva, Venta $venta, User $user): Venta
    {
        $reserva->update([
            'estado' => ReservaEstado::Concretada->value,
            'venta_id' => $venta->id,
            'cerrada_por' => $user->id,
            'cerrada_at' => now(),
        ]);

        return $this->pagos->registrarSena($venta, $reserva, $user);
    }

    /**
     * Se anula la venta en que se concreto: la reserva queda cancelada con la
     * seña devuelta (el dinero vuelve con la venta). El equipo lo devuelve a
     * Inventario la anulacion de su linea.
     */
    public function deshacerConcrecion(Venta $venta, ?User $user): void
    {
        foreach (Reserva::where('venta_id', $venta->id)->lockForUpdate()->get() as $reserva) {
            $reserva->update([
                'estado' => ReservaEstado::Cancelada->value,
                'sena_destino' => SenaDestino::Devuelta->value,
                'venta_id' => null,
                'cerrada_por' => $user?->id,
                'cerrada_at' => now(),
                'nota' => $this->limpiar(trim(($reserva->nota ? $reserva->nota . ' · ' : '') . "Se anuló la venta #{$venta->id}.")),
            ]);
        }
    }

    private function limpiar(?string $nota): ?string
    {
        $nota = trim((string) $nota);

        return $nota === '' ? null : mb_substr($nota, 0, 255);
    }
}
