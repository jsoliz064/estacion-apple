<?php

namespace App\Services;

use App\Enums\ArticuloTipo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * El UNICO camino de escritura del stock de repuestos y accesorios.
 *
 * Los dos comparten stock_sucursales, cada uno por su columna (repuesto_id /
 * accesorio_id). Todos los metodos reciben el ArticuloTipo y el id: la tabla y
 * la columna salen SOLO del enum, nunca de la pantalla, asi que interpolarlas en
 * el SQL crudo es seguro.
 *
 * Antes de existir, el stock se movia con increment()/decrement() directos desde
 * dieciseis sitios en ocho componentes. Unificar la escritura es lo que vuelve
 * el stock verificable: una funcion que probar.
 *
 * POR QUE NO HAY UN mover($delta) CON SIGNO
 * El fallo que mas veces aparecio en este modulo es un signo copiado del modulo
 * de al lado: una compra calcula (nuevo - original) y una venta (original -
 * nuevo), LAS DOS CORRECTAS para su documento, y por eso se copiaban mal. Aqui
 * las cantidades son SIEMPRE positivas y la direccion la pone el NOMBRE del
 * metodo.
 *
 * TRANSACCIONES
 * Ningun metodo abre la suya: asumen la del llamador. Una venta que falla a
 * mitad tiene que revertir el stock Y el documento, y eso solo pasa si comparten
 * transaccion.
 *
 * USO
 *   $stock->retirar(ArticuloTipo::Accesorio, $id, $sucursalId, 3);
 *   ... mas movimientos ...
 *   $stock->recalcularTotales();   // UNA vez, al final, antes de cerrar
 *
 * El SQL crudo es invisible para BitacoraObserver: un ajuste de stock que
 * importe como hecho se registra a mano con Bitacora::registrar().
 */
class StockService
{
    /** Articulos tocados ("Tipo:id" => [tipo, id]), para recalcular el total una vez. */
    private array $tocados = [];

    // ------------------------------------------------------------------ entradas

    /**
     * Suma unidades al stock de una sucursal. Compras, devoluciones y el destino
     * de una transferencia.
     */
    public function ingresar(ArticuloTipo $tipo, int $id, ?int $sucursalId, int $cantidad): void
    {
        // Corte obligatorio, y es la linea mas importante de la clase: al editar
        // un documento viejo solo para cambiar el precio, su linea pasa por
        // ajustarSalida(antes: 5, ahora: 5) -> delta 0. Si eso llegara al UPDATE
        // de retirar() con la fila inexistente daria un falso "sin stock" y
        // ningun documento viejo se podria volver a guardar.
        if ($cantidad === 0) {
            return;
        }

        $this->exigirPositiva($cantidad);
        $sucursalId = $this->exigirSucursal($sucursalId);
        $col = $tipo->columna();

        // Una sola sentencia: crea la fila si no existe y suma si existe. Funciona
        // con dos UNIQUE en la tabla porque en cada fila solo uno puede coincidir
        // (la otra columna de articulo es NULL, y los NULL no chocan). No se usa
        // VALUES(): esta deprecado desde MySQL 8.0.20.
        DB::statement(
            "INSERT INTO stock_sucursales
                 ({$col}, sucursal_id, cantidad, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE cantidad = cantidad + ?, updated_at = ?",
            [$id, $sucursalId, $cantidad, now(), now(), $cantidad, now()]
        );

        $this->tocar($tipo, $id);
    }

    // ------------------------------------------------------------------- salidas

    /**
     * Resta unidades del stock de una sucursal. Ventas, reparaciones, regalos,
     * bajas, el origen de una transferencia y revertir una linea de compra.
     *
     * @throws ValidationException si esa sucursal no tiene las unidades.
     */
    public function retirar(ArticuloTipo $tipo, int $id, ?int $sucursalId, int $cantidad): void
    {
        if ($cantidad === 0) {
            return;                       // ver el comentario de ingresar()
        }

        $this->exigirPositiva($cantidad);
        $sucursalId = $this->exigirSucursal($sucursalId);
        $col = $tipo->columna();

        // El `WHERE cantidad >= ?` ES la validacion de stock, y es atomica:
        // InnoDB toma el lock de la fila dentro de la propia sentencia. Un
        // disponible() leido antes y un UPDATE despues dejan hueco para que otro
        // vendedor se lleve las mismas unidades en medio. No lo saques a un SELECT.
        //
        // 0 filas afectadas significa "no hay fila" O "no alcanza". Funciona
        // porque `cantidad - n` siempre cambia el valor (MySQL devuelve filas
        // CAMBIADAS, no encontradas).
        $afectadas = DB::update(
            "UPDATE stock_sucursales
                SET cantidad = cantidad - ?, updated_at = ?
              WHERE {$col} = ? AND sucursal_id = ? AND cantidad >= ?",
            [$cantidad, now(), $id, $sucursalId, $cantidad]
        );

        if ($afectadas === 0) {
            throw ValidationException::withMessages([
                'detalles' => $this->mensajeSinStock($tipo, $id, $sucursalId, $cantidad),
            ]);
        }

        $this->tocar($tipo, $id);
    }

    // -------------------------------------------------------------- ajustes 1:1
    //
    // Estos dos existen para que NINGUN llamador escriba una resta. La asimetria
    // entre compras y ventas vive aqui, en el nombre del metodo.

    /** Compras: subir la cantidad de una linea INGRESA mas stock. */
    public function ajustarEntrada(ArticuloTipo $tipo, int $id, ?int $sucursalId, int $antes, int $ahora): void
    {
        $delta = $ahora - $antes;

        $delta >= 0
            ? $this->ingresar($tipo, $id, $sucursalId, $delta)
            : $this->retirar($tipo, $id, $sucursalId, -$delta);
    }

    /** Ventas, reparaciones y regalos: subir la cantidad de una linea RETIRA mas stock. */
    public function ajustarSalida(ArticuloTipo $tipo, int $id, ?int $sucursalId, int $antes, int $ahora): void
    {
        $delta = $ahora - $antes;

        $delta >= 0
            ? $this->retirar($tipo, $id, $sucursalId, $delta)
            : $this->ingresar($tipo, $id, $sucursalId, -$delta);
    }

    // -------------------------------------------------------------- transferencia

    /**
     * Mueve unidades de una sucursal a otra y deja el documento.
     *
     * @throws ValidationException si el origen no tiene stock o coincide con el destino.
     */
    public function transferir(ArticuloTipo $tipo, int $id, int $origenId, int $destinoId, int $cantidad, ?int $userId): void
    {
        if ($origenId === $destinoId) {
            throw ValidationException::withMessages([
                'sucursal2_id' => 'El origen y el destino tienen que ser sucursales distintas.',
            ]);
        }

        $this->exigirPositiva($cantidad);

        if ($cantidad === 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'Indica cuantas unidades transferir.',
            ]);
        }

        // Retirar PRIMERO: si no hay stock, la excepcion revierte antes de haber
        // inventado nada en el destino.
        $this->retirar($tipo, $id, $origenId, $cantidad);
        $this->ingresar($tipo, $id, $destinoId, $cantidad);

        DB::table('stock_transferencias')->insert([
            $tipo->columna() => $id,
            'sucursal_origen_id' => $origenId,
            'sucursal_destino_id' => $destinoId,
            'cantidad' => $cantidad,
            'user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ------------------------------------------------------------------ lectura

    /**
     * Stock de una sucursal. Sirve para pintar pantalla y para avisar antes de
     * confirmar; NO es la puerta: la validacion de verdad vive dentro del UPDATE
     * de retirar().
     */
    public function disponible(ArticuloTipo $tipo, int $id, ?int $sucursalId): int
    {
        if ($sucursalId === null) {
            return 0;
        }

        return (int) DB::table('stock_sucursales')
            ->where($tipo->columna(), $id)
            ->where('sucursal_id', $sucursalId)
            ->value('cantidad');
    }

    /** El stock de un articulo en todas sus sucursales: [sucursal_id => cantidad]. */
    /**
     * Fija el stock minimo de un articulo en una sucursal. Crea la fila (en 0
     * unidades) si no existia: el minimo puede ir antes que la mercaderia.
     * No toca `cantidad` ni el total cacheado.
     */
    public function fijarMinimo(ArticuloTipo $tipo, int $id, int $sucursalId, int $minimo): void
    {
        if ($minimo < 0) {
            throw new InvalidArgumentException("El minimo no puede ser negativo: {$minimo}.");
        }

        $col = $tipo->columna();

        DB::statement(
            "INSERT INTO stock_sucursales
                 ({$col}, sucursal_id, cantidad, minimo, created_at, updated_at)
             VALUES (?, ?, 0, ?, ?, ?)
             ON DUPLICATE KEY UPDATE minimo = ?, updated_at = ?",
            [$id, $sucursalId, $minimo, now(), now(), $minimo, now()]
        );
    }

    /** [sucursal_id => minimo] de un articulo. */
    public function minimosPorSucursal(ArticuloTipo $tipo, int $id): array
    {
        return DB::table('stock_sucursales')
            ->where($tipo->columna(), $id)
            ->pluck('minimo', 'sucursal_id')
            ->map(fn($m) => (int) $m)
            ->all();
    }

    public function porSucursal(ArticuloTipo $tipo, int $id): array
    {
        return DB::table('stock_sucursales')
            ->where($tipo->columna(), $id)
            ->pluck('cantidad', 'sucursal_id')
            ->map(fn($c) => (int) $c)
            ->all();
    }

    // ----------------------------------------------------------- total cacheado

    /**
     * Reescribe repuestos.cantidad / accesorios.cantidad desde stock_sucursales
     * para los articulos tocados. Una sentencia por articulo, idempotente y
     * AUTO-SANANTE: si el total derivo por cualquier via, esto lo cuadra.
     *
     * NO hay un increment paralelo sobre el total: seria un segundo contador
     * capaz de divergir del primero sin forma de saber cual miente.
     *
     * Se llama UNA vez al final y no una por movimiento: una edicion puede mover
     * el mismo articulo dos veces en un mismo guardado.
     */
    public function recalcularTotales(): void
    {
        foreach ($this->tocados as [$tipo, $id]) {
            $tabla = $tipo->tabla();
            $col = $tipo->columna();

            DB::update(
                "UPDATE {$tabla}
                    SET cantidad = COALESCE(
                            (SELECT SUM(cantidad) FROM stock_sucursales WHERE {$col} = ?), 0
                        ),
                        updated_at = ?
                  WHERE id = ?",
                [$id, now(), $id]
            );
        }

        $this->tocados = [];
    }

    // ------------------------------------------------------------------- guardas

    private function tocar(ArticuloTipo $tipo, int $id): void
    {
        $this->tocados["{$tipo->value}:{$id}"] = [$tipo, $id];
    }

    private function exigirPositiva(int $cantidad): void
    {
        // InvalidArgumentException y no ValidationException: una cantidad
        // negativa aqui no es culpa del usuario, es un llamador que calculo un
        // signo, que es justo lo que estos metodos prohiben.
        if ($cantidad < 0) {
            throw new InvalidArgumentException(
                "StockService: las cantidades son siempre positivas, llego {$cantidad}. "
                . 'Para una diferencia usa ajustarEntrada() o ajustarSalida().'
            );
        }
    }

    /**
     * Las sucursales de las lineas son nullable con set null, asi que un null
     * llega de verdad. Se corta con mensaje en vez de crear una fila huerfana:
     * el UNIQUE (articulo, sucursal) NO colisiona entre NULLs en MySQL.
     */
    private function exigirSucursal(?int $sucursalId): int
    {
        if ($sucursalId === null) {
            throw ValidationException::withMessages([
                'detalles' => 'Ese movimiento no tiene sucursal asignada, y el stock se lleva por '
                    . 'sucursal. Asigna la sucursal del documento antes de guardar.',
            ]);
        }

        return $sucursalId;
    }

    private function mensajeSinStock(ArticuloTipo $tipo, int $id, int $sucursalId, int $pedido): string
    {
        $nombre = DB::table($tipo->tabla())->where('id', $id)->value('nombre') ?? "#{$id}";
        $sucursal = DB::table('sucursales')->where('id', $sucursalId)->value('nombre') ?? "#{$sucursalId}";
        $hay = $this->disponible($tipo, $id, $sucursalId);

        return "No hay stock de «{$nombre}» en {$sucursal}: se piden {$pedido} y hay {$hay}.";
    }
}
