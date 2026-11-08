<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Un color de equipo, administrable (antes el enum ProductoColor: cada color
 * nuevo de Apple exigia tocar codigo y migrar).
 *
 * `productos.color` guarda el nombre como texto, asi que:
 *  - un color en uso NO se renombra (los equipos quedarian con un nombre que
 *    ya no esta en la lista): se crea otro y se desactiva este;
 *  - un color en uso NO se borra: se desactiva, y deja de ofrecerse.
 */
class Color extends Model
{
    protected $table = 'colores';

    protected $fillable = ['nombre', 'hex', 'activo', 'orden'];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    public function modelos()
    {
        return $this->belongsToMany(ProductoModelo::class, 'productos_modelos_colores', 'color_id', 'producto_modelo_id');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeOrdenados(Builder $query): Builder
    {
        return $query->orderBy('orden')->orderBy('nombre');
    }

    /**
     * Los colores que se ofrecen al cargar un equipo de ese modelo: la UNICA
     * respuesta a esa pregunta (alta, edicion, permuta, reemplazo de reclamo).
     *
     * Los activos marcados en el modelo; si el modelo no tiene ninguno marcado
     * (o no hay modelo), todos los activos. Mas `$actual` aunque este inactivo
     * o fuera del modelo: editar un equipo viejo no debe borrarle el color
     * (el patron de Sucursal::paraSelect).
     *
     * @return Collection<int, Color>
     */
    public static function paraModelo(?int $modeloId, ?string $actual = null): Collection
    {
        $delModelo = $modeloId
            ? static::activos()->whereHas('modelos', fn($q) => $q->whereKey($modeloId))->ordenados()->get()
            : collect();

        $colores = $delModelo->isNotEmpty() ? $delModelo : static::activos()->ordenados()->get();

        if ($actual !== null && $actual !== '' && !$colores->contains('nombre', $actual)) {
            $extra = static::where('nombre', $actual)->first();
            if ($extra) {
                $colores->push($extra);
            }
        }

        return $colores->values();
    }

    /** @return array<int, string> Los nombres de paraModelo(), para Rule::in. */
    public static function nombresParaModelo(?int $modeloId, ?string $actual = null): array
    {
        return static::paraModelo($modeloId, $actual)->pluck('nombre')->all();
    }

    /** Cuantos equipos llevan este color (por nombre: `productos.color` es texto). */
    public function equiposQueLoUsan(): int
    {
        return Producto::where('color', $this->getOriginal('nombre') ?? $this->nombre)->count();
    }

    /**
     * El style inline de la muestra (x-color-chip y la tabla de colores): no
     * hay safelist, una clase bg-[#xxx] armada aqui nunca se generaria.
     */
    public function estilo(): string
    {
        return 'background-color: ' . e($this->hex) . '; color: ' . $this->colorTexto() . ';';
    }

    /** Negro o blanco, el que se lee mejor sobre la muestra (luminancia relativa). */
    public function colorTexto(): string
    {
        $hex = ltrim((string) $this->hex, '#');
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            return '#111827';
        }

        [$r, $g, $b] = array_map(fn($c) => hexdec($c) / 255, str_split($hex, 2));
        $lineal = fn($c) => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        $l = 0.2126 * $lineal($r) + 0.7152 * $lineal($g) + 0.0722 * $lineal($b);

        return $l > 0.4 ? '#111827' : '#FFFFFF';
    }
}
