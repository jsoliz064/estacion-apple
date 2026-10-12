<?php

namespace App\Console\Commands;

use App\Enums\ArticuloTipo;
use App\Enums\ProductoEstado;
use App\Models\Producto;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Detector de deriva del inventario. SOLO LECTURA.
 *
 * La deriva se mide, no se asume. Los indices unicos y los CHECK cierran la
 * puerta de la aplicacion, pero nada impide un UPDATE a mano en la base, y los
 * totales cacheados (stock, costo de regalos, totales de venta y compra) solo
 * cuadran si todo pasa por sus servicios.
 *
 * No arregla nada a proposito. Cada hallazgo necesita una decision de negocio
 * (¿esa venta se cobro?), y un --fix que adivine seria peor que el problema.
 */
class AuditarProductos extends Command
{
    protected $signature = 'productos:auditar';

    protected $description = 'Busca inconsistencias entre productos, ventas, compras, historial y stock (solo lectura)';

    /** Hallazgos acumulados, para decidir el codigo de salida al final. */
    private int $problemas = 0;

    public function handle(): int
    {
        // La instancia se reutiliza si se llama dos veces en el mismo proceso
        // (Artisan::call): sin esto, la segunda arrastraba los hallazgos de la primera.
        $this->problemas = 0;
        $this->newLine();
        $this->line('<options=bold>Auditoria de inventario</> — solo lectura');

        $this->vendidosSinVenta();
        $this->enVentaSinEstarVendidos();
        $this->ventasSinDetalles();
        $this->regalosVentaDescuadrados();
        $this->comprasSinDetalles();
        $this->enCompraDescuadrados();
        $this->productosSinCompra();
        $this->imeiDuplicados();
        $this->historialDesalineado();
        $this->dadosDeBajaVendidos();
        foreach (ArticuloTipo::cases() as $tipo) {
            $this->stockDescuadrado($tipo);
        }
        $this->costoRegalosDescuadrado();
        $this->costoTotalDescuadrado();
        $this->costoCompraDescuadrado();
        $this->costoUsdDescuadrado();
        $this->totalVentaDescuadrado();
        $this->totalCompraDescuadrado();
        $this->pagadoDescuadrado();
        $this->pagadaAtIncoherente();
        $this->equiposConCobroIncoherente();
        $this->creditoSinCliente();
        $this->reservasDescuadradas();
        $this->reservasConcretadasSinSena();
        $this->permutasDescuadradas();
        $this->pagadoCompraDescuadrado();
        $this->pagadaAtCompraIncoherente();
        $this->reclamosDescuadrados();
        $this->devueltosConCosto();
        $this->comisionesFaltantes();
        $this->comisionesSobrantes();
        $this->comisionesGanadaIncoherente();
        $this->comisionesMontoDescuadrado();
        $this->liquidacionesDescuadradas();

        $this->newLine();

        if ($this->problemas === 0) {
            $this->info('Sin inconsistencias.');

            return self::SUCCESS;
        }

        $this->error("{$this->problemas} comprobacion(es) con hallazgos.");

        // Codigo != 0 para que sirva en un cron o en un hook sin leer la salida.
        return self::FAILURE;
    }

    /** El telefono marcado como vendido (o a credito) y la linea de venta en ninguna parte. */
    private function vendidosSinVenta(): void
    {
        $filas = DB::table('productos as p')
            ->whereIn('p.estado', ProductoEstado::vendidos())
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('ventas_detalles as vd')
                ->whereColumn('vd.producto_id', 'p.id'))
            ->select('p.id', 'p.imei', 'p.estado', 'p.updated_at')
            ->orderByDesc('p.updated_at')
            ->get();

        $this->reportar('Productos vendidos sin linea de venta', $filas, ['id', 'imei', 'estado', 'updated_at']);
    }

    /** El reverso: la venta existe pero el telefono sigue disponible y puede volver a venderse. */
    private function enVentaSinEstarVendidos(): void
    {
        $filas = DB::table('ventas_detalles as vd')
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->whereNotIn('p.estado', ProductoEstado::vendidos())
            ->select('p.id', 'p.imei', 'p.estado', 'vd.venta_id')
            ->get();

        $this->reportar('Productos en una venta sin estar vendidos', $filas, ['id', 'imei', 'estado', 'venta_id']);
    }

    /**
     * Los regalos de un equipo vendido viajan como lineas de su venta
     * (VentaService::registrarRegalos). Descuadre: una linea de regalo sin su
     * equipo en esa venta o distinta de su regalo (otro accesorio, otra
     * cantidad, otro equipo), o un regalo de un equipo vendido sin su linea.
     */
    private function regalosVentaDescuadrados(): void
    {
        $lineas = DB::table('ventas_detalles as d')
            ->leftJoin('productos_regalos as r', 'r.id', '=', 'd.producto_regalo_id')
            ->leftJoin('ventas_detalles as e', fn($j) => $j->on('e.venta_id', '=', 'd.venta_id')->on('e.producto_id', '=', 'd.producto_asociado_id'))
            ->whereNotNull('d.producto_regalo_id')
            ->where(fn($q) => $q->whereNull('e.id')
                ->orWhereColumn('r.producto_id', '!=', 'd.producto_asociado_id')
                ->orWhereColumn('r.accesorio_id', '!=', 'd.accesorio_id')
                ->orWhereColumn('r.cantidad', '!=', 'd.cantidad'))
            ->select('d.venta_id', 'd.producto_asociado_id as producto_id', 'd.producto_regalo_id as regalo_id', DB::raw("'linea sin su equipo o distinta del regalo' as motivo"))
            ->get();

        $sinLinea = DB::table('productos_regalos as r')
            ->join('ventas_detalles as e', 'e.producto_id', '=', 'r.producto_id')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('ventas_detalles as d')->whereColumn('d.producto_regalo_id', 'r.id'))
            ->select('e.venta_id', 'r.producto_id', 'r.id as regalo_id', DB::raw("'regalo de un equipo vendido sin su linea' as motivo"))
            ->get();

        $this->reportar('Regalos de equipos vendidos descuadrados con su venta', $lineas->merge($sinLinea), ['venta_id', 'producto_id', 'regalo_id', 'motivo']);
    }

    /** Una cabecera sin lineas es lo que un usuario describe como "la venta no se guardo". */
    private function ventasSinDetalles(): void
    {
        $filas = DB::table('ventas as v')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('ventas_detalles as vd')
                ->whereColumn('vd.venta_id', 'v.id'))
            ->select('v.id', 'v.cliente', 'v.total', 'v.created_at')
            ->orderByDesc('v.id')
            ->get();

        $this->reportar('Ventas sin ninguna linea', $filas, ['id', 'cliente', 'total', 'created_at']);
    }

    /**
     * Una compra FINALIZADA sin lineas: finalizar() exige al menos una, asi que
     * solo queda vacia si alguien le quito todo despues. Un borrador vacio es
     * normal (se crea la cabecera y despues se carga).
     */
    private function comprasSinDetalles(): void
    {
        $filas = DB::table('compras as c')
            ->whereNotNull('c.finalizada_at')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('compras_detalles as cd')
                ->whereColumn('cd.compra_id', 'c.id'))
            ->select('c.id', 'c.fecha', 'c.created_at')
            ->get();

        $this->reportar('Compras finalizadas sin ninguna linea', $filas, ['id', 'fecha', 'created_at']);
    }

    /**
     * EnCompra <=> su compra sigue en borrador. Y el estado_destino solo vive
     * mientras tanto: finalizar() lo aplica y lo limpia.
     */
    private function enCompraDescuadrados(): void
    {
        $filas = DB::table('productos as p')
            ->join('compras_detalles as cd', 'cd.producto_id', '=', 'p.id')
            ->join('compras as c', 'c.id', '=', 'cd.compra_id')
            ->where(fn($q) => $q
                ->where(fn($q) => $q->where('p.estado', ProductoEstado::EnCompra->value)->whereNotNull('c.finalizada_at'))
                ->orWhere(fn($q) => $q->where('p.estado', '!=', ProductoEstado::EnCompra->value)->whereNull('c.finalizada_at'))
                ->orWhere(fn($q) => $q->whereNotNull('cd.estado_destino')->whereNotNull('c.finalizada_at')))
            ->select('p.id', 'p.imei', 'p.estado', 'c.id as compra_id', 'c.finalizada_at', 'cd.estado_destino')
            ->get();

        // Un EnCompra sin linea de compra no tiene compra que lo libere.
        $huerfanos = DB::table('productos as p')
            ->where('p.estado', ProductoEstado::EnCompra->value)
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('compras_detalles as cd')->whereColumn('cd.producto_id', 'p.id'))
            ->select('p.id', 'p.imei', 'p.estado', DB::raw('NULL as compra_id'), DB::raw('NULL as finalizada_at'), DB::raw('NULL as estado_destino'))
            ->get();

        $this->reportar('Equipos En compra que no coinciden con el borrador de su compra', $filas->merge($huerfanos), ['id', 'imei', 'estado', 'compra_id', 'finalizada_at', 'estado_destino']);
    }

    /**
     * Equipos sin linea de compra. Hoy todo equipo entra por una compra; cuando
     * exista la permuta (etapa 5) los equipos recibidos no tendran linea y esta
     * comprobacion tendra que excluirlos.
     */
    private function productosSinCompra(): void
    {
        $filas = DB::table('productos as p')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('compras_detalles as cd')
                ->whereColumn('cd.producto_id', 'p.id'))
            // Los recibidos en permuta no vienen de una compra: su origen es el pago.
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))
                ->from('ventas_pagos as vp')
                ->whereColumn('vp.producto_id', 'p.id'))
            ->select('p.id', 'p.imei', 'p.created_at')
            ->get();

        $this->reportar('Productos sin linea de compra ni permuta', $filas, ['id', 'imei', 'created_at']);
    }

    /** Lo que impide el indice unico de productos.imei. */
    private function imeiDuplicados(): void
    {
        $filas = DB::table('productos')
            ->selectRaw('imei, COUNT(*) as veces, GROUP_CONCAT(id) as ids')
            ->groupBy('imei')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        $this->reportar('IMEI duplicados', $filas, ['imei', 'veces', 'ids']);
    }

    /**
     * Compara el estado real con el de la ultima fila de bitacora que lleva un
     * ESTADO como evento. Las demas ('editado', 'garantia', 'cobro', 'baja'...)
     * no dicen en que estado quedo el telefono. Un cambio de estado que se
     * saltara EstadoProductoService deja la ultima fila con el estado anterior.
     */
    private function historialDesalineado(): void
    {
        $estados = ProductoEstado::values();
        $huecos = implode(',', array_fill(0, count($estados), '?'));

        $filas = DB::select(
            "SELECT p.id, p.imei, p.estado AS estado_real, b.evento AS estado_historial, b.created_at
               FROM productos p
               JOIN bitacoras b
                 ON b.id = (SELECT b2.id
                              FROM bitacoras b2
                             WHERE b2.auditable_type = ?
                               AND b2.auditable_id = p.id
                               AND b2.evento IN ($huecos)
                             ORDER BY b2.id DESC
                             LIMIT 1)
              WHERE b.evento <> p.estado",
            [(new Producto)->getMorphClass(), ...$estados],
        );

        $this->reportar(
            'Ultima fila de historial distinta del estado real',
            collect($filas),
            ['id', 'imei', 'estado_real', 'estado_historial', 'created_at'],
        );
    }

    /** Un equipo dado de baja no puede estar vendido: BajaService lo impide. */
    private function dadosDeBajaVendidos(): void
    {
        $filas = DB::table('productos')
            ->whereNotNull('dado_de_baja_at')
            ->whereIn('estado', ProductoEstado::vendidos())
            ->select('id', 'imei', 'estado', 'dado_de_baja_at')
            ->get();

        $this->reportar('Productos dados de baja en estado vendido', $filas, ['id', 'imei', 'estado', 'dado_de_baja_at']);
    }

    /**
     * El total cacheado (repuestos.cantidad / accesorios.cantidad) contra
     * stock_sucursales. recalcularTotales() es auto-sanante, asi que una fila
     * aqui significa que algo escribio el total por fuera de StockService.
     */
    private function stockDescuadrado(ArticuloTipo $tipo): void
    {
        $tabla = $tipo->tabla();
        $col = $tipo->columna();

        $filas = DB::select(
            "SELECT a.id, a.nombre, a.cantidad AS total_cacheado,
                    COALESCE(SUM(ss.cantidad), 0) AS suma_sucursales
               FROM {$tabla} a
          LEFT JOIN stock_sucursales ss ON ss.{$col} = a.id
           GROUP BY a.id, a.nombre, a.cantidad
             HAVING a.cantidad <> COALESCE(SUM(ss.cantidad), 0)"
        );

        $this->reportar(
            "{$tabla}.cantidad distinto de la suma por sucursal",
            collect($filas),
            ['id', 'nombre', 'total_cacheado', 'suma_sucursales'],
        );
    }

    private function costoRegalosDescuadrado(): void
    {
        $filas = DB::select(
            'SELECT p.id, p.imei, p.costo_regalos, COALESCE(SUM(g.subtotal_costo), 0) AS suma_regalos
               FROM productos p
          LEFT JOIN productos_regalos g ON g.producto_id = p.id
           GROUP BY p.id, p.imei, p.costo_regalos
             HAVING ABS(p.costo_regalos - COALESCE(SUM(g.subtotal_costo), 0)) > 0.009'
        );

        $this->reportar('costo_regalos distinto de la suma de regalos', collect($filas), ['id', 'imei', 'costo_regalos', 'suma_regalos']);
    }

    private function costoTotalDescuadrado(): void
    {
        $filas = DB::table('productos')
            ->whereRaw('ABS(costo_total - (costo_unidad + costo_regalos + costo_reparacion)) > 0.009')
            ->select('id', 'imei', 'costo_unidad', 'costo_regalos', 'costo_reparacion', 'costo_total')
            ->get();

        $this->reportar('costo_total distinto de la suma de sus partes', $filas, ['id', 'imei', 'costo_unidad', 'costo_regalos', 'costo_reparacion', 'costo_total']);
    }

    /** La linea de compra del equipo y su costo_unidad: solo los escribe CompraService. */
    private function costoCompraDescuadrado(): void
    {
        $filas = DB::table('compras_detalles as cd')
            ->join('productos as p', 'p.id', '=', 'cd.producto_id')
            ->whereRaw('ABS(cd.costo - p.costo_unidad) > 0.009')
            ->select('p.id', 'p.imei', 'p.costo_unidad', 'cd.costo', 'cd.compra_id')
            ->get();

        $this->reportar('Costo de la linea de compra distinto del costo_unidad', $filas, ['id', 'imei', 'costo_unidad', 'costo', 'compra_id']);
    }

    /** Un costo cargado en USD: costo_unidad tiene que ser USD x TC (lo deriva Producto). */
    private function costoUsdDescuadrado(): void
    {
        $filas = DB::table('productos')
            ->where('costo_moneda', 'USD')
            ->whereRaw('ABS(costo_unidad - ROUND(costo_moneda_monto * costo_tipo_cambio, 2)) > 0.009')
            ->select('id', 'imei', 'costo_unidad', 'costo_moneda_monto', 'costo_tipo_cambio')
            ->get();

        $this->reportar('Costo en USD que no cuadra con su tipo de cambio', $filas, ['id', 'imei', 'costo_unidad', 'costo_moneda_monto', 'costo_tipo_cambio']);
    }

    private function totalVentaDescuadrado(): void
    {
        $filas = DB::select(
            'SELECT v.id, v.total, COALESCE(SUM(d.subtotal), 0) - v.descuento + v.mano_obra AS calculado
               FROM ventas v
          LEFT JOIN ventas_detalles d ON d.venta_id = v.id
           GROUP BY v.id, v.total, v.descuento, v.mano_obra
             HAVING ABS(v.total - (COALESCE(SUM(d.subtotal), 0) - v.descuento + v.mano_obra)) > 0.009'
        );

        $this->reportar('ventas.total distinto de sus lineas', collect($filas), ['id', 'total', 'calculado']);
    }

    private function totalCompraDescuadrado(): void
    {
        $filas = DB::select(
            'SELECT c.id, c.total, COALESCE(SUM(d.subtotal), 0) AS calculado
               FROM compras c
          LEFT JOIN compras_detalles d ON d.compra_id = c.id
           GROUP BY c.id, c.total
             HAVING ABS(c.total - COALESCE(SUM(d.subtotal), 0)) > 0.009'
        );

        $this->reportar('compras.total distinto de sus lineas', collect($filas), ['id', 'total', 'calculado']);
    }

    /** ventas.pagado es la suma cacheada de ventas_pagos (PagoService::sincronizar). */
    private function pagadoDescuadrado(): void
    {
        $filas = DB::select(
            'SELECT v.id, v.pagado, COALESCE(SUM(p.monto), 0) AS calculado
               FROM ventas v
          LEFT JOIN ventas_pagos p ON p.venta_id = v.id
           GROUP BY v.id, v.pagado
             HAVING ABS(v.pagado - COALESCE(SUM(p.monto), 0)) > 0.009'
        );

        $this->reportar('ventas.pagado distinto de sus pagos', collect($filas), ['id', 'pagado', 'calculado']);
    }

    /** pagada_at existe si y solo si no queda saldo. */
    private function pagadaAtIncoherente(): void
    {
        $filas = DB::table('ventas')
            ->where(fn($q) => $q
                ->where(fn($w) => $w->where('saldo', '>', 0)->whereNotNull('pagada_at'))
                ->orWhere(fn($w) => $w->where('saldo', '<=', 0)->whereNull('pagada_at')))
            ->select('id', 'total', 'pagado', 'saldo', 'pagada_at')
            ->get();

        $this->reportar('pagada_at en desacuerdo con el saldo', $filas, ['id', 'total', 'pagado', 'saldo', 'pagada_at']);
    }

    /** Un equipo de una venta con saldo esta en Credito; de una venta pagada, en Vendido. */
    private function equiposConCobroIncoherente(): void
    {
        $filas = DB::table('ventas_detalles as vd')
            ->join('ventas as v', 'v.id', '=', 'vd.venta_id')
            ->join('productos as p', 'p.id', '=', 'vd.producto_id')
            ->where(fn($q) => $q
                ->where(fn($w) => $w->where('v.saldo', '>', 0)->where('p.estado', '!=', ProductoEstado::Credito->value))
                ->orWhere(fn($w) => $w->where('v.saldo', '<=', 0)->where('p.estado', '!=', ProductoEstado::Vendido->value)))
            ->select('p.id', 'p.imei', 'p.estado', 'vd.venta_id', 'v.saldo')
            ->get();

        $this->reportar('Equipos cuyo estado no coincide con el cobro de su venta', $filas, ['id', 'imei', 'estado', 'venta_id', 'saldo']);
    }

    /** Una deuda tiene que estar atada a una ficha de cliente. */
    private function creditoSinCliente(): void
    {
        $filas = DB::table('ventas')->where('saldo', '>', 0)->whereNull('cliente_id')
            ->select('id', 'total', 'saldo', 'cliente')
            ->get();

        $this->reportar('Ventas a credito sin cliente', $filas, ['id', 'total', 'saldo', 'cliente']);
    }

    /** Equipo en Reserva sin reserva activa, o reserva activa con el equipo en otro estado. */
    private function reservasDescuadradas(): void
    {
        $sinReserva = DB::table('productos as p')
            ->where('p.estado', ProductoEstado::Reserva->value)
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('reservas as r')
                ->whereColumn('r.producto_id', 'p.id')->where('r.estado', 'Activa'))
            ->select('p.id', 'p.imei', 'p.estado', DB::raw('NULL as reserva_id'))
            ->get();

        $otroEstado = DB::table('reservas as r')
            ->join('productos as p', 'p.id', '=', 'r.producto_id')
            ->where('r.estado', 'Activa')
            ->where('p.estado', '!=', ProductoEstado::Reserva->value)
            ->select('p.id', 'p.imei', 'p.estado', 'r.id as reserva_id')
            ->get();

        $this->reportar('Equipos en Reserva sin reserva activa, o al reves', $sinReserva->merge($otroEstado), ['id', 'imei', 'estado', 'reserva_id']);
    }

    /** Una reserva concretada deja su seña como pago de la venta. */
    private function reservasConcretadasSinSena(): void
    {
        $filas = DB::table('reservas as r')
            ->where('r.estado', 'Concretada')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('ventas_pagos as vp')
                ->whereColumn('vp.venta_id', 'r.venta_id')->where('vp.momento', 'Sena')
                ->whereColumn('vp.monto', 'r.sena'))
            ->select('r.id', 'r.venta_id', 'r.sena')
            ->get();

        $this->reportar('Reservas concretadas sin la seña en su venta', $filas, ['id', 'venta_id', 'sena']);
    }

    /** El pago de permuta y el costo del equipo recibido son la misma cifra. */
    private function permutasDescuadradas(): void
    {
        $filas = DB::table('ventas_pagos as vp')
            ->join('metodos_pago as m', 'm.id', '=', 'vp.metodo_pago_id')
            ->leftJoin('productos as p', 'p.id', '=', 'vp.producto_id')
            ->where(fn($q) => $q
                ->where(fn($w) => $w->where('m.sistema', true)->whereNull('vp.producto_id'))
                ->orWhere(fn($w) => $w->whereNotNull('vp.producto_id')->whereRaw('ABS(vp.monto - p.costo_unidad) > 0.009')))
            ->select('vp.id', 'vp.venta_id', 'vp.monto', 'vp.producto_id', 'p.costo_unidad')
            ->get();

        $this->reportar('Pagos de permuta sin equipo o con otro costo', $filas, ['id', 'venta_id', 'monto', 'producto_id', 'costo_unidad']);
    }

    /** compras.pagado es la suma cacheada de compras_pagos (PagoProveedorService). */
    private function pagadoCompraDescuadrado(): void
    {
        $filas = DB::select(
            'SELECT c.id, c.pagado, COALESCE(SUM(p.monto), 0) AS calculado
               FROM compras c
          LEFT JOIN compras_pagos p ON p.compra_id = c.id
           GROUP BY c.id, c.pagado
             HAVING ABS(c.pagado - COALESCE(SUM(p.monto), 0)) > 0.009'
        );

        $this->reportar('compras.pagado distinto de sus pagos', collect($filas), ['id', 'pagado', 'calculado']);
    }

    /** Una compra con total queda pagada (pagada_at) si y solo si no tiene saldo. */
    private function pagadaAtCompraIncoherente(): void
    {
        $filas = DB::table('compras')
            ->where(fn($q) => $q
                ->where(fn($w) => $w->where('saldo', '>', 0)->whereNotNull('pagada_at'))
                ->orWhere(fn($w) => $w->where('total', '>', 0)->where('saldo', '<=', 0)->whereNull('pagada_at'))
                ->orWhere(fn($w) => $w->where('total', '<=', 0)->whereNotNull('pagada_at')))
            ->select('id', 'total', 'pagado', 'saldo', 'pagada_at')
            ->get();

        $this->reportar('Compras con pagada_at en desacuerdo con el saldo', $filas, ['id', 'total', 'pagado', 'saldo', 'pagada_at']);
    }

    /** Equipo en Reclamo (sin baja) sin reclamo abierto, o reclamo abierto con el equipo en otro estado. */
    private function reclamosDescuadrados(): void
    {
        $sinReclamo = DB::table('productos as p')
            ->where('p.estado', ProductoEstado::Reclamo->value)
            ->whereNull('p.dado_de_baja_at')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('compras_reclamos as r')
                ->whereColumn('r.producto_id', 'p.id')->where('r.estado', 'Abierto'))
            ->select('p.id', 'p.imei', 'p.estado', DB::raw('NULL as reclamo_id'))
            ->get();

        $otroEstado = DB::table('compras_reclamos as r')
            ->join('productos as p', 'p.id', '=', 'r.producto_id')
            ->where('r.estado', 'Abierto')
            ->where('p.estado', '!=', ProductoEstado::Reclamo->value)
            ->select('p.id', 'p.imei', 'p.estado', 'r.id as reclamo_id')
            ->get();

        $this->reportar('Equipos en Reclamo sin reclamo abierto, o al reves', $sinReclamo->merge($otroEstado), ['id', 'imei', 'estado', 'reclamo_id']);
    }

    /** El devuelto al proveedor no cuesta nada: ni el equipo ni su linea de compra. */
    private function devueltosConCosto(): void
    {
        $filas = DB::table('productos as p')
            ->leftJoin('compras_detalles as cd', 'cd.producto_id', '=', 'p.id')
            ->where('p.motivo_baja', 'Devolucion')
            ->where(fn($q) => $q->where('p.costo_unidad', '!=', 0)->orWhere('cd.costo', '!=', 0))
            ->select('p.id', 'p.imei', 'p.costo_unidad', 'cd.costo')
            ->get();

        $this->reportar('Devueltos al proveedor con costo', $filas, ['id', 'imei', 'costo_unidad', 'costo']);
    }

    /** Toda venta con vendedor y toda reparacion con tecnico (que no sea de garantia) tiene su comision. */
    private function comisionesFaltantes(): void
    {
        $ventas = DB::table('ventas as v')
            ->whereNotNull('v.user_id')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('comisiones as c')->whereColumn('c.venta_id', 'v.id'))
            ->select(DB::raw("'venta' as origen"), 'v.id')
            ->get();

        $reparaciones = DB::table('productos_reparaciones as r')
            ->whereNotNull('r.tecnico_id')
            ->where('r.tipo', '!=', 'Garantia')
            ->whereNotExists(fn($q) => $q->select(DB::raw(1))->from('comisiones as c')->whereColumn('c.producto_reparacion_id', 'r.id'))
            ->select(DB::raw("'reparacion' as origen"), 'r.id')
            ->get();

        $this->reportar('Ventas o reparaciones sin su comision', $ventas->merge($reparaciones), ['origen', 'id']);
    }

    /**
     * Comisiones no liquidadas que no deberian existir: de una reparacion de
     * garantia o sin tecnico, o sin documento (la venta se anulo y la comision
     * no se fue con ella). La liquidada si sobrevive a su venta: se pago.
     */
    private function comisionesSobrantes(): void
    {
        $filas = DB::table('comisiones as c')
            ->leftJoin('productos_reparaciones as r', 'r.id', '=', 'c.producto_reparacion_id')
            ->whereNull('c.liquidacion_id')
            ->where(fn($q) => $q
                ->where(fn($w) => $w->whereNull('c.venta_id')->whereNull('c.producto_reparacion_id'))
                ->orWhere('r.tipo', 'Garantia')
                ->orWhere(fn($w) => $w->whereNotNull('c.producto_reparacion_id')->whereNull('r.tecnico_id')))
            ->select('c.id', 'c.referencia', 'r.tipo')
            ->get();

        $this->reportar('Comisiones que no deberian existir', $filas, ['id', 'referencia', 'tipo']);
    }

    /** La no liquidada se gana con su documento: la venta pagada, la reparacion terminada. */
    private function comisionesGanadaIncoherente(): void
    {
        $ventas = DB::table('comisiones as c')
            ->join('ventas as v', 'v.id', '=', 'c.venta_id')
            ->whereNull('c.liquidacion_id')
            ->where(fn($q) => $q
                ->where(fn($w) => $w->whereNull('c.ganada_at')->whereNotNull('v.pagada_at'))
                ->orWhere(fn($w) => $w->whereNotNull('c.ganada_at')->whereNull('v.pagada_at'))
                ->orWhereColumn('c.ganada_at', '!=', 'v.pagada_at'))
            ->select('c.id', 'c.referencia', 'c.ganada_at', 'v.pagada_at as documento')
            ->get();

        $reparaciones = DB::table('comisiones as c')
            ->join('productos_reparaciones as r', 'r.id', '=', 'c.producto_reparacion_id')
            ->whereNull('c.liquidacion_id')
            ->where(fn($q) => $q
                ->where(fn($w) => $w->whereNull('c.ganada_at')->where('r.estado', 'Terminado'))
                ->orWhere(fn($w) => $w->whereNotNull('c.ganada_at')->where('r.estado', '!=', 'Terminado')))
            ->select('c.id', 'c.referencia', 'c.ganada_at', 'r.estado as documento')
            ->get();

        $this->reportar('Comisiones ganadas en desacuerdo con su venta o reparacion', $ventas->merge($reparaciones), ['id', 'referencia', 'ganada_at', 'documento']);
    }

    /**
     * La no liquidada sigue a su documento: base = ganancia de la venta o mano
     * de obra, y monto = max(0, base) x %. La liquidada queda congelada.
     */
    private function comisionesMontoDescuadrado(): void
    {
        $filas = DB::select(
            'SELECT c.id, c.referencia, c.base, c.porcentaje, c.monto,
                    COALESCE(v.total - v.costo_total, r.costo) AS base_documento
               FROM comisiones c
          LEFT JOIN ventas v ON v.id = c.venta_id
          LEFT JOIN productos_reparaciones r ON r.id = c.producto_reparacion_id
              WHERE ABS(c.monto - ROUND(GREATEST(0, c.base) * c.porcentaje / 100, 2)) > 0.011
                 OR (c.liquidacion_id IS NULL
                     AND (c.venta_id IS NOT NULL OR c.producto_reparacion_id IS NOT NULL)
                     AND ABS(c.base - COALESCE(v.total - v.costo_total, r.costo)) > 0.009)'
        );

        $this->reportar('Comisiones con base o monto descuadrado', collect($filas), ['id', 'referencia', 'base', 'base_documento', 'porcentaje', 'monto']);
    }

    /** El total y la cantidad de una liquidacion son los de sus comisiones. */
    private function liquidacionesDescuadradas(): void
    {
        $filas = DB::select(
            'SELECT l.id, l.total, l.cantidad, COALESCE(SUM(c.monto), 0) AS suma, COUNT(c.id) AS filas
               FROM comisiones_liquidaciones l
          LEFT JOIN comisiones c ON c.liquidacion_id = l.id
           GROUP BY l.id, l.total, l.cantidad
             HAVING ABS(l.total - COALESCE(SUM(c.monto), 0)) > 0.009 OR l.cantidad <> COUNT(c.id)'
        );

        $this->reportar('Liquidaciones descuadradas con sus comisiones', collect($filas), ['id', 'total', 'suma', 'cantidad', 'filas']);
    }

    /** Imprime una comprobacion y cuenta el hallazgo si trajo filas. */
    private function reportar(string $titulo, Collection $filas, array $columnas): void
    {
        $this->newLine();

        if ($filas->isEmpty()) {
            $this->line("  <fg=green>ok</>    {$titulo}");

            return;
        }

        $this->problemas++;
        $this->line("  <fg=red>FALLA</> {$titulo}: <options=bold>{$filas->count()}</>");

        $this->table(
            $columnas,
            // Solo las primeras 20: el objetivo es avisar, no volcar la base.
            $filas->take(20)->map(fn($fila) => array_map(
                fn($columna) => (string) (((array) $fila)[$columna] ?? ''),
                array_combine($columnas, $columnas),
            ))->all(),
        );

        if ($filas->count() > 20) {
            $this->line('  ... y ' . ($filas->count() - 20) . ' mas');
        }
    }
}
