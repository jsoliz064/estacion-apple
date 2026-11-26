<?php

namespace App\Livewire\Producto\Modals;

use App\Enums\ProductoAlmacenamiento;
use App\Enums\ProductoEstado;
use App\Enums\ProductoGrado;
use App\Enums\ProductoVersion;
use App\Models\Producto;
use App\Models\ProductoModelo;
use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * El detalle de un modelo (el boton de info de cada tarjeta de «Modelos de
 * Productos»): cuantos equipos hay, con filtros que se combinan (sucursal,
 * estado, tipo, capacidad, grado y color) y la lista de los que coinciden.
 *
 * Pedido de la tienda: preguntar «cuantos Chip rojos hay en la sucursal A» y
 * ver a la vez lo general. Por eso cada grupo de fichas se cuenta con todos los
 * filtros MENOS el suyo: con Chip elegido, «Tipo» sigue diciendo cuantos eSIM
 * hay, y «Color» cuenta solo entre los Chip.
 *
 * Antes era un modal escrito dentro de ProductoFilterModel, que recalcula las
 * tarjetas de todos los modelos en cada render: cada toque de un filtro las
 * habria rehecho.
 *
 * Cuenta solo el stock vigente: nunca lo vendido, lo que esta en una compra en
 * borrador ni lo dado de baja.
 */
class ProductoModeloDetalleModal extends Component
{
    /** Lo vacio (un equipo sin tipo o sin almacenamiento), como valor de filtro. */
    public const VACIO = '_vacio';

    public const TODOS = 'todos';

    /** Cuantos equipos de la lista se pintan de entrada, y cuantos suma «Mostrar más». */
    public const PAGINA = 20;

    /** Las fichas: propiedad del filtro => columna de productos. */
    private const FACETAS = [
        'version' => 'version',
        'almacenamiento' => 'almacenamiento',
        'grado' => 'estado_grado',
        'color' => 'color',
    ];

    public bool $openModal = false;

    #[Locked]
    public ?int $modeloId = null;

    // Los filtros: '' = todos.
    public string $sucursalId = '';
    public string $estado = 'Inventario';
    public string $version = '';
    public string $almacenamiento = '';
    public string $grado = '';
    public string $color = '';

    public int $mostrar = self::PAGINA;

    /** Los estados de stock (lo que muestra la tarjeta del modelo): «Todos» son estos. */
    public static function estadosDeStock(): array
    {
        return [ProductoEstado::Inventario->value, ProductoEstado::Reparacion->value, ProductoEstado::Fuera->value,
            ProductoEstado::Roto->value, ProductoEstado::Reserva->value];
    }

    #[On('openProductoModeloDetalleModal')]
    public function openModal($id): void
    {
        abort_unless(Auth::user()?->can('producto.index'), 403);

        $this->modeloId = (int) $id;
        $this->limpiarFiltros();
        $this->openModal = true;
    }

    public function closeModal(): void
    {
        $this->openModal = false;
    }

    public function limpiarFiltros(): void
    {
        $this->reset('sucursalId', 'estado', 'version', 'almacenamiento', 'grado', 'color', 'mostrar');
    }

    /** Tocar una ficha la elige; tocar la elegida la suelta. */
    public function elegir(string $faceta, string $valor): void
    {
        if (!array_key_exists($faceta, self::FACETAS)) {
            return;
        }

        $this->{$faceta} = $this->{$faceta} === $valor ? '' : $valor;
        $this->mostrar = self::PAGINA;
    }

    public function mostrarMas(): void
    {
        $this->mostrar += self::PAGINA;
    }

    public function updated($propiedad): void
    {
        // Solo los estados de stock o «Todos»: un Vendido que llegue a mano
        // vuelve a Inventario, no se cuela como «Todos».
        if ($propiedad === 'estado' && $this->estado !== self::TODOS && !in_array($this->estado, self::estadosDeStock(), true)) {
            $this->estado = 'Inventario';
        }

        // Otro filtro, otra lista: vuelve a los primeros.
        if (in_array($propiedad, ['sucursalId', 'estado'], true)) {
            $this->mostrar = self::PAGINA;
        }
    }

    /**
     * Los equipos del modelo con todos los filtros puestos menos `$sin` (una
     * faceta, 'sucursal' o 'estado'): la unica copia de las reglas, para que el
     * numero grande, las fichas y la lista no cuenten distinto.
     */
    private function consulta(?string $sin = null): Builder
    {
        $estados = match (true) {
            $sin === 'estado', $this->estado === self::TODOS => self::estadosDeStock(),
            in_array($this->estado, self::estadosDeStock(), true) => [$this->estado],
            default => [ProductoEstado::Inventario->value],
        };

        $q = Producto::query()
            ->vigentes()
            ->where('productos.producto_modelo_id', $this->modeloId)
            ->whereIn('productos.estado', $estados);

        if ($sin !== 'sucursal' && $this->sucursalId !== '') {
            $q->where('productos.sucursal_id', (int) $this->sucursalId);
        }

        foreach (self::FACETAS as $faceta => $columna) {
            $valor = $this->{$faceta};

            if ($faceta === $sin || $valor === '') {
                continue;
            }

            $valor === self::VACIO ? $q->whereNull("productos.$columna") : $q->where("productos.$columna", $valor);
        }

        return $q;
    }

    /** Cuantos hay de cada valor de una columna, con los demas filtros. NULL = VACIO. */
    private function conteoPor(string $sin, string $columna): array
    {
        return $this->consulta($sin)->toBase()
            ->selectRaw("productos.$columna AS valor, COUNT(*) AS total")
            ->groupBy("productos.$columna")
            ->get()
            ->mapWithKeys(fn($f) => [($f->valor === null || $f->valor === '' ? self::VACIO : (string) $f->valor) => (int) $f->total])
            ->all();
    }

    /**
     * Las fichas de una faceta, en su orden (el del enum; el color, de mas a
     * menos) y con lo vacio al final. La elegida sigue aunque otro filtro la
     * deje en 0: si no, no habria como soltarla.
     *
     * @return list<array{valor: string, etiqueta: string, total: int, activa: bool}>
     */
    private function fichas(string $faceta): array
    {
        $conteo = $this->conteoPor($faceta, self::FACETAS[$faceta]);
        $elegido = $this->{$faceta};

        if ($elegido !== '' && !isset($conteo[$elegido])) {
            $conteo[$elegido] = 0;
        }

        $orden = match ($faceta) {
            'version' => array_column(ProductoVersion::cases(), 'value'),
            'almacenamiento' => array_column(ProductoAlmacenamiento::cases(), 'value'),
            'grado' => ProductoGrado::values(),
            default => null,
        };

        $fichas = collect($conteo)->map(fn($total, $valor) => [
            'valor' => (string) $valor,
            'etiqueta' => $this->etiqueta($faceta, (string) $valor),
            'total' => $total,
            'activa' => $elegido === (string) $valor,
        ])->values();

        $posicion = function ($ficha) use ($orden) {
            if ($ficha['valor'] === self::VACIO) {
                return [2, 0, ''];
            }

            if ($orden === null) {
                return [0, -$ficha['total'], $ficha['etiqueta']];
            }

            $i = array_search($ficha['valor'], $orden, true);

            return [$i === false ? 1 : 0, $i === false ? 0 : $i, $ficha['etiqueta']];
        };

        return $fichas->sort(fn($a, $b) => $posicion($a) <=> $posicion($b))->values()->all();
    }

    private function etiqueta(string $faceta, string $valor): string
    {
        if ($valor === self::VACIO) {
            return match ($faceta) {
                'almacenamiento' => 'Sin almacenamiento',
                'color' => 'Sin color',
                default => 'Sin definir',
            };
        }

        return $faceta === 'version' ? ProductoVersion::labelDe($valor) : $valor;
    }

    /** El texto de «Copiar», para mandar por WhatsApp. */
    private function textoParaCopiar(ProductoModelo $modelo, int $total, array $filtros, array $grupos): string
    {
        $texto = "*Modelo:* {$modelo->nombre}\n";
        $texto .= '*Filtros:* ' . implode(' · ', $filtros) . "\n";
        $texto .= "*Cantidad:* {$total}\n";

        foreach ($grupos as $grupo) {
            $conEquipos = array_filter($grupo['fichas'], fn($f) => $f['total'] > 0);

            if (!$conEquipos) {
                continue;
            }

            $texto .= "\n*{$grupo['titulo']}:*\n";
            foreach ($conEquipos as $f) {
                $texto .= "- {$f['etiqueta']}: {$f['total']}\n";
            }
        }

        return trim($texto);
    }

    public function render()
    {
        $modelo = $this->openModal && $this->modeloId ? ProductoModelo::find($this->modeloId) : null;

        if (!$modelo) {
            return view('livewire.producto.modals.producto-modelo-detalle-modal', ['modelo' => null]);
        }

        $total = $this->consulta()->count();

        // Las sucursales con equipos (con los demas filtros), y la elegida aunque quede en 0.
        $porSucursal = $this->conteoPor('sucursal', 'sucursal_id');
        $sucursales = Sucursal::orderBy('nombre')->get(['id', 'nombre'])
            ->filter(fn($s) => isset($porSucursal[(string) $s->id]) || (string) $s->id === $this->sucursalId)
            ->map(fn($s) => ['id' => (string) $s->id, 'nombre' => $s->nombre, 'total' => $porSucursal[(string) $s->id] ?? 0])
            ->values()
            ->all();

        $porEstado = $this->conteoPor('estado', 'estado');
        $estados = collect(self::estadosDeStock())
            ->map(fn($e) => ['valor' => $e, 'etiqueta' => ProductoEstado::labelDe($e), 'total' => $porEstado[$e] ?? 0])
            ->all();

        $grupos = [];
        foreach (['version' => 'Tipo', 'almacenamiento' => 'Capacidad', 'grado' => 'Grado', 'color' => 'Color'] as $faceta => $titulo) {
            $grupos[$faceta] = ['titulo' => $titulo, 'fichas' => $this->fichas($faceta)];
        }

        $equipos = $this->consulta()
            ->with('sucursal')
            ->orderBy('productos.sucursal_id')
            ->orderBy('productos.almacenamiento')
            ->orderBy('productos.color')
            ->orderBy('productos.id')
            ->limit($this->mostrar)
            ->get();

        // Lo que se esta mirando, en palabras (el encabezado del texto copiado).
        $filtros = [
            $this->sucursalId === '' ? 'Todas las sucursales' : (collect($sucursales)->firstWhere('id', $this->sucursalId)['nombre'] ?? 'Sucursal'),
            $this->estado === self::TODOS ? 'Todos los estados' : ProductoEstado::labelDe($this->estado),
        ];
        foreach (array_keys(self::FACETAS) as $faceta) {
            if ($this->{$faceta} !== '') {
                $filtros[] = $this->etiqueta($faceta, $this->{$faceta});
            }
        }

        $hayFiltros = $this->sucursalId !== '' || $this->estado !== 'Inventario'
            || collect(array_keys(self::FACETAS))->contains(fn($f) => $this->{$f} !== '');

        return view('livewire.producto.modals.producto-modelo-detalle-modal', [
            'modelo' => $modelo,
            'total' => $total,
            'totalModelo' => $hayFiltros ? Producto::query()->vigentes()->where('producto_modelo_id', $modelo->id)
                ->whereIn('estado', self::estadosDeStock())->count() : null,
            'sucursales' => $sucursales,
            'estados' => $estados,
            'grupos' => $grupos,
            'equipos' => $equipos,
            'hayFiltros' => $hayFiltros,
            'texto' => $this->textoParaCopiar($modelo, $total, $filtros, $grupos),
        ]);
    }
}
