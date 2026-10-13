<?php

namespace App\Rules;

use App\Models\CodigoBarras;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Un codigo generado por el sistema (un sticker de codigos_barras) se asigna
 * a un solo equipo o articulo. Un UPC de caja comun no se toca: ese es del
 * modelo y se repite a proposito.
 *
 * Es una regla y no un indice porque productos.upc no puede ser UNIQUE (las
 * cajas repiten UPC). La red de seguridad es productos:auditar.
 */
class CodigoGeneradoLibre implements ValidationRule
{
    /**
     * @param  string    $tipo  'Producto', 'Repuesto' o 'Accesorio' (los de CodigoBarras::consultaDuenos)
     * @param  int|null  $id    el registro que se esta editando: su propio codigo no choca consigo
     */
    public function __construct(private string $tipo, private ?int $id = null)
    {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $codigo = trim((string) $value);

        if ($codigo === '' || !CodigoBarras::where('codigo', $codigo)->exists()) {
            return;
        }

        $propio = $this->id ? $this->tipo . '|' . $this->id : null;

        $otros = CodigoBarras::consultaDuenos([$codigo])->get()
            ->reject(fn($f) => $f->tipo . '|' . $f->dueno_id === $propio);

        if ($otros->isEmpty()) {
            return;
        }

        $otro = $otros->first();
        $quien = CodigoBarras::nombres($otros)[$otro->tipo][$otro->dueno_id] ?? 'otro registro';

        $fail("El código {$codigo} ya está asignado a {$quien}.");
    }
}
