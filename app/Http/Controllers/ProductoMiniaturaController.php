<?php

namespace App\Http\Controllers;

use App\Models\ProductoImagen;
use Illuminate\Http\Request;

/**
 * La miniatura de una foto de equipo: 160 px para las tablas (Productos y el
 * detalle de la compra) y 480 px para las tarjetas del catalogo publico.
 *
 * La foto completa es de hasta 1280 px (100-300 KB). La tabla muestra 40 px
 * (80 en el desplegable del celular) y la tarjeta del catalogo unos 180 px de
 * ancho en un celular 2x. Se reduce una vez con GD y queda en disco.
 *
 * Publica, como la foto completa (ProductoFotoController): el catalogo no
 * tiene sesion, y una miniatura no expone mas que la foto.
 *
 * Cache eterno: una imagen no se edita nunca (editar fotos borra filas y crea
 * otras), asi que su id identifica siempre los mismos bytes.
 */
class ProductoMiniaturaController extends Controller
{
    /** Lo de las tablas. */
    public const LADO = 160;
    /** Lo de las tarjetas del catalogo. */
    public const LADO_CATALOGO = 480;
    /** Los unicos lados que se generan: un ?l= libre llenaria el disco. */
    public const LADOS = [self::LADO, self::LADO_CATALOGO];

    /**
     * El lado va en el nombre del archivo y en la URL: el navegador guarda la
     * miniatura como inmutable, y cambiar el lado sin cambiar la URL dejaria la vieja.
     */
    public static function ruta(int $imagenId, int $lado = self::LADO): string
    {
        return storage_path("app/miniaturas/{$imagenId}-{$lado}.jpg");
    }

    /** La URL de la miniatura de ese lado. */
    public static function url(int $imagenId, int $lado = self::LADO): string
    {
        return route('productos.miniatura', ['imagen' => $imagenId, 'l' => $lado]);
    }

    /**
     * La celda «Foto» de las tablas: la miniatura, que abre el visor de fotos
     * (ProductoFotosModal), o un icono si el equipo no tiene fotos. Una sola copia
     * para Productos y la compra.
     *
     * Por debajo de lg la columna va en el desplegable (collapseOnTablet), que
     * pinta este mismo HTML: de ahi las clases responsivas, 80 px alli y 40 px en
     * la celda de escritorio.
     */
    public static function html(?int $imagenId, int $productoId): string
    {
        if (!$imagenId) {
            return '<span class="inline-flex h-20 w-20 lg:h-10 lg:w-10 items-center justify-center rounded-md bg-gray-100 text-gray-400 align-middle dark:bg-gray-700"><i class="fa-solid fa-mobile-screen"></i></span>';
        }

        return '<button type="button" title="Ver fotos" class="inline-block align-middle cursor-pointer rounded-md hover:opacity-80" '
            . 'wire:click="$dispatch(\'openProductoFotosModal\', { id: ' . $productoId . ' })">'
            . '<img src="' . e(self::url($imagenId)) . '" loading="lazy" alt="" class="h-20 w-20 lg:h-10 lg:w-10 rounded-md object-cover">'
            . '</button>';
    }

    /** El id de la primera foto de cada equipo, como subconsulta del builder (sin N+1). */
    public static function subconsultaPrimera(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductoImagen::selectRaw('MIN(id)')->whereColumn('producto_id', 'productos.id');
    }

    public function __invoke(Request $request, int $imagen)
    {
        $lado = (int) $request->query('l', self::LADO);
        if (!in_array($lado, self::LADOS, true)) {
            $lado = self::LADO;
        }
        $ruta = self::ruta($imagen, $lado);

        if (!is_file($ruta)) {
            $foto = ProductoImagen::find($imagen);
            $contenido = $foto?->contenido();
            abort_if($contenido === null, 404);

            // Sin GD con JPEG (le pasaba a produccion) la miniatura no se puede
            // hacer: se sirve la foto entera, sin el cache eterno, para que la
            // tabla no muestre una imagen rota y la reduzca cuando GD funcione.
            if (!$this->generar($contenido, $ruta, $lado)) {
                return response($contenido, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'no-cache']);
            }
        }

        return response()->file($ruta, ['Content-Type' => 'image/jpeg'])
            ->setPublic()
            ->setMaxAge(31536000)
            ->setImmutable();
    }

    /** Recorta al centro en cuadrado y la reduce a $lado x $lado (sin agrandar). */
    private function generar(string $contenido, string $ruta, int $lado): bool
    {
        $origen = @imagecreatefromstring($contenido);
        if ($origen === false) {
            return false;
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $corte = min($ancho, $alto);
        $lado = min($lado, $corte);

        $mini = imagecreatetruecolor($lado, $lado);
        imagecopyresampled($mini, $origen, 0, 0, intdiv($ancho - $corte, 2), intdiv($alto - $corte, 2), $lado, $lado, $corte, $corte);

        if (!is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        imagejpeg($mini, $ruta, 80);
        imagedestroy($origen);
        imagedestroy($mini);

        return true;
    }
}
