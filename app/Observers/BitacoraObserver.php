<?php

namespace App\Observers;

use App\Models\Bitacora;
use Illuminate\Database\Eloquent\Model;

/**
 * El unico escritor de la bitacora para los cambios de modelo.
 *
 * Antes no habia observer ninguno: las diecinueve escrituras de
 * productos_historiales las sostenia cada call site por su cuenta, y por eso
 * cubrian solo el `estado` -- editar el precio, el IMEI, el modelo o la sucursal
 * de un telefono no dejaba absolutamente nada.
 *
 * Lo que se apunta sale de dos fuentes que se combinan en UNA fila: el diff real
 * del modelo, y lo que haya anotado quien hace la operacion (ver Auditable).
 */
class BitacoraObserver
{
    public function created(Model $modelo): void
    {
        $this->escribir($modelo, 'creado', $this->atributosRelevantes($modelo));
    }

    public function updated(Model $modelo): void
    {
        $cambios = $this->diff($modelo);
        $anotado = $modelo->consumirBitacoraPendiente();

        // Nada que contar: un save() que solo movio updated_at, o que toco unica
        // y exclusivamente columnas excluidas. Sin este corte la tabla se llena
        // de filas vacias.
        if ($cambios === [] && $anotado === null) {
            return;
        }

        $this->escribir($modelo, 'editado', $cambios, $anotado);
    }

    /**
     * La red de seguridad del hecho anotado que no cambio ninguna columna.
     *
     * Eloquent NO dispara `updated` cuando el save no encuentra nada sucio, y
     * ese caso existe de verdad: reabrir un producto que ya esta en Fuera para
     * corregirle la descripcion escribe `estado = 'Fuera'` sobre 'Fuera'. El
     * hecho hay que registrarlo igual -- la nota ES el hecho.
     *
     * `saved` corre despues de `created` y de `updated`, y los dos consumen lo
     * anotado; si algo sigue pendiente al llegar aqui es que ninguno disparo.
     */
    public function saved(Model $modelo): void
    {
        $anotado = $modelo->consumirBitacoraPendiente();

        if ($anotado === null) {
            return;
        }

        $this->escribir($modelo, 'editado', [], $anotado);
    }

    public function deleted(Model $modelo): void
    {
        // El estado completo en el momento de borrarlo: despues no hay a donde
        // ir a buscarlo, porque auditable_id apunta a una fila que ya no existe.
        $this->escribir($modelo, 'eliminado', $this->atributosRelevantes($modelo, alBorrar: true));
    }

    /**
     * Escribe la fila, dando prioridad a lo anotado sobre el nombre generico.
     *
     * Se llama a Bitacora::registrar() SIEMPRE con el anotado ya consumido, para
     * que un anotar() que no llegue a guardarse no se quede pegado al modelo y
     * acabe etiquetando el siguiente save.
     */
    protected function escribir(Model $modelo, string $generico, array $cambios, ?array $anotado = null): void
    {
        $anotado ??= $modelo->consumirBitacoraPendiente();

        Bitacora::registrar(
            modelo: $modelo,
            evento: $anotado['evento'] ?? $generico,
            descripcion: $anotado['descripcion'] ?? null,
            enlaces: $anotado['enlaces'] ?? [],
            cambios: $cambios === [] ? null : $cambios,
        );
    }

    /**
     * [columna => [antes, despues]] de lo que acaba de cambiar.
     *
     * getChanges() devuelve lo que el save() escribio de verdad, no lo que se le
     * paso: un update() con el mismo valor que ya tenia no aparece aqui, que es
     * justo lo que se quiere.
     */
    protected function diff(Model $modelo): array
    {
        $excluidas = array_flip($modelo->columnasNoAuditadas());
        $cambios = [];

        foreach ($modelo->getChanges() as $columna => $nuevo) {
            if (isset($excluidas[$columna])) {
                continue;
            }

            $antes = $modelo->getOriginal($columna);

            if ($this->equivalentes($antes, $nuevo)) {
                continue;
            }

            $cambios[$columna] = [$antes, $nuevo];
        }

        return $cambios;
    }

    /**
     * Si dos valores son el mismo dato escrito de dos maneras.
     *
     * Eloquent marca como sucio lo que no es identico, y estos modelos no
     * declaran casts: la base devuelve 0 y "100.00", el formulario devuelve
     * false y 100. Sin esto, abrir el modal de editar un telefono y guardar SIN
     * TOCAR NADA dejaba una fila 'editado' con disponible_catalogo: 0 -> false
     * -- medido, no supuesto -- y la bitacora se llenaba de cambios que no lo son.
     */
    protected function equivalentes(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if ($a === null || $b === null) {
            // Un vacio que pasa a nulo tampoco es un cambio que contar.
            return ($a ?? '') === ($b ?? '');
        }

        $numerico = fn($v) => is_bool($v) || is_numeric($v);

        if ($numerico($a) && $numerico($b)) {
            return abs((float) $a - (float) $b) < 0.000001;
        }

        return (string) $a === (string) $b;
    }

    /**
     * El retrato del modelo al crearlo o al borrarlo, sin las excluidas.
     *
     * Con la MISMA forma [antes, despues] que un diff: crear es pasar de nada a
     * un valor, y borrar de un valor a nada. Asi `cambios` tiene una sola forma
     * y un solo componente la pinta en las tres pantallas de historial.
     *
     * Sin los nulos: el alta de un telefono tiene media docena de columnas
     * vacias, y [null, null] no cuenta nada.
     */
    protected function atributosRelevantes(Model $modelo, bool $alBorrar = false): array
    {
        $retrato = [];

        foreach ($modelo->attributesToArray() as $columna => $valor) {
            if ($valor === null || in_array($columna, $modelo->columnasNoAuditadas(), true)) {
                continue;
            }

            $retrato[$columna] = $alBorrar ? [$valor, null] : [null, $valor];
        }

        return $retrato;
    }
}
