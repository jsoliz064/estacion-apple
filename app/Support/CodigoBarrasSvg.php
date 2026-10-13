<?php

namespace App\Support;

use Picqer\Barcode\Renderers\SvgRenderer;
use Picqer\Barcode\Types\TypeEan13;

/**
 * El EAN-13 de un sticker, como SVG para incrustar en el HTML de impresion.
 * SVG y no PNG por lo mismo que QrGarantia: no necesita gd y la termica lo
 * imprime nitido a cualquier tamano.
 *
 * Sale estirable (preserveAspectRatio="none"): el ancho y el alto los pone el
 * CSS de la etiqueta, segun el tamano elegido. Los digitos legibles los pinta
 * la etiqueta aparte, en texto.
 */
class CodigoBarrasSvg
{
    public static function ean13(string $codigo): string
    {
        $barcode = (new TypeEan13())->getBarcode($codigo);

        // Un modulo = 2 unidades: el SVG se escala entero, el numero da igual.
        $svg = (new SvgRenderer())
            ->setSvgType(SvgRenderer::TYPE_SVG_INLINE)
            ->render($barcode, $barcode->getWidth() * 2, 60);

        return str_replace('<svg ', '<svg preserveAspectRatio="none" ', $svg);
    }
}
