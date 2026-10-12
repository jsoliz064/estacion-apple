<?php

namespace App\Services;

use App\Enums\BitacoraEvento;
use App\Enums\LineaTipo;
use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\Compra;
use App\Models\CompraDetalle;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * El UNICO escritor de compras_detalles.
 *
 * Una compra trae equipos (cada uno es una linea de cantidad 1, y el equipo se
 * CREA con ella) y repuestos o accesorios (con cantidad, que entran al stock de
 * la sucursal de la compra).
 *
 * BORRADOR: una compra de 50 equipos no se carga en una sola pantalla sin
 * perderla al primer corte de internet. Se crea la cabecera (crear()) y todo
 * lo demas se guarda linea por linea desde el detalle. Mientras no tiene
 * `finalizada_at`, nada entra al stock y sus equipos esperan en EnCompra (no se
 * venden). finalizar() mete el stock, libera los equipos y registra lo pagado
 * al recibir. Despues se puede seguir corrigiendo, y entonces cada cambio
 * mueve el stock en el acto.
 *
 * El costo de una linea de equipo es su `costo_unidad`: los dos solo los
 * escribe este servicio, y productos:auditar vigila que no diverjan.
 *
 * Ningun metodo abre transaccion: la abre el componente.
 *
 * Formato de una linea de articulo que llega de la pantalla:
 *   ['tipo' => 'Repuesto'|'Accesorio', 'id' => int, 'cantidad' => int, 'costo' => float]
 */
class CompraService
{
    public function __construct(
        private StockService $stock,
    ) {}

    /**
     * Crea la cabecera, en BORRADOR. Lo comprado se carga despues desde el
     * detalle, linea por linea, y lo pagado al recibir va al finalizar.
     *
     * @param  array  $cabecera  proveedor_id, fecha, sucursal_id
     */
    public function crear(array $cabecera, User $user, ?string $clave = null): Compra
    {
        return Compra::create([
            'clave_idempotencia' => $clave,
            'proveedor_id' => $cabecera['proveedor_id'],
            'fecha' => $cabecera['fecha'],
            'sucursal_id' => $cabecera['sucursal_id'],
            'user_id' => $user->id,
            'total' => 0,
        ]);
    }

    /** Edita proveedor y fecha. La sucursal NO cambia: cada linea ya la congelo. */
    public function actualizar(Compra $compra, array $cabecera): Compra
    {
        $compra->update([
            'proveedor_id' => $cabecera['proveedor_id'],
            'fecha' => $cabecera['fecha'],
        ]);

        return $compra;
    }

    /**
     * Agrega un repuesto o accesorio, o cambia la cantidad y el costo de su
     * linea: la clave natural (cd_compra_articulo_unico) hace que reintentar
     * no la duplique. En borrador no toca el stock; en una compra finalizada
     * mueve solo la diferencia (ajustarEntrada), en la sucursal de la linea.
     *
     * @param  array  $linea  ['tipo' => 'Repuesto'|'Accesorio', 'id', 'cantidad', 'costo']
     */
    public function guardarArticulo(Compra $compra, array $linea): CompraDetalle
    {
        $linea = $this->normalizar($linea);
        $tipo = LineaTipo::from($linea['tipo'])->articulo();
        $mueveStock = !$compra->esBorrador();

        $detalle = $compra->detalles()->where($tipo->columna(), $linea['id'])->first();

        if ($detalle) {
            if ($mueveStock) {
                $this->stock->ajustarEntrada($tipo, $linea['id'], $detalle->sucursal_id, (int) $detalle->cantidad, $linea['cantidad']);
            }

            $detalle->update([
                'cantidad' => $linea['cantidad'],
                'costo' => $linea['costo'],
                'subtotal' => round($linea['cantidad'] * $linea['costo'], 2),
            ]);
        } else {
            if ($mueveStock) {
                $this->stock->ingresar($tipo, $linea['id'], $compra->sucursal_id, $linea['cantidad']);
            }

            $detalle = CompraDetalle::create([
                'compra_id' => $compra->id,
                $tipo->columna() => $linea['id'],
                'sucursal_id' => $compra->sucursal_id,
                'cantidad' => $linea['cantidad'],
                'costo' => $linea['costo'],
                'subtotal' => round($linea['cantidad'] * $linea['costo'], 2),
            ]);
        }

        $this->stock->recalcularTotales();
        $compra->recalcularTotal();

        return $detalle;
    }

    /**
     * Quita la linea de un repuesto o accesorio. En una compra finalizada sus
     * unidades salen del stock (retirar() falla con mensaje si ya se vendieron).
     */
    public function quitarArticulo(Compra $compra, int $detalleId): void
    {
        $detalle = $compra->detalles()->whereNull('producto_id')->whereKey($detalleId)->first();

        if (!$detalle) {
            return; // ya se quito (reintento)
        }

        if (!$compra->esBorrador()) {
            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->retirar($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad);
        }

        $detalle->delete();

        $this->stock->recalcularTotales();
        $compra->recalcularTotal();
    }

    /**
     * Los estados a los que puede pasar un equipo al finalizar la compra. Sin
     * Reparacion: mandarlo al tecnico abre una reparacion, y un equipo que
     * todavia no se recibio no entra al banco de nadie.
     *
     * @return string[]
     */
    public static function estadosDestinoBorrador(): array
    {
        return [ProductoEstado::Inventario->value, ProductoEstado::Fuera->value, ProductoEstado::Roto->value];
    }

    /**
     * Cierra el borrador: los equipos pasan de EnCompra a su estado elegido,
     * los articulos entran al stock de la sucursal de su linea, y se registra lo
     * pagado al recibir. Orden de bloqueo de la casa: la compra (la bloquea el
     * componente), los equipos por id, el stock.
     *
     * Rechaza una compra ya finalizada: reintentar no mete el stock dos veces.
     *
     * @param  array  $pagos  lo pagado al recibir (filas de PagoProveedorService)
     */
    public function finalizar(Compra $compra, User $user, array $pagos = [], ?string $clave = null): Compra
    {
        if (!$compra->esBorrador()) {
            throw ValidationException::withMessages([
                'detalles' => "La compra #{$compra->id} ya se finalizó.",
            ]);
        }

        $detalles = $compra->detalles()->get();

        if ($detalles->isEmpty()) {
            throw ValidationException::withMessages([
                'detalles' => 'La compra no tiene nada cargado: agrega equipos o artículos antes de finalizarla.',
            ]);
        }

        $estados = app(EstadoProductoService::class);
        $equipos = $detalles->whereNotNull('producto_id')->sortBy('producto_id');

        foreach ($equipos as $detalle) {
            $destino = ProductoEstado::tryFrom((string) $detalle->estado_destino) ?? ProductoEstado::Inventario;

            $estados->cambiar(
                (int) $detalle->producto_id,
                ProductoEstado::EnCompra,
                $destino,
                "Compra #{$compra->id} finalizada: el equipo pasa a {$destino->label()}",
            );

            $detalle->update(['estado_destino' => null]);
        }

        // Ordenado por (tipo, id), como en todos los flujos que mueven stock.
        $articulos = $detalles->whereNull('producto_id')
            ->sortBy(fn($d) => $d->tipo . ':' . str_pad((string) $d->{$d->tipoLinea()->columna()}, 12, '0', STR_PAD_LEFT));
        $unidades = 0;

        foreach ($articulos as $detalle) {
            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->ingresar($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad);
            $unidades += (int) $detalle->cantidad;
        }

        $this->stock->recalcularTotales();

        $compra->anotar(
            BitacoraEvento::Finalizada->value,
            "Compra finalizada: {$equipos->count()} equipos y {$unidades} unidades de artículos, Bs " . number_format((float) $compra->total, 2),
        );
        $compra->finalizada_at = now();
        $compra->save();

        return app(PagoProveedorService::class)->registrar($compra, $pagos, true, $user, $clave);
    }

    /**
     * Da de alta un equipo dentro de la compra: crea el producto, su linea y
     * sus fotos. El IMEI es la clave natural (UNIQUE productos_imei_unico): un
     * reintento choca ahi en vez de crear el equipo dos veces.
     *
     * @param  array  $datos  columnas del producto (sin compra: no existe la columna)
     * @param  \Illuminate\Http\UploadedFile[]  $fotos  se guardan con ProductoImagen::guardar()
     */
    public function agregarProducto(Compra $compra, array $datos, array $fotos = []): Producto
    {
        $datos['sucursal_id'] = $datos['sucursal_id'] ?? $compra->sucursal_id;
        $datos['estado'] = $datos['estado'] ?? ProductoEstado::Inventario->value;
        $destino = null;
        $frase = "Producto registrado en la compra #{$compra->id}";

        // En borrador el equipo espera en EnCompra y la linea guarda el estado
        // elegido, que finalizar() le aplica.
        if ($compra->esBorrador()) {
            $destino = $this->validarDestino($datos['estado']);
            $datos['estado'] = ProductoEstado::EnCompra->value;
            $frase .= ' (en borrador: no se vende hasta finalizar la compra)';
        }

        // make() + anotar() + save() y no create(): asi el Observer escribe UNA
        // fila con el estado con el que nace el equipo, la frase y su retrato.
        // Sin esta fila, un equipo creado en Fuera o Roto nacia sin traza y el
        // auditor lo veia como "ultimo historial distinto del estado real".
        $producto = new Producto($datos);
        $producto->anotar($datos['estado'], $frase);
        $producto->save();

        CompraDetalle::create([
            'compra_id' => $compra->id,
            'producto_id' => $producto->id,
            'sucursal_id' => $producto->sucursal_id,
            'cantidad' => 1,
            'costo' => (float) $producto->costo_unidad,
            'subtotal' => (float) $producto->costo_unidad,
            'estado_destino' => $destino,
        ]);

        foreach ($fotos as $foto) {
            ProductoImagen::guardar($producto, $foto);
        }

        $producto->recalcularCosto();
        $compra->recalcularTotal();

        return $producto;
    }

    /**
     * Cambia el estado al que pasara al finalizar un equipo de una compra en
     * borrador (el modal de editar del lote). El estado real sigue en EnCompra.
     */
    public function cambiarEstadoDestino(Producto $producto, string $estado): void
    {
        $detalle = $producto->compraDetalle;

        if ($producto->estado !== ProductoEstado::EnCompra->value || !$detalle || !$detalle->compra->esBorrador()) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} ya no está en una compra en borrador.",
            ]);
        }

        $destino = $this->validarDestino($estado);
        $antes = $detalle->estado_destino;

        if ($antes === $destino) {
            return;
        }

        $detalle->update(['estado_destino' => $destino]);

        // La linea no es Auditable: el hecho se anota en el equipo.
        Bitacora::registrar(
            $producto,
            BitacoraEvento::Editado->value,
            'Estado al finalizar la compra: ' . ProductoEstado::labelDe($destino),
            ['compra_id' => $detalle->compra_id],
            ['estado_destino' => [$antes, $destino]],
        );
    }

    /**
     * Sincroniza la linea de compra con el costo_unidad del equipo, despues de
     * editarlo. Llamarlo siempre que cambie costo_unidad.
     */
    public function actualizarCostoProducto(Producto $producto): void
    {
        $detalle = $producto->compraDetalle;

        if (!$detalle) {
            return;
        }

        $detalle->update([
            'costo' => (float) $producto->costo_unidad,
            'subtotal' => (float) $producto->costo_unidad,
        ]);

        $detalle->compra->recalcularTotal();
    }

    /**
     * Quita un equipo de su compra y lo borra. Solo si nunca se movio: con
     * venta, reparaciones o regalos tiene historia, y las FK en RESTRICT lo
     * impedirian igual; aqui se avisa antes con un mensaje claro.
     */
    public function quitarProducto(Producto $producto): void
    {
        // En reclamo, o reemplazo de un reclamo: es parte de ese tramite.
        if (\App\Models\CompraReclamo::where('producto_id', $producto->id)->orWhere('producto_reemplazo_id', $producto->id)->exists()) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} tiene un reclamo al proveedor (o es el reemplazo de uno): no se puede quitar de la compra.",
            ]);
        }

        // Recibido en permuta: es el pago de una venta. Se va anulando esa venta.
        if ($producto->permuta()->exists()) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} se recibió en permuta: para quitarlo, anula la venta #{$producto->permuta->venta_id}.",
            ]);
        }

        if ($producto->ventaDetalle()->exists() || $producto->reparaciones()->exists() || $producto->regalos()->exists()) {
            throw ValidationException::withMessages([
                'detalles' => "El equipo {$producto->imei} ya tiene ventas, reparaciones o regalos: no se puede quitar de la compra. Dalo de baja si ya no existe.",
            ]);
        }

        $compra = $producto->compra;

        $producto->compraDetalle()->delete();
        // Por Eloquent: el evento deleted borra cada miniatura.
        $producto->imagenes()->get()->each->delete();
        $producto->delete();

        $compra?->recalcularTotal();
    }

    /**
     * Elimina una compra. En borrador se va entera, equipos incluidos: estan en
     * EnCompra y no pudieron venderse, repararse ni regalarse, y su stock nunca
     * entro. Finalizada, solo sin equipos (cada equipo se quita antes, con sus
     * propias reglas); los articulos salen del stock de la sucursal de su
     * linea, y si ya se vendieron, retirar() falla con mensaje y no se borra nada.
     */
    public function eliminar(Compra $compra): void
    {
        // Decision del usuario: con pagos no se elimina; se anulan antes.
        if ($compra->pagos()->exists()) {
            throw ValidationException::withMessages([
                'detalles' => "La compra #{$compra->id} tiene pagos al proveedor registrados: anúlalos antes de eliminarla.",
            ]);
        }

        if ($compra->esBorrador()) {
            foreach ($compra->productos()->orderBy('productos.id')->get() as $producto) {
                $this->quitarProducto($producto);
            }

            $compra->detalles()->delete();
            $compra->delete();

            return;
        }

        if ($compra->detalles()->whereNotNull('producto_id')->exists()) {
            throw ValidationException::withMessages([
                'detalles' => 'La compra tiene equipos: quítalos primero desde el detalle de la compra.',
            ]);
        }

        foreach ($compra->detalles as $detalle) {
            $tipo = $detalle->tipoLinea()->articulo();
            $this->stock->retirar($tipo, $detalle->{$tipo->columna()}, $detalle->sucursal_id, (int) $detalle->cantidad);
            $detalle->delete();
        }

        $this->stock->recalcularTotales();
        $compra->delete();
    }

    // ------------------------------------------------------------------ apoyo

    private function validarDestino(string $estado): string
    {
        if (!in_array($estado, self::estadosDestinoBorrador(), true)) {
            throw ValidationException::withMessages([
                'status' => 'En una compra en borrador el equipo solo puede quedar en Inventario, Fuera o Roto al finalizarla. La reparación se manda después.',
            ]);
        }

        return $estado;
    }

    private function normalizar(array $l): array
    {
        $tipo = LineaTipo::tryFrom((string) ($l['tipo'] ?? ''));
        $id = (int) ($l['id'] ?? 0);
        $cantidad = (int) ($l['cantidad'] ?? 0);
        $costo = round((float) ($l['costo'] ?? 0), 2);

        if (!$tipo || $tipo === LineaTipo::Producto || $id <= 0) {
            throw ValidationException::withMessages(['detalles' => 'Hay una línea de compra inválida. Recarga la pantalla.']);
        }
        if ($cantidad < 1 || $costo < 0) {
            throw ValidationException::withMessages(['detalles' => 'Cada línea necesita al menos una unidad y un costo que no sea negativo.']);
        }

        return ['tipo' => $tipo->value, 'id' => $id, 'cantidad' => $cantidad, 'costo' => $costo];
    }
}
