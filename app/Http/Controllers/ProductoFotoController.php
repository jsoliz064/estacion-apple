<?php

namespace App\Http\Controllers;

use App\Models\ProductoImagen;
use Illuminate\Support\Facades\Storage;

/**
 * La foto completa de un equipo (el visor, la edicion y el catalogo publico).
 *
 * Pasa por PHP y no por /storage: en produccion el servidor web es otro
 * contenedor y no llegaba al enlace public/storage (storage:link lo crea con la
 * ruta absoluta de este contenedor), asi que las fotos daban 404 mientras la
 * miniatura, que ya pasaba por aqui, se veia.
 *
 * Publica porque el catalogo lo es, y con cache eterno: una foto no se edita
 * (editar fotos borra filas y crea otras), su id siempre son los mismos bytes.
 */
class ProductoFotoController extends Controller
{
    public function __invoke(int $imagen)
    {
        $foto = ProductoImagen::find($imagen);
        $disco = Storage::disk(ProductoImagen::DISCO);
        abort_unless($foto && $disco->exists($foto->ruta), 404);

        return response()->file($disco->path($foto->ruta), ['Content-Type' => 'image/jpeg'])
            ->setPublic()
            ->setMaxAge(31536000)
            ->setImmutable();
    }
}
