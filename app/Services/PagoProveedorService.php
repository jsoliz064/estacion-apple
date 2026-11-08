<?php

namespace App\Services;

use App\Enums\BitacoraEvento;
use App\Models\Compra;
use App\Models\CompraPago;
use App\Models\MetodoPago;
use App\Models\User;
use App\Services\Concerns\FilasDePago;
use Illuminate\Validation\ValidationException;
use App\Support\Dinero;

/**
 * El UNICO escritor de compras_pagos, de compras.pagado y de compras.pagada_at:
 * las cuentas por pagar al proveedor (docs/06). Es el espejo de PagoService,
 * del otro lado del mostrador, con las mismas filas de pago (FilasDePago: USD o
 * Bs con su tipo de cambio).
 *
 * A diferencia de la venta, el saldo de una compra no mueve ningun equipo: una
 * compra a credito es solo una deuda con el proveedor.
 *
 * Decision del usuario: una compra con pagos no se elimina hasta anularlos
 * (CompraService::eliminar lo exige).
 *
 * Ningun metodo abre transaccion: la abre el componente.
 */
class PagoProveedorService
{
    use FilasDePago;

    /**
     * @param  array  $pagos  filas de pago (ver FilasDePago::normalizar)
     */
    public function registrar(Compra $compra, array $pagos, bool $alRecibir, User $user, ?string $clave = null): Compra
    {
        $compra = Compra::whereKey($compra->id)->lockForUpdate()->firstOrFail();
        $pagos = $this->normalizar($pagos);

        if ($pagos === []) {
            return $this->sincronizar($compra);
        }

        $suma = round(array_sum(array_column($pagos, 'monto')), 2);
        $saldo = $compra->saldoPendiente();

        if ($suma > $saldo) {
            throw ValidationException::withMessages([
                'pagos' => 'El pago de ' . Dinero::usd($suma) . ' supera el saldo de la compra #' . $compra->id
                    . ' (' . Dinero::usd($saldo) . ').',
            ]);
        }

        $metodos = MetodoPago::whereKey(array_column($pagos, 'metodo_pago_id'))->get()->keyBy('id');
        $frases = [];

        foreach ($pagos as $pago) {
            $metodo = $metodos->get($pago['metodo_pago_id']);

            if (!$metodo || !$metodo->activo || $metodo->sistema) {
                throw ValidationException::withMessages(['pagos' => 'Uno de los métodos de pago no existe o está desactivado.']);
            }

            CompraPago::create([
                'compra_id' => $compra->id,
                'metodo_pago_id' => $metodo->id,
                'monto' => $pago['monto'],
                'moneda' => $pago['moneda'],
                'monto_moneda' => $pago['monto_moneda'],
                'tipo_cambio' => $pago['tipo_cambio'],
                'al_recibir' => $alRecibir,
                'fecha' => now(),
                'nota' => $pago['nota'],
                'user_id' => $user->id,
                'clave_idempotencia' => $clave,
            ]);

            $frases[] = $this->frase($pago, $metodo->nombre);
        }

        $restante = round($saldo - $suma, 2);
        $compra->anotar(
            BitacoraEvento::Pago->value,
            ($alRecibir ? 'Pagado al recibir: ' : 'Pago al proveedor: ') . implode(' + ', $frases)
                . '. ' . ($restante > 0 ? 'Saldo ' . Dinero::usd($restante) . '.' : 'Compra pagada.'),
            ['compra_id' => $compra->id],
        );

        return $this->sincronizar($compra);
    }

    public function anular(CompraPago $pago, User $user): Compra
    {
        $compra = Compra::whereKey($pago->compra_id)->lockForUpdate()->firstOrFail();
        $pago = CompraPago::with('metodo')->whereKey($pago->id)->where('compra_id', $compra->id)->first();

        if (!$pago) {
            throw ValidationException::withMessages(['pagos' => 'Ese pago ya no existe: alguien lo anuló. Recarga la pantalla.']);
        }

        $compra->anotar(
            BitacoraEvento::PagoAnulado->value,
            'Pago al proveedor anulado por ' . $user->name . ': ' . $this->frase([
                'monto' => (float) $pago->monto, 'moneda' => $pago->moneda->value,
                'monto_moneda' => $pago->monto_moneda, 'tipo_cambio' => $pago->tipo_cambio,
            ], $pago->metodo?->nombre ?? 'método') . ' del ' . $pago->fecha->format('d/m/Y H:i') . '.',
            ['compra_id' => $compra->id],
        );
        $pago->delete();

        return $this->sincronizar($compra);
    }

    /** Recalcula lo pagado desde la base. Idempotente. */
    public function sincronizar(Compra $compra): Compra
    {
        $pagado = round((float) $compra->pagos()->sum('monto'), 2);
        $total = round((float) $compra->total, 2);

        if ($pagado > $total) {
            throw ValidationException::withMessages([
                'pagos' => "La compra #{$compra->id} quedaría con " . Dinero::usd($pagado)
                    . ' pagados sobre un total de ' . Dinero::usd($total) . '. Anula un pago antes.',
            ]);
        }

        $compra->pagado = $pagado;
        // Una compra en 0 (todavia sin lineas) no esta "pagada": no hay nada.
        $compra->pagada_at = ($total > 0 && $pagado >= $total) ? ($compra->pagada_at ?? now()) : null;
        $compra->save();

        return $compra;
    }
}
