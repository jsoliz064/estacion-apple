<?php

namespace App\Services;

use App\Enums\BitacoraEvento;
use App\Models\Bitacora;
use App\Models\TipoCambio;
use App\Models\User;
use App\Support\Dinero;
use Illuminate\Validation\ValidationException;

/**
 * El unico escritor de `tipos_cambio`. Solo agrega una fila: el dinero se
 * guarda en USD, asi que un TC nuevo cambia lo que se VE en Bs y nada mas.
 * Los documentos (ventas, compras) ya congelaron el suyo.
 */
class TipoCambioService
{
    public function actualizar(float $valor, User $user): TipoCambio
    {
        $valor = round($valor, 4);

        if ($valor <= 0) {
            throw ValidationException::withMessages(['valor' => 'El tipo de cambio tiene que ser mayor a cero.']);
        }

        $anterior = TipoCambio::actual();
        $tc = TipoCambio::create(['valor' => $valor, 'user_id' => $user->id]);
        TipoCambio::olvidar();

        Bitacora::registrar($user, BitacoraEvento::TipoCambio->value,'Tipo de cambio: ' . Dinero::tc($anterior) . ' → ' . Dinero::tc($valor));

        return $tc;
    }
}
