<?php

namespace App\Services;

use App\Enums\ProductoEstado;
use App\Models\Producto;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO camino de escritura de productos.estado.
 *
 * Hermano de StockService, y por el mismo motivo: el estado se cambiaba
 * desde DIEZ sitios en ocho componentes, cada uno con su idiom, y dos de ellos
 * se olvidaban del historial. Unificar la escritura es lo que permite poner la
 * precondicion y el permiso una sola vez en lugar de diez.
 *
 * LA PRECONDICION ES EL CORAZON
 * Cada llamada declara desde que estado CREE venir. Si el producto ya esta en
 * otro, la operacion no se hace y el mensaje dice en cual esta. Eso es lo que
 * vuelve inocuo reintentar: el segundo intento de vender un telefono ya vendido
 * no crea una segunda venta, avisa.
 *
 * POR QUE UNA LECTURA BLOQUEADA Y NO UN `UPDATE ... WHERE estado = ?`
 * El idiom atomico de StockService::retirar() -- poner la condicion en
 * el WHERE y mirar las filas afectadas -- aqui se rompe en SILENCIO. Laravel no
 * activa PDO::MYSQL_ATTR_FOUND_ROWS, asi que MySQL devuelve filas CAMBIADAS, no
 * filas encontradas: un `SET estado='Fuera' WHERE estado='Fuera'` -- que es
 * justo lo que pasa al reabrir un producto en Fuera para corregirle la
 * descripcion-- devuelve 0 y fingiria un conflicto inexistente.
 *
 * En retirar() el idiom funciona porque `cantidad - n` SIEMPRE cambia el valor.
 * Aqui hace falta un SELECT ... FOR UPDATE, el mismo bloqueo que VentaService
 * toma sobre los equipos antes de venderlos.
 *
 * EL HISTORIAL SALE DE LA MISMA VARIABLE QUE EL ESTADO
 * CLAUDE.md promete que cada cambio escribe su fila y que es "la unica traza del
 * telefono". No habia observer: lo sostenia cada call site por su cuenta, y ya
 * estaba roto -- TecnicoTerminarModal y ReparacionEditModal escribian
 * 'Reparacion' en el historial mientras movian el producto a 'Inventario', y
 * dos caminos de compra no escribian fila. Aqui los dos valores salen de
 * $destino, asi que no pueden divergir.
 *
 * TRANSACCIONES
 * Ningun metodo abre la suya: asumen la del llamador, para que una venta fallida
 * revierta el estado Y el documento. El lock de fila se mantiene hasta que el
 * llamador cierra.
 */
class EstadoProductoService
{
    /**
     * Mueve un producto de estado, dejando su fila de historial.
     *
     * @param  ProductoEstado|ProductoEstado[]  $esperado  el estado (o los estados)
     *         en que el llamador cree que esta el producto. Varios para los casos
     *         con dos origenes legitimos, como Fuera/Roto -> Inventario.
     * @param  array  $enlaces  claves opcionales del historial: venta_id,
     *         producto_reparacion_id.
     * @param  bool  $exigirPermiso  solo donde el usuario ELIGE el estado de una
     *         lista filtrada por permisos (los dos modales de estado). En el
     *         resto el cambio es consecuencia de otra operacion -- vender,
     *         terminar una reparacion-- que ya tiene su propio permiso, y
     *         exigirlo aqui ademas dejaria sin vender a quien puede vender.
     *
     * @throws ValidationException si el producto no esta en el estado esperado.
     */
    public function cambiar(
        int $productoId,
        ProductoEstado|array $esperado,
        ProductoEstado $destino,
        string $descripcion,
        array $enlaces = [],
        bool $exigirPermiso = false,
    ): Producto {
        if ($exigirPermiso) {
            abort_unless(ProductoEstado::validatePermission($destino->value), 403);
        }

        $producto = $this->bloquear($productoId, $esperado);

        // Se anota ANTES del update y el que escribe es BitacoraObserver: asi
        // sale UNA fila con el evento, la frase y el diff del estado, en vez de
        // la frase por un lado y el cambio por otro. Y el evento sale del mismo
        // $destino que el update, que es lo que impide que el historial diga
        // una cosa y el producto otra.
        //
        // Si el estado no cambia (reabrir un Fuera para corregir la nota), el
        // observer lo registra igual desde su saved(): la nota es el hecho.
        $producto->anotar($destino->value, $descripcion, $enlaces);
        $producto->update(['estado' => $destino->value]);

        return $producto;
    }

    /**
     * Marca un producto como vendido. Azucar sobre cambiar() para que las tres
     * puertas de venta no repitan la lista de estados vendibles.
     *
     * NO toca disponible_catalogo. Las tres puertas lo ponian en false al
     * vender, y era redundante y destructivo a la vez: el catalogo ya excluye
     * Vendido con un whereNotIn explicito, y su condicion es
     * `disponible_catalogo = 1 OR estado IN (Inventario, Oferta)` -- un OR, asi
     * que la bandera no cambiaba nada de lo que se ve. Lo que si hacia era
     * pisar para siempre una casilla que marca el operador a mano en el modal
     * del lote, sin guardar el valor anterior en ninguna parte: al cancelar la
     * venta no habia nada que restaurar.
     */
    public function vender(
        int $productoId,
        string $descripcion,
        array $enlaces = [],
        ProductoEstado $destino = ProductoEstado::Vendido,
        ?ProductoEstado $esperado = null,
    ): Producto {
        // Vendido o Credito: lo decide VentaService segun lo cobrado, ANTES de
        // vender, para que el historial no anote Vendido y enseguida Credito.
        if (!in_array($destino->value, ProductoEstado::vendidos(), true)) {
            throw new \LogicException("vender() solo lleva a un estado vendido, no a {$destino->value}.");
        }

        // Esperado: disponible, o Reserva cuando la venta concreta SU reserva
        // (VentaService ya bloqueo la reserva y comprobo que es de este equipo).
        return $this->cambiar(
            $productoId,
            $esperado ?? array_map(fn($valor) => ProductoEstado::from($valor), ProductoEstado::disponibles()),
            $destino,
            $descripcion,
            $enlaces,
        );
    }

    /**
     * Relee el producto con bloqueo y confirma que sigue donde el llamador cree.
     *
     * El mensaje NOMBRA el estado que encontro. Antes decia solo "ya no esta
     * disponible", que habla del producto y no del guardado: el usuario cuya
     * venta si se habia registrado -- y que reintentaba porque se le corto la
     * red antes de ver la respuesta-- leia eso y concluia que no se guardo.
     *
     * @param  ProductoEstado|ProductoEstado[]  $esperado
     */
    private function bloquear(int $productoId, ProductoEstado|array $esperado): Producto
    {
        $esperados = array_map(
            fn(ProductoEstado $estado) => $estado->value,
            is_array($esperado) ? $esperado : [$esperado],
        );

        $producto = Producto::whereKey($productoId)->lockForUpdate()->first();

        if (!$producto) {
            throw ValidationException::withMessages([
                'detalles' => 'El producto ya no existe. Recarga la pantalla.',
            ]);
        }

        // Un equipo dado de baja esta archivado: no se vende, no se repara, no
        // se mueve. Va aqui y no en cada pantalla porque todo cambio de estado
        // pasa por este metodo, asi que ninguna puerta puede saltarselo.
        if ($producto->estaDadoDeBaja()) {
            throw ValidationException::withMessages([
                'detalles' => "El producto {$producto->imei} esta dado de baja. Revierte la baja antes de operar con el.",
            ]);
        }

        if (!in_array($producto->estado, $esperados, true)) {
            throw ValidationException::withMessages([
                'detalles' => $this->mensajeEstadoInesperado($producto),
            ]);
        }

        return $producto;
    }

    /** El mensaje que distingue "no entro" de "ya entro". */
    private function mensajeEstadoInesperado(Producto $producto): string
    {
        $etiqueta = ProductoEstado::tryFrom($producto->estado)?->label() ?? $producto->estado;

        return "El producto {$producto->imei} ya esta en {$etiqueta}: alguien lo cambio "
            . 'mientras tenias esta pantalla abierta, o esta operacion ya se guardo. '
            . 'Recarga para ver como quedo.';
    }
}
