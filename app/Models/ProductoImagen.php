<?php

namespace App\Models;

use App\Http\Controllers\ProductoMiniaturaController;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Una foto del equipo: un archivo en el disco `public`
 * (productos/{producto_id}/{uuid}.jpg) y su ruta aqui. Antes era el data URL
 * base64 en la base, que inflaba los respaldos y viajaba en cada peticion de
 * Livewire. Se guarda SOLO con guardar() y se borra por Eloquent: el evento
 * deleted se lleva el archivo y su miniatura.
 */
class ProductoImagen extends Model
{
    public const DISCO = 'public';
    public const LADO_MAXIMO = 1280;

    protected $table = 'productos_imagenes';
    protected $guarded = ['id'];

    protected $fillable = ['ruta', 'producto_id'];

    protected static function booted(): void
    {
        static::deleted(function (ProductoImagen $imagen) {
            Storage::disk(self::DISCO)->delete($imagen->ruta);
            @unlink(ProductoMiniaturaController::ruta($imagen->id));
        });
    }

    /**
     * Guarda la foto como JPEG de 1280 px de lado mayor como mucho: la de la
     * camara ya viene asi, y una de galeria pesaria diez veces mas. Primero el
     * archivo y despues la fila: si la transaccion falla queda un archivo
     * huerfano (inocuo), nunca una fila que apunte a la nada.
     */
    public static function guardar(Producto $producto, UploadedFile $archivo): self
    {
        $ruta = "productos/{$producto->id}/" . Str::uuid() . '.jpg';
        Storage::disk(self::DISCO)->put($ruta, self::comoJpeg($archivo));

        return self::create(['producto_id' => $producto->id, 'ruta' => $ruta]);
    }

    /**
     * Un JPEG que ya cabe (todo lo que entrega la camara) se guarda tal cual:
     * getimagesize() no necesita GD. Solo lo demas pasa por GD. En produccion GD
     * se compilo sin JPEG, imagecreatefromstring() devolvia false y ninguna foto
     * dejaba guardar el equipo (el error no se veia en el modal).
     */
    private static function comoJpeg(UploadedFile $archivo): string
    {
        $contenido = (string) file_get_contents($archivo->getRealPath());
        $info = @getimagesizefromstring($contenido);

        if ($info && $info[2] === IMAGETYPE_JPEG && max($info[0], $info[1]) <= self::LADO_MAXIMO) {
            return $contenido;
        }

        $origen = @imagecreatefromstring($contenido);

        if ($origen === false) {
            throw ValidationException::withMessages(['fotos' => $info
                ? 'El servidor no pudo procesar esta foto (falta soporte de imágenes). Sácala con el botón «Tomar Fotos» o avisa al administrador.'
                : 'La foto no es una imagen válida.']);
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $escala = min(1, self::LADO_MAXIMO / max($ancho, $alto));
        $destino = $origen;

        if ($escala < 1) {
            $destino = imagecreatetruecolor((int) round($ancho * $escala), (int) round($alto * $escala));
            imagecopyresampled($destino, $origen, 0, 0, 0, 0, imagesx($destino), imagesy($destino), $ancho, $alto);
        }

        ob_start();
        imagejpeg($destino, null, 85);
        $jpeg = ob_get_clean();
        imagedestroy($origen);
        if ($destino !== $origen) {
            imagedestroy($destino);
        }

        return $jpeg;
    }

    /**
     * Por la ruta de Laravel (ProductoFotoController), no por /storage: en
     * produccion el servidor web no llegaba al enlace public/storage. route()
     * usa ademas la direccion del pedido, no APP_URL.
     */
    public function url(): string
    {
        return route('productos.foto', $this->id);
    }

    public function contenido(): ?string
    {
        return Storage::disk(self::DISCO)->get($this->ruta);
    }
}
