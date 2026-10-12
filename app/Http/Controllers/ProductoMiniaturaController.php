<?php

namespace App\Http\Controllers;

use App\Models\ProductoImagen;

/**
 * La miniatura de una foto de equipo, para las tablas (Productos y el detalle
 * de la compra).
 *
 * La foto completa es de hasta 1280 px (100-300 KB) y la tabla muestra 40 px
 * (80 en el desplegable del celular); rappasoft la redibuja en cada filtro,
 * busqueda o pagina. Se reduce una vez a 160 px con GD (nitida a 80 px en una
 * pantalla 2x) y queda en disco.
 *
 * Cache eterno: una imagen no se edita nunca (editar fotos borra filas y crea
 * otras), asi que su id identifica siempre los mismos bytes.
 */
class ProductoMiniaturaController extends Controller
{
    public const LADO = 160;

    /**
     * El lado va en el nombre del archivo y en la URL: el navegador guarda la
     * miniatura como inmutable, y cambiar LADO sin cambiar la URL dejaria la vieja.
     */
    public static function ruta(int $imagenId): string
    {
        return storage_path("app/miniaturas/{$imagenId}-" . self::LADO . '.jpg');
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
            . '<img src="' . e(route('productos.miniatura', ['imagen' => $imagenId, 'l' => self::LADO])) . '" loading="lazy" alt="" class="h-20 w-20 lg:h-10 lg:w-10 rounded-md object-cover">'
            . '</button>';
    }

    /** El id de la primera foto de cada equipo, como subconsulta del builder (sin N+1). */
    public static function subconsultaPrimera(): \Illuminate\Database\Eloquent\Builder
    {
        return ProductoImagen::selectRaw('MIN(id)')->whereColumn('producto_id', 'productos.id');
    }

    public function __invoke(int $imagen)
    {
        $ruta = self::ruta($imagen);

        if (!is_file($ruta)) {
            $foto = ProductoImagen::find($imagen);
            $contenido = $foto?->contenido();
            abort_if($contenido === null, 404);

            // Sin GD con JPEG (le pasaba a produccion) la miniatura no se puede
            // hacer: se sirve la foto entera, sin el cache eterno, para que la
            // tabla no muestre una imagen rota y la reduzca cuando GD funcione.
            if (!$this->generar($contenido, $ruta)) {
                return response($contenido, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, no-cache']);
            }
        }

        // setPrivate(): BinaryFileResponse se marca public sola, y va detras del login.
        return response()->file($ruta, ['Content-Type' => 'image/jpeg'])
            ->setPrivate()
            ->setMaxAge(31536000)
            ->setImmutable();
    }

    /** Recorta al centro en cuadrado y la reduce a LADO x LADO. */
    private function generar(string $contenido, string $ruta): bool
    {
        $origen = @imagecreatefromstring($contenido);
        if ($origen === false) {
            return false;
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $lado = min($ancho, $alto);

        $mini = imagecreatetruecolor(self::LADO, self::LADO);
        imagecopyresampled($mini, $origen, 0, 0, intdiv($ancho - $lado, 2), intdiv($alto - $lado, 2), self::LADO, self::LADO, $lado, $lado);

        if (!is_dir(dirname($ruta))) {
            mkdir(dirname($ruta), 0775, true);
        }

        imagejpeg($mini, $ruta, 80);
        imagedestroy($origen);
        imagedestroy($mini);

        return true;
    }
}
