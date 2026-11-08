<?php

namespace App\Livewire\Catalogo;

use App\Enums\ProductoAlmacenamiento;
use App\Enums\ProductoGrado;
use App\Enums\ProductoTipoVenta;
use App\Http\Controllers\ProductoMiniaturaController;
use App\Models\Color;
use App\Models\Producto;
use App\Support\Dinero;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * El catalogo publico (/catalogo), pensado para el celular del cliente.
 *
 * Solo lo que publica Producto::scopeEnCatalogo() (Inventario, sin baja y con
 * la casilla marcada): los chips de modelo, almacenamiento y grado tambien
 * salen de ahi, asi que un modelo sin equipos no aparece (antes habia una
 * seccion «Agotados» con todo el catalogo base).
 *
 * Los filtros y el equipo abierto viven en la URL: un link compartido abre lo
 * mismo, y `equipo` va con history para que el «atras» del celular cierre el
 * detalle en vez de salir de la pagina.
 *
 * Nunca muestra ni busca por IMEI: es una pagina publica.
 */
class CatalogoIndex extends Component
{
    public const POR_PAGINA = 12;

    public const ORDENES = [
        'recomendados' => 'Recomendados',
        'precio_asc' => 'Menor precio',
        'precio_desc' => 'Mayor precio',
        'recientes' => 'Recién llegados',
    ];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'modelo', except: '')]
    public string $modeloId = '';

    #[Url(as: 'gb', except: '')]
    public string $almacenamiento = '';

    #[Url(except: '')]
    public string $grado = '';

    #[Url(except: 'recomendados')]
    public string $orden = 'recomendados';

    #[Url(as: 'equipo', except: '', history: true)]
    public string $equipoId = '';

    public int $porPagina = self::POR_PAGINA;

    public function updated($propiedad): void
    {
        if (in_array($propiedad, ['search', 'modeloId', 'almacenamiento', 'grado', 'orden'], true)) {
            $this->porPagina = self::POR_PAGINA;
        }
    }

    public function elegirModelo($id): void
    {
        // Tocar el elegido lo suelta. El almacenamiento es del modelo: se limpia.
        $this->modeloId = (string) $this->modeloId === (string) $id ? '' : (string) $id;
        $this->almacenamiento = '';
        $this->porPagina = self::POR_PAGINA;
    }

    public function elegirAlmacenamiento(string $valor): void
    {
        $this->almacenamiento = $this->almacenamiento === $valor ? '' : $valor;
        $this->porPagina = self::POR_PAGINA;
    }

    public function elegirGrado(string $valor): void
    {
        $this->grado = $this->grado === $valor ? '' : $valor;
        $this->porPagina = self::POR_PAGINA;
    }

    public function limpiar(): void
    {
        $this->reset(['search', 'modeloId', 'almacenamiento', 'grado', 'orden']);
        $this->porPagina = self::POR_PAGINA;
    }

    public function verMas(): void
    {
        $this->porPagina += self::POR_PAGINA;
    }

    public function abrir($id): void
    {
        $this->equipoId = (string) (int) $id;
    }

    public function cerrar(): void
    {
        $this->equipoId = '';
    }

    /**
     * Lo publicado con los filtros aplicados, menos los que se nombran en
     * $sin: cada fila de chips cuenta con los DEMAS filtros, para que elegir
     * un grado no deje la fila de grados con uno solo.
     */
    private function consulta(array $sin = []): Builder
    {
        $q = Producto::query()->enCatalogo();

        if ($this->search !== '' && !in_array('search', $sin, true)) {
            $termino = '%' . trim($this->search) . '%';
            $q->where(fn($w) => $w
                ->whereHas('modelo', fn($m) => $m->where('nombre', 'like', $termino))
                ->orWhere('productos.almacenamiento', 'like', $termino)
                ->orWhere('productos.color', 'like', $termino)
                ->orWhere('productos.estado_grado', 'like', $termino));
        }
        if ($this->modeloId !== '' && !in_array('modelo', $sin, true)) {
            $q->where('productos.producto_modelo_id', (int) $this->modeloId);
        }
        if ($this->almacenamiento !== '' && !in_array('almacenamiento', $sin, true)) {
            $q->where('productos.almacenamiento', $this->almacenamiento);
        }
        if ($this->grado !== '' && !in_array('grado', $sin, true)) {
            $q->where('productos.estado_grado', $this->grado);
        }

        return $q;
    }

    private function ordenar(Builder $q): Builder
    {
        return match ($this->orden) {
            'precio_asc' => $q->orderBy('productos.precio')->orderByDesc('productos.id'),
            'precio_desc' => $q->orderByDesc('productos.precio')->orderByDesc('productos.id'),
            'recientes' => $q->orderByDesc('productos.created_at')->orderByDesc('productos.id'),
            // Los destacados primero, despues lo ultimo que entro.
            default => $q->orderByDesc('productos.venta_rapida')->orderByDesc('productos.created_at')->orderByDesc('productos.id'),
        };
    }

    /** Los modelos con equipos publicados, con su cantidad. */
    private function modelos()
    {
        return $this->consulta(['modelo', 'almacenamiento'])
            ->join('productos_modelos as m', 'm.id', '=', 'productos.producto_modelo_id')
            ->groupBy('m.id', 'm.nombre')
            ->orderBy('m.nombre')
            ->selectRaw('m.id, m.nombre, COUNT(*) AS cantidad')
            ->toBase()
            ->get();
    }

    /** Almacenamientos del modelo elegido, en el orden del enum (64GB antes que 1TB). */
    private function almacenamientos(): array
    {
        if ($this->modeloId === '') {
            return [];
        }

        $hay = $this->consulta(['almacenamiento'])
            ->groupBy('productos.almacenamiento')
            ->selectRaw('productos.almacenamiento AS valor, COUNT(*) AS cantidad')
            ->pluck('cantidad', 'valor');

        $orden = array_column(ProductoAlmacenamiento::cases(), 'value');

        return $hay->map(fn($c) => (int) $c)
            ->sortBy(fn($c, $valor) => ($i = array_search($valor, $orden, true)) === false ? 99 : $i)
            ->all();
    }

    /** Grados con equipos, en el orden del enum. */
    private function grados(): array
    {
        $hay = $this->consulta(['grado'])
            ->whereNotNull('productos.estado_grado')
            ->groupBy('productos.estado_grado')
            ->selectRaw('productos.estado_grado AS valor, COUNT(*) AS cantidad')
            ->pluck('cantidad', 'valor');

        return collect(ProductoGrado::values())
            ->filter(fn($g) => isset($hay[$g]))
            ->mapWithKeys(fn($g) => [$g => (int) $hay[$g]])
            ->all();
    }

    /** El equipo abierto, solo si se sigue publicando (un link viejo no abre nada). */
    private function equipo(): ?Producto
    {
        if ($this->equipoId === '' || !ctype_digit($this->equipoId)) {
            return null;
        }

        return Producto::enCatalogo()->with(['imagenes', 'modelo'])->find((int) $this->equipoId);
    }

    public static function miniatura(Producto $p): ?string
    {
        $primera = $p->imagenes->sortBy('id')->first();

        return $primera ? ProductoMiniaturaController::url($primera->id, ProductoMiniaturaController::LADO_CATALOGO) : null;
    }

    public static function esOferta(Producto $p): bool
    {
        return $p->tipo_venta === ProductoTipoVenta::Oferta->value;
    }

    public static function recienLlegado(Producto $p): bool
    {
        return $p->created_at !== null && $p->created_at->gt(now()->subDays(3));
    }

    private function nombre(Producto $p): string
    {
        return trim(($p->modelo?->nombre ?? 'Equipo') . ' ' . $p->almacenamiento . ' ' . $p->color);
    }

    /** El link de WhatsApp: general, o la consulta por un equipo con su link. */
    public function whatsapp(?Producto $p = null): string
    {
        $texto = $p
            ? "Hola, me interesa el {$this->nombre($p)}"
                . ($p->estado_grado ? " ({$p->estado_grado}" . ($p->bateria_porcentaje ? ", batería {$p->bateria_porcentaje}%" : '') . ')' : '')
                . ' de ' . Dinero::usd($p->precio) . '. ' . route('catalogo', ['equipo' => $p->id])
            : 'Hola, quisiera consultar por un equipo del catálogo.';

        return 'https://wa.me/' . config('tienda.whatsapp') . '?text=' . rawurlencode($texto);
    }

    public function render()
    {
        $consulta = $this->consulta();
        $total = (clone $consulta)->count();

        return view('livewire.catalogo.catalogo-index', [
            'equipos' => $this->ordenar($consulta)->with(['imagenes', 'modelo'])->limit($this->porPagina)->get(),
            'total' => $total,
            'publicados' => Producto::enCatalogo()->count(),
            'modelos' => $this->modelos(),
            'almacenamientos' => $this->almacenamientos(),
            'grados' => $this->grados(),
            'equipo' => $this->equipo(),
            // Una consulta para el punto de color de tarjetas y detalle.
            'hexColores' => Color::pluck('hex', 'nombre'),
            'filtrosActivos' => $this->search !== '' || $this->modeloId !== '' || $this->almacenamiento !== '' || $this->grado !== '',
        ]);
    }
}
