<?php

namespace App\Models;

use App\Enums\ArticuloTipo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * ############################################################################
 * # MODELO VIRTUAL: LA TABLA `movimientos_stock` NO EXISTE EN LA BASE DE     #
 * # DATOS. No busques su migración, no la hay.                              #
 * ############################################################################
 *
 * El historial de movimientos de un repuesto o un accesorio. No hay kardex: el
 * stock lo escribe StockService sin dejar fila de movimiento propia. Esta clase
 * es SOLO un envoltorio Eloquent sobre el UNION ALL de las fuentes reales:
 *
 *   compras_detalles                  -> Entrada
 *   ventas_detalles                   -> Salida
 *   productos_reparaciones_repuestos  -> Salida   (solo repuestos)
 *   productos_regalos                 -> Salida   (solo accesorios)
 *   stock_bajas                       -> Salida
 *   stock_transferencias              -> Salida en el origen
 *   stock_transferencias              -> Entrada en el destino
 *
 * Las ramas de reparacion y regalo son CONDICIONALES por tipo: si se incluyeran
 * siempre, el WHERE rr.repuesto_id = 5 del historial del ACCESORIO #5 mostraria
 * las piezas del REPUESTO #5.
 *
 * Es de SOLO LECTURA: los hooks saving/deleting lanzan excepción a propósito.
 * Nunca lo consultes fuera de paraArticulo(): sin el fromSub() Eloquent
 * intentará leer una tabla inexistente.
 *
 * ---------------------------------------------------------------------------
 * POR QUÉ $table Y EL ALIAS DEL fromSub SON EL MISMO STRING (no lo cambies):
 * rappasoft/laravel-livewire-tables construye cada SELECT como
 * "{$column->getTable()}.{$column->getField()} as {$field}", y getTable() sale
 * de $this->getBuilder()->getModel()->getTable(). Si el alias del fromSub no es
 * exactamente $table, todas las columnas apuntan a una tabla que no está en el
 * FROM y la query revienta.
 *
 * POR QUÉ $incrementing = false (esto NO es cosmético):
 * el id sintético es 'C-1' / 'V-18' / 'R-14'. Con el default (true + 'int')
 * Eloquent castea 'C-1' a 0 en TODAS las filas, todos los wire:key de la tabla
 * colisionan y Livewire recicla el DOM mal.
 * ---------------------------------------------------------------------------
 */
class MovimientoStock extends Model
{
    /** Alias del fromSub. Tabla VIRTUAL: no existe en la BD. */
    protected $table = 'movimientos_stock';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    /**
     * Sin casts decimal:* a propósito: los importes se formatean con
     * number_format((float) $value, 2), y así los NULL de `precio` /
     * `total_venta` llegan como NULL limpios a los format callbacks.
     */
    protected $casts = [
        'fecha' => 'datetime',
        'referencia_id' => 'integer',
        'cantidad' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function () {
            throw new LogicException('MovimientoStock es de solo lectura: `movimientos_stock` es una tabla virtual (UNION), no existe en la BD.');
        });

        static::deleting(function () {
            throw new LogicException('MovimientoStock es de solo lectura: `movimientos_stock` es una tabla virtual (UNION), no existe en la BD.');
        });
    }

    /**
     * UNION ALL de las ramas, envuelto en un builder Eloquent.
     *
     * ¡OJO! Las ramas de un UNION se emparejan por POSICIÓN, no por nombre, y
     * los nombres de columna resultantes son los de la PRIMERA rama. Son TRECE
     * columnas: si añades o quitas una, hazlo en TODAS y en el MISMO ORDEN. Y un
     * tipo nuevo necesita su case en MovimientoStockTipo (el filtro es whitelist).
     *
     * Sin orderBy aquí: applySorting() de rappasoft ACUMULA orderBy. El orden va
     * en setDefaultSort().
     */
    public static function paraArticulo(ArticuloTipo $tipo, int $id): Builder
    {
        // Columna de FK: sale del enum, nunca de la pantalla.
        $col = $tipo->columna();

        // 1) COMPRAS -> ENTRADA
        // TIMESTAMP(fecha, TIME(created_at)): `fecha` es DATE y a pelo daria
        // 00:00:00, con lo que toda compra apareceria antes que las ventas del
        // mismo dia. Se conserva la fecha de negocio con la hora de registro.
        // Solo compras finalizadas: la linea de un borrador todavia no movio
        // el stock, y el historial mostraria una entrada que no ocurrio.
        $compras = DB::table('compras_detalles as d')
            ->join('compras as c', 'd.compra_id', '=', 'c.id')
            ->whereNotNull('c.finalizada_at')
            ->leftJoin('users as u', 'c.user_id', '=', 'u.id')
            ->leftJoin('proveedores as pv', 'c.proveedor_id', '=', 'pv.id')
            ->leftJoin('sucursales as s', 'd.sucursal_id', '=', 's.id')
            ->where("d.{$col}", $id)
            ->selectRaw("
                CONCAT('C-', d.id)                            as id,
                'Compra'                                      as tipo,
                'Entrada'                                     as direccion,
                c.id                                          as referencia_id,
                TIMESTAMP(c.fecha, TIME(d.created_at))        as fecha,
                CONCAT('Compra a ', COALESCE(pv.nombre, 'proveedor'))
                                                              as detalle,
                d.cantidad                                    as cantidad,
                d.costo                                       as costo,
                NULL                                          as precio,
                d.subtotal                                    as subtotal_costo,
                NULL                                          as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 2) VENTAS -> SALIDA
        $ventas = DB::table('ventas_detalles as d')
            ->join('ventas as v', 'd.venta_id', '=', 'v.id')
            ->leftJoin('users as u', 'v.user_id', '=', 'u.id')
            // Por d.sucursal_id y NO por v.sucursal_id: el dato de stock es el
            // de la LINEA. Anularla devuelve el stock ahi.
            ->leftJoin('sucursales as s', 'd.sucursal_id', '=', 's.id')
            // La ficha del cliente manda sobre el texto congelado.
            ->leftJoin('clientes as cl', 'v.cliente_id', '=', 'cl.id')
            ->where("d.{$col}", $id)
            // Sin las lineas de regalo: la salida de ese stock ya la muestra la
            // rama de regalos, y la linea de venta va a precio y costo 0.
            ->whereNull('d.producto_regalo_id')
            ->selectRaw("
                CONCAT('V-', d.id)                            as id,
                'Venta'                                       as tipo,
                'Salida'                                      as direccion,
                v.id                                          as referencia_id,
                d.created_at                                  as fecha,
                CONCAT(
                    COALESCE(cl.nombre, v.cliente, 'Sin cliente'),
                    CASE WHEN d.producto_reparacion_repuesto_id IS NULL THEN ''
                         ELSE ' · cobrado con la venta del equipo (ya descontado en la reparación)' END
                )                                             as detalle,
                -- Un repuesto cobrado junto al telefono salio del almacen en la
                -- reparacion, no aqui. Se muestra el dinero pero NO el
                -- movimiento, o el saldo contaria la salida dos veces.
                CASE WHEN d.producto_reparacion_repuesto_id IS NULL THEN d.cantidad ELSE 0 END
                                                              as cantidad,
                d.costo                                       as costo,
                d.precio                                      as precio,
                d.subtotal_costo                              as subtotal_costo,
                d.subtotal                                    as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 5) BAJAS -> SALIDA, con el costo congelado (reporte de perdidas).
        $bajas = DB::table('stock_bajas as b')
            ->leftJoin('users as u', 'b.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 'b.sucursal_id', '=', 's.id')
            ->where("b.{$col}", $id)
            ->selectRaw("
                CONCAT('B-', b.id)                            as id,
                'Baja'                                        as tipo,
                'Salida'                                      as direccion,
                b.id                                          as referencia_id,
                b.created_at                                  as fecha,
                CONCAT('Baja: ', b.motivo, COALESCE(CONCAT(' - ', b.nota), ''))
                                                              as detalle,
                b.cantidad                                    as cantidad,
                b.costo                                       as costo,
                NULL                                          as precio,
                b.cantidad * b.costo                          as subtotal_costo,
                NULL                                          as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 6) TRANSFERENCIA -> SALIDA en el origen. Dos ramas porque una
        // transferencia es NETA CERO: con una sola fila el saldo dejaria de
        // cuadrar con el total y el aviso de "el balance calculado no coincide"
        // se encenderia por diseño. El sufijo -S / -E del id evita que las dos
        // filas compartan wire:key.
        $transferenciaSalida = DB::table('stock_transferencias as t')
            ->leftJoin('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 't.sucursal_origen_id', '=', 's.id')
            ->leftJoin('sucursales as sd', 't.sucursal_destino_id', '=', 'sd.id')
            ->where("t.{$col}", $id)
            ->selectRaw("
                CONCAT('T-', t.id, '-S')                      as id,
                'Transferencia'                               as tipo,
                'Salida'                                      as direccion,
                t.id                                          as referencia_id,
                t.created_at                                  as fecha,
                CONCAT('Transferencia hacia ', COALESCE(sd.nombre, 'sin sucursal'))
                                                              as detalle,
                t.cantidad                                    as cantidad,
                NULL                                          as costo,
                NULL                                          as precio,
                NULL                                          as subtotal_costo,
                NULL                                          as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        // 7) TRANSFERENCIA -> ENTRADA en el destino.
        $transferenciaEntrada = DB::table('stock_transferencias as t')
            ->leftJoin('users as u', 't.user_id', '=', 'u.id')
            ->leftJoin('sucursales as s', 't.sucursal_destino_id', '=', 's.id')
            ->leftJoin('sucursales as so', 't.sucursal_origen_id', '=', 'so.id')
            ->where("t.{$col}", $id)
            ->selectRaw("
                CONCAT('T-', t.id, '-E')                      as id,
                'Transferencia'                               as tipo,
                'Entrada'                                     as direccion,
                t.id                                          as referencia_id,
                t.created_at                                  as fecha,
                CONCAT('Transferencia desde ', COALESCE(so.nombre, 'sin sucursal'))
                                                              as detalle,
                t.cantidad                                    as cantidad,
                NULL                                          as costo,
                NULL                                          as precio,
                NULL                                          as subtotal_costo,
                NULL                                          as total_venta,
                u.name                                        as usuario,
                s.nombre                                      as sucursal
            ");

        $union = $compras->unionAll($ventas);

        if ($tipo === ArticuloTipo::Repuesto) {
            // 3) REPARACIONES -> SALIDA (solo repuestos). productos_reparaciones
            // no tiene user_id: `usuario` va NULL a propósito.
            $union->unionAll(DB::table('productos_reparaciones_repuestos as rr')
                ->join('productos_reparaciones as r', 'rr.producto_reparacion_id', '=', 'r.id')
                ->join('productos as p', 'r.producto_id', '=', 'p.id')
                ->leftJoin('tecnicos as tc', 'r.tecnico_id', '=', 'tc.id')
                // La sucursal CONGELADA de la linea: de ahi salio la pieza.
                ->leftJoin('sucursales as s', 'rr.sucursal_id', '=', 's.id')
                ->where('rr.repuesto_id', $id)
                ->selectRaw("
                    CONCAT('R-', rr.id)                       as id,
                    'Reparacion'                              as tipo,
                    'Salida'                                  as direccion,
                    r.id                                      as referencia_id,
                    rr.created_at                             as fecha,
                    CONCAT('IMEI ', p.imei, COALESCE(CONCAT(' - ', tc.nombre), ''))
                                                              as detalle,
                    rr.cantidad                               as cantidad,
                    rr.costo                                  as costo,
                    NULL                                      as precio,
                    rr.subtotal_costo                         as subtotal_costo,
                    NULL                                      as total_venta,
                    NULL                                      as usuario,
                    s.nombre                                  as sucursal
                "));
        } else {
            // 4) REGALOS -> SALIDA (solo accesorios): salen con el equipo y su
            // costo va al costo del equipo.
            $union->unionAll(DB::table('productos_regalos as g')
                ->join('productos as p', 'g.producto_id', '=', 'p.id')
                ->leftJoin('users as u', 'g.user_id', '=', 'u.id')
                ->leftJoin('sucursales as s', 'g.sucursal_id', '=', 's.id')
                ->where('g.accesorio_id', $id)
                ->selectRaw("
                    CONCAT('G-', g.id)                        as id,
                    'Regalo'                                  as tipo,
                    'Salida'                                  as direccion,
                    p.id                                      as referencia_id,
                    g.created_at                              as fecha,
                    CONCAT('Regalo con el IMEI ', p.imei)     as detalle,
                    g.cantidad                                as cantidad,
                    g.costo                                   as costo,
                    NULL                                      as precio,
                    g.subtotal_costo                          as subtotal_costo,
                    NULL                                      as total_venta,
                    u.name                                    as usuario,
                    s.nombre                                  as sucursal
                "));
        }

        $union->unionAll($bajas)->unionAll($transferenciaSalida)->unionAll($transferenciaEntrada);

        // El alias DEBE ser 'movimientos_stock' (= $table). Ver docblock.
        return static::query()->fromSub($union, 'movimientos_stock');
    }
}
