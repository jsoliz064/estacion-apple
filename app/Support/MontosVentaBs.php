<?php

namespace App\Support;

/**
 * La unica cuenta de los Bs de una venta: la usan el formulario (antes de
 * guardar), VentaService y Venta::recalcularTotales() (al guardar) y el recibo y
 * el detalle (al mostrar). Si cada uno la hiciera a su modo, la pantalla diria
 * 11500 y el recibo 11499.96.
 *
 * Todo se guarda en USD; los Bs escritos (ventas_detalles.precio_bs,
 * ventas.descuento_bs) se guardan aparte para mostrarlos tal cual. Con algun
 * monto en Bs la venta «manda en Bs»: el total en Bs es la suma de lo escrito, y
 * el total USD es ese total / TC, redondeado. El descuento USD absorbe la
 * diferencia de redondeo (nunca por debajo de 0): asi pagar en Bs exactamente
 * el total en Bs da, en USD, exactamente el total USD, y la venta queda pagada
 * en vez de a credito por un centavo.
 */
final class MontosVentaBs
{
    public static function aUsd(float $bs, float $tc): float
    {
        return $tc > 0 ? round($bs / $tc, 2) : 0.0;
    }

    /** El precio unitario en Bs: lo escrito o su USD al TC. */
    public static function precioBs(?float $precioBs, float $precioUsd, float $tc): float
    {
        return $precioBs !== null ? round($precioBs, 2) : Dinero::aBs($precioUsd, $tc);
    }

    /**
     * El subtotal en Bs de una linea. El descuento por linea (de ventas viejas)
     * esta en USD.
     */
    public static function lineaBs(?float $precioBs, float $precioUsd, int $cantidad, float $descuentoUsd, float $tc): float
    {
        $bruto = $precioBs !== null
            ? round($precioBs * $cantidad, 2)
            : Dinero::aBs(round($precioUsd * $cantidad, 2), $tc);

        return round($bruto - Dinero::aBs($descuentoUsd, $tc), 2);
    }

    /**
     * Los importes de la venta en USD y en Bs.
     *
     * Sin montos en Bs, como siempre: total = subtotal - descuento + mano de
     * obra, y sus Bs al TC (el descuento en Bs se deriva para que el recuadro
     * cuadre). Con montos en Bs: los Bs son la suma de lo escrito y el USD sale
     * de ellos.
     *
     * @param  list<float>  $lineasBs  el Bs de cada linea (lineaBs), cobros incluidos
     * @return array{subtotal: float, descuento: float, mano_obra: float, total: float, subtotal_bs: float, descuento_bs: float, mano_obra_bs: float, total_bs: float, con_bs: bool}
     */
    public static function calcular(float $subtotalUsd, float $descuentoUsd, float $manoObraUsd, array $lineasBs, ?float $descuentoBs, bool $conBs, float $tc): array
    {
        $subtotalUsd = round($subtotalUsd, 2);
        $manoObraUsd = round($manoObraUsd, 2);
        $manoBs = Dinero::aBs($manoObraUsd, $tc);

        if (!$conBs) {
            $descuento = round($descuentoUsd, 2);
            $total = round($subtotalUsd - $descuento + $manoObraUsd, 2);
            $subtotalBs = Dinero::aBs($subtotalUsd, $tc);
            $totalBs = Dinero::aBs($total, $tc);

            return [
                'subtotal' => $subtotalUsd, 'descuento' => $descuento, 'mano_obra' => $manoObraUsd, 'total' => $total,
                'subtotal_bs' => $subtotalBs, 'descuento_bs' => round($subtotalBs + $manoBs - $totalBs, 2),
                'mano_obra_bs' => $manoBs, 'total_bs' => $totalBs, 'con_bs' => false,
            ];
        }

        $subtotalBs = round(array_sum($lineasBs), 2);
        $descBs = round($descuentoBs ?? Dinero::aBs($descuentoUsd, $tc), 2);
        $totalBs = round($subtotalBs - $descBs + $manoBs, 2);

        $total = self::aUsd($totalBs, $tc);
        $descuento = round($subtotalUsd + $manoObraUsd - $total, 2);

        // Lineas en Bs redondeadas hacia abajo y sin descuento: el total USD
        // seria un centavo mayor que la suma. Un descuento negativo no existe;
        // queda la suma (y el pago en Bs cae dentro del centavo de FilasDePago).
        if ($descuento < 0) {
            $descuento = 0.0;
            $total = round($subtotalUsd + $manoObraUsd, 2);
        }

        return [
            'subtotal' => $subtotalUsd, 'descuento' => $descuento, 'mano_obra' => $manoObraUsd, 'total' => $total,
            'subtotal_bs' => $subtotalBs, 'descuento_bs' => $descBs, 'mano_obra_bs' => $manoBs, 'total_bs' => $totalBs, 'con_bs' => true,
        ];
    }
}
