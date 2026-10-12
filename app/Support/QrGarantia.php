<?php

namespace App\Support;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * El QR de los terminos de garantia (la pagina publica /garantia) que va en la
 * nota y en el recibo PDF. SVG y no PNG: no necesita imagick ni gd, y la
 * termica lo imprime nitido a cualquier tamano.
 *
 * Usa la URL absoluta de route(): en produccion APP_URL tiene que ser la
 * direccion publica con HTTPS, o el QR apunta a localhost.
 */
class QrGarantia
{
    public static function url(): string
    {
        return route('garantia');
    }

    /** El SVG para incrustar en HTML (la nota termica). */
    public static function svg(int $px = 120): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($px, 1), new SvgImageBackEnd()));

        // Sin la declaracion XML: dentro de un HTML sobra.
        return preg_replace('/^<\?xml.*?\?>\s*/', '', $writer->writeString(self::url()));
    }

    /** Como data URI, para un <img> (dompdf no dibuja un <svg> en linea). */
    public static function dataUri(int $px = 120): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(self::svg($px));
    }
}
