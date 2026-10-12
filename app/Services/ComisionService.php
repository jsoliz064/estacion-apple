<?php

namespace App\Services;

use App\Enums\ComisionOrigen;
use App\Enums\ReparacionTipo;
use App\Models\Comision;
use App\Models\ComisionLiquidacion;
use App\Models\ProductoReparacion;
use App\Models\Tecnicos;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * El unico que escribe `comisiones` y `comisiones_liquidaciones` (docs/05).
 *
 *  - VENDEDOR: el % de su ficha sobre TODA la ganancia de la venta
 *    (Venta::ganancia(): equipos, repuestos y accesorios; la permuta es un pago,
 *    no baja la venta). Se gana cuando la venta queda pagada (`pagada_at`).
 *  - TECNICO: el % de su ficha sobre la mano de obra de la reparacion
 *    (`costo`). Se gana al terminarla. La de GARANTIA no comisiona (decision
 *    del usuario: no se le cobro nada al cliente).
 *
 * Reglas:
 *  - Una fila por documento aunque el monto sea 0: el % se CONGELA el dia que
 *    nace, y cambiar despues la ficha no reescribe lo anterior.
 *  - Una ganancia negativa da comision 0.
 *  - Una comision LIQUIDADA no se toca: si luego se edita o anula la venta,
 *    queda como se pago ("hay que ajustarlo a mano", docs/05).
 *
 * Ningun metodo abre transaccion: la abre el componente.
 */
class ComisionService
{
    /**
     * La comision del vendedor, al dia con la venta. La llama
     * PagoService::sincronizar(), por donde pasan todos los flujos que mueven
     * el total o lo cobrado: crear, editar, cobrar, anular un pago o una linea.
     */
    public function sincronizarVenta(Venta $venta): void
    {
        if (!$venta->user_id) {
            return;
        }

        $comision = Comision::where('venta_id', $venta->id)->lockForUpdate()->first();

        if ($comision?->estaLiquidada()) {
            return;
        }

        $porcentaje = $comision
            ? (float) $comision->porcentaje
            : (float) (User::whereKey($venta->user_id)->value('comision_porcentaje') ?? 0);
        $base = $venta->ganancia();

        $datos = [
            'referencia' => Str::limit('Venta #' . $venta->id . ($venta->nombreCliente() ? ' · ' . $venta->nombreCliente() : ''), 250),
            'base' => $base,
            'monto' => self::monto($base, $porcentaje),
            'ganada_at' => $venta->pagada_at,
        ];

        if ($comision) {
            $comision->update($datos);

            return;
        }

        Comision::create($datos + [
            'origen' => ComisionOrigen::Venta->value,
            'user_id' => $venta->user_id,
            'venta_id' => $venta->id,
            'porcentaje' => $porcentaje,
        ]);
    }

    /**
     * Antes de borrar una venta anulada: se va su comision no liquidada. La
     * liquidada se queda (la FK la deja sin venta), porque ese dinero se pago.
     */
    public function desligarVenta(Venta $venta): void
    {
        Comision::where('venta_id', $venta->id)->whereNull('liquidacion_id')->delete();
    }

    /**
     * La comision del tecnico, al dia con la reparacion. La dispara
     * ProductoReparacionObserver en cada save().
     */
    public function sincronizarReparacion(ProductoReparacion $reparacion): void
    {
        // Releida: la instancia que se guardo puede estar vieja en las columnas
        // que no toco (otra pantalla la termino entre tanto), y un update de la
        // mano de obra la devolvia a pendiente.
        $reparacion = ProductoReparacion::find($reparacion->id);

        if (!$reparacion) {
            return;
        }

        $comision = Comision::where('producto_reparacion_id', $reparacion->id)->lockForUpdate()->first();

        if ($comision?->estaLiquidada()) {
            return;
        }

        $tipo = $reparacion->tipo instanceof ReparacionTipo ? $reparacion->tipo->value : $reparacion->tipo;

        if (!$reparacion->tecnico_id || $tipo === ReparacionTipo::Garantia->value) {
            $comision?->delete();

            return;
        }

        // Si cambio el tecnico, la comision es del nuevo, con su %.
        $porcentaje = $comision && (int) $comision->tecnico_id === (int) $reparacion->tecnico_id
            ? (float) $comision->porcentaje
            : (float) (Tecnicos::whereKey($reparacion->tecnico_id)->value('comision_porcentaje') ?? 0);
        $base = round((float) $reparacion->costo, 2);

        $datos = [
            'tecnico_id' => $reparacion->tecnico_id,
            'referencia' => $this->referenciaReparacion($reparacion),
            'base' => $base,
            'porcentaje' => $porcentaje,
            'monto' => self::monto($base, $porcentaje),
            'ganada_at' => self::ganadaReparacion($reparacion),
        ];

        if ($comision) {
            $comision->update($datos);

            return;
        }

        Comision::create($datos + [
            'origen' => ComisionOrigen::Reparacion->value,
            'producto_reparacion_id' => $reparacion->id,
        ]);
    }

    /**
     * Paga las comisiones elegidas de una persona.
     *
     * @param  string  $beneficiario  U-5 (vendedor) o T-3 (tecnico)
     * @param  array<int>  $ids
     *
     * @throws ValidationException si alguna ya no esta por pagar o no es suya
     *         (otra pantalla la liquido entre tanto).
     */
    public function liquidar(string $beneficiario, array $ids, string $desde, string $hasta, ?string $nota, User $user, ?string $clave = null): ComisionLiquidacion
    {
        [$tipo, $id] = Comision::partirClave($beneficiario);
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if (!$tipo || $ids === []) {
            throw ValidationException::withMessages(['seleccion' => 'Elige al menos una comisión para pagar.']);
        }

        $filas = Comision::whereKey($ids)->orderBy('id')->lockForUpdate()->get();
        $columna = $tipo === 'U' ? 'user_id' : 'tecnico_id';

        $ajenas = $filas->count() !== count($ids)
            || $filas->contains(fn(Comision $c) => (int) $c->{$columna} !== $id || $c->ganada_at === null || $c->estaLiquidada());

        if ($ajenas) {
            throw ValidationException::withMessages([
                'seleccion' => 'Alguna de las comisiones ya no está por pagar: puede que otra persona la haya liquidado. Recarga la pantalla.',
            ]);
        }

        $liquidacion = ComisionLiquidacion::create([
            $columna => $id,
            'desde' => $desde,
            'hasta' => $hasta,
            'total' => round((float) $filas->sum('monto'), 2),
            'cantidad' => $filas->count(),
            'nota' => $nota !== null && trim($nota) !== '' ? trim($nota) : null,
            'pagado_por' => $user->id,
            'clave_idempotencia' => $clave,
        ]);

        Comision::whereKey($ids)->update(['liquidacion_id' => $liquidacion->id]);

        return $liquidacion;
    }

    /**
     * Deshace una liquidacion: sus comisiones vuelven a por pagar, al dia con
     * su documento (pudo editarse mientras estaban congeladas). La que ya no
     * tiene documento (su venta se anulo despues de pagarla) se borra: solo
     * existia porque estaba pagada.
     */
    public function anularLiquidacion(ComisionLiquidacion $liquidacion): void
    {
        $liquidacion = ComisionLiquidacion::whereKey($liquidacion->id)->lockForUpdate()->first();

        if (!$liquidacion) {
            throw ValidationException::withMessages(['liquidacion' => 'Esa liquidación ya no existe: alguien la anuló. Recarga la pantalla.']);
        }

        $comisiones = $liquidacion->comisiones()->orderBy('id')->lockForUpdate()->get();
        Comision::where('liquidacion_id', $liquidacion->id)->update(['liquidacion_id' => null]);
        $liquidacion->delete();

        foreach ($comisiones as $comision) {
            if ($comision->venta_id) {
                $this->sincronizarVenta(Venta::findOrFail($comision->venta_id));
            } elseif ($comision->producto_reparacion_id) {
                $this->sincronizarReparacion(ProductoReparacion::findOrFail($comision->producto_reparacion_id));
            } else {
                Comision::whereKey($comision->id)->delete();
            }
        }
    }

    // ------------------------------------------------------------------ reglas

    /** El monto de una base y un %. Una ganancia negativa da 0. */
    public static function monto(float $base, float $porcentaje): float
    {
        return round(max(0, $base) * $porcentaje / 100, 2);
    }

    /** Cuando se gano la comision de una reparacion: al terminarla. */
    public static function ganadaReparacion(ProductoReparacion $reparacion): ?Carbon
    {
        if ($reparacion->estado !== 'Terminado') {
            return null;
        }

        return $reparacion->fecha_recogida
            ? Carbon::parse($reparacion->fecha_recogida)->startOfDay()
            : now();
    }

    private function referenciaReparacion(ProductoReparacion $reparacion): string
    {
        $producto = $reparacion->producto()->first(['id', 'descripcion', 'imei']);

        return Str::limit(
            'Reparación #' . $reparacion->id
                . ($producto ? ' · ' . ($producto->descripcion ?: 'Equipo') . ' · IMEI ' . $producto->imei : ''),
            250,
        );
    }
}
