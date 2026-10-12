<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Que reintentar un guardado no cree un segundo documento.
 *
 * EL FALLO QUE LO MOTIVA
 * Se registra una venta, el servidor hace COMMIT, y la respuesta no llega: se
 * corto la red, o el celular perdio senal a mitad del guardado. El usuario no
 * ve nada y reintenta. Antes de esto pasaba una de dos cosas, las dos malas: o
 * se creaba una SEGUNDA venta del mismo telefono, o el reintento moria con "el
 * producto ya no esta disponible" -- un mensaje que habla del producto y no del
 * guardado, asi que no habia forma de distinguir "no entro" de "ya entro". En
 * los dos casos el usuario concluye que la orden no se guardo.
 *
 * COMO SE USA
 * 1. El componente llama a nuevaClaveIdempotencia() en mount() o al abrir el
 *    modal. Una clave por APERTURA del formulario: dos ventas identicas hechas
 *    a proposito son dos aperturas, luego dos claves, luego dos ventas.
 * 2. El guardado empieza preguntando por yaGuardado() y termina atrapando la
 *    QueryException con esClaveDuplicada():
 *
 *      if ($ya = $this->yaGuardado(Venta::class)) {
 *          $this->avisarYaGuardado($ya, 'venta');
 *          return redirect()->route('ventas');
 *      }
 *
 *      try {
 *          $venta = DB::transaction(fn() => ...);
 *      } catch (QueryException $e) {
 *          if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(Venta::class)) {
 *              $this->avisarYaGuardado($ya, 'venta');
 *              return redirect()->route('ventas');
 *          }
 *          throw $e;
 *      }
 *
 * 3. La columna va en $fillable si el modelo lo declara. VER EL AVISO ABAJO.
 *
 * POR QUE DOS CAPAS
 * El SELECT de yaGuardado() es comodidad para la pantalla: resuelve el caso
 * normal -- el reintento llega cuando el primero ya cerro-- con un mensaje
 * claro. Quien impide de verdad el duplicado es el indice UNICO de la base, que
 * es el unico que cubre las dos peticiones simultaneas. Es el mismo reparto que
 * documenta RepuestosDeReparacionService para vrd_reparacion_repuesto_unique:
 * "quien impide de verdad el doble cobro es el indice".
 *
 * Y es correcto en los dos ordenes. Si la otra peticion commitea primero, InnoDB
 * nos hizo esperar en el indice, nos rechaza con 1062, nuestra transaccion ya
 * revirtio entera y releer encuentra la suya. Si la otra revierte, nuestro
 * INSERT entra.
 *
 * ⚠️ $fillable GANA A $guarded
 * Venta declara los dos. Sin 'clave_idempotencia' en su $fillable, create() la
 * descarta EN SILENCIO, la clave se guarda como NULL, el indice unico admite
 * todos los NULL que quieras y toda esta maquinaria queda inerte sin un solo
 * error que lo delate. Es la trampa que ya se cobro a mano_obra. Compra es
 * $guarded = ['id'] y no necesita nada.
 */
trait GuardadoIdempotenteTrait
{
    /**
     * #[Locked] porque es la identidad del intento: si el cliente pudiera
     * cambiarla, reenviar con otra clave crearia el segundo documento y la
     * proteccion seria decorativa.
     */
    #[Locked]
    public string $claveIdempotencia = '';

    /**
     * Siembra una clave nueva. Va en mount() o al abrir el modal, NUNCA en
     * render(): render corre en cada request y la clave cambiaria entre el
     * intento y el reintento, que es exactamente lo que hay que evitar.
     */
    protected function nuevaClaveIdempotencia(): void
    {
        $this->claveIdempotencia = (string) Str::uuid();
    }

    /**
     * El documento que este mismo formulario ya guardo, si existe.
     *
     * @param  class-string<Model>  $modelo
     */
    protected function yaGuardado(string $modelo): ?Model
    {
        // Sin clave no hay nada que comparar: un componente que no la sembro
        // (o un flujo viejo) se comporta como antes en vez de reventar.
        if ($this->claveIdempotencia === '') {
            return null;
        }

        return $modelo::where('clave_idempotencia', $this->claveIdempotencia)->first();
    }

    /** Los datos de la clave, para mezclar en el create() del documento. */
    protected function datosDeIdempotencia(): array
    {
        return ['clave_idempotencia' => $this->claveIdempotencia ?: null];
    }

    /**
     * Si la excepcion es el choque de NUESTRO indice.
     *
     * Se comprueba el nombre y no solo el 1062: en la misma transaccion hay
     * otros indices unicos -- vd_reparacion_repuesto_unico, que impide cobrar
     * dos veces la misma pieza, o el de un telefono en dos ventas -- y tomar uno de
     * esos por nuestro daria un "ya se guardo" falso sobre una venta que de
     * verdad fallo.
     */
    protected function esClaveDuplicada(QueryException $e): bool
    {
        return ($e->errorInfo[1] ?? null) === 1062
            && str_contains($e->getMessage(), 'clave_idem');
    }

    /** El aviso que distingue "no entro" de "ya entro". */
    protected function avisarYaGuardado(Model $documento, string $queEs): void
    {
        toastr()->info(
            "Esta {$queEs} ya se habia guardado (#{$documento->id}). No se creo una segunda."
        );
    }
}
