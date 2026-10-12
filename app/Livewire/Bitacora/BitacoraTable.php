<?php

namespace App\Livewire\Bitacora;

use App\Enums\BitacoraEvento;
use App\Models\Bitacora;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\DateFilter;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;

/**
 * La bitacora vista de dos maneras, con UNA sola tabla:
 *
 *   - de un SUJETO (la pestaña "Cambios" del repuesto):  tipo + id
 *   - de un USUARIO (su historial):                     usuarioId
 *
 * Eran dos copias en potencia de lo mismo. El historial del telefono NO pasa por
 * aqui: tiene su modal de detalle con venta y reparacion, que es suyo.
 *
 * Los parametros van #[Locked]: son los que deciden QUE filas se ven, y uno
 * manipulado desde el navegador enseñaria el historial de otro.
 */
class BitacoraTable extends DataTableComponent
{
    protected $model = Bitacora::class;

    #[Locked]
    public ?string $tipo = null;

    #[Locked]
    public ?int $sujetoId = null;

    #[Locked]
    public ?int $usuarioId = null;

    /** Memo por request de la fila de totales. */
    protected ?int $total = null;

    public function mount(?string $tipo = null, ?int $sujetoId = null, ?int $usuarioId = null): void
    {
        // Lista blanca: un tipo que la bitacora no conoce no llega a un where.
        abort_if($tipo !== null && !array_key_exists($tipo, Bitacora::TIPOS), 404);
        abort_if(($tipo === null) === ($usuarioId === null), 500, 'BitacoraTable: o un sujeto o un usuario.');

        $this->tipo = $tipo;
        $this->sujetoId = $sujetoId;
        $this->usuarioId = $usuarioId;
    }

    protected function esDeUsuario(): bool
    {
        return $this->usuarioId !== null;
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para TODAS las tablas,
        // y en el historial del repuesto convive con la de movimientos.
        $this->setTableName('cambios');

        $this->setPrimaryKey('id')
            // La mas nueva primero. Por 'created_at' y no por 'id': rappasoft solo
            // ordena por una COLUMNA declarada, y esta tabla no tiene la de id;
            // un setDefaultSort('id') se ignoraba en silencio y MySQL devolvia
            // el orden del indice, de la mas vieja a la mas nueva. La columna
            // Fecha ya desempata por id.
            ->setDefaultSort('created_at', 'desc')
            ->setEmptyMessage($this->esDeUsuario()
                ? 'Este usuario todavía no tiene ninguna acción registrada.'
                : 'Todavía no hay cambios registrados.')
            ->setSearchPlaceholder('Buscar en la descripción...');

        // Lo que leen los format() sin ser columna declarada.
        $this->setAdditionalSelects([
            'bitacoras.auditable_type',
            'bitacoras.auditable_id',
            'bitacoras.cambios',
        ]);

        $this->setFooterTrAttributes(fn($rows) => [
            'default' => false,
            'class' => 'bg-gray-100 dark:bg-gray-900 font-bold text-gray-900 dark:text-white',
        ]);
    }

    /**
     * Query base, compartida por las filas y el pie.
     *
     * TODO filtro y TODA busqueda se aplican AQUI, nunca con ->filter() ni
     * ->searchable(): applyFilters() y applySearch() solo tocan el builder de
     * las filas, no el del pie, y el total dejaria de cuadrar con lo que se ve.
     * Es la regla que documenta MovimientosStockTable.
     */
    protected function scopedQuery(): Builder
    {
        $query = Bitacora::query()->with('auditable');

        if ($this->esDeUsuario()) {
            $query->where('bitacoras.user_id', $this->usuarioId);
        } else {
            $query->where('bitacoras.auditable_type', $this->tipo)
                ->where('bitacoras.auditable_id', $this->sujetoId);
        }

        // getAppliedFilterWithValue() devuelve el valor CRUDO del query string:
        // la lista blanca va a mano.
        $eventos = array_values(array_intersect(
            (array) ($this->getAppliedFilterWithValue('evento') ?? []),
            array_keys(BitacoraEvento::opcionesConEstados()),
        ));
        if ($eventos !== []) {
            $query->whereIn('bitacoras.evento', $eventos);
        }

        if ($this->esDeUsuario()) {
            $tipos = array_values(array_intersect(
                (array) ($this->getAppliedFilterWithValue('tipo') ?? []),
                array_keys(Bitacora::TIPOS),
            ));
            if ($tipos !== []) {
                $query->whereIn('bitacoras.auditable_type', $tipos);
            }
        }

        if ($desde = $this->fechaFiltrada('desde')) {
            $query->whereDate('bitacoras.created_at', '>=', $desde);
        }
        if ($hasta = $this->fechaFiltrada('hasta')) {
            $query->whereDate('bitacoras.created_at', '<=', $hasta);
        }

        $search = trim((string) $this->search);
        if ($search !== '') {
            $query->where('bitacoras.descripcion', 'like', '%' . addcslashes($search, '%_\\') . '%');
        }

        return $query;
    }

    protected function fechaFiltrada(string $key): ?string
    {
        $valor = $this->getAppliedFilterWithValue($key);

        if (!is_string($valor) || $valor === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $valor)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    public function builder(): Builder
    {
        return $this->scopedQuery();
    }

    protected function total(): int
    {
        // Sin el with(): para contar no hace falta cargar los sujetos.
        return $this->total ??= $this->scopedQuery()->setEagerLoads([])->count();
    }

    public function filters(): array
    {
        $filtros = [
            MultiSelectDropdownFilter::make('Evento', 'evento')
                ->options(BitacoraEvento::opcionesConEstados()),
        ];

        // Sobre un solo sujeto el tipo es siempre el mismo: el filtro sobra.
        if ($this->esDeUsuario()) {
            $filtros[] = MultiSelectDropdownFilter::make('Sobre', 'tipo')
                ->options(Bitacora::TIPOS);
        }

        // DateFilter y no DateRangeFilter: este necesita flatpickr y el layout
        // no carga los assets del paquete.
        $filtros[] = DateFilter::make('Desde', 'desde');
        $filtros[] = DateFilter::make('Hasta', 'hasta');

        return $filtros;
    }

    public function columns(): array
    {
        $columnas = [
            Column::make('Fecha', 'created_at')
                // Desempate por id: dos hechos del mismo segundo (vender y mudar
                // de sucursal) saltarian de pagina.
                ->sortable(fn(Builder $q, string $dir) => $q
                    ->orderBy('bitacoras.created_at', $dir)
                    ->orderBy('bitacoras.id', $dir))
                ->format(fn($value) => $value?->format('d/m/Y H:i'))
                ->footer(fn($rows) => 'TOTAL'),

            Column::make('Evento', 'evento')
                ->sortable()
                ->format(fn($value) => BitacoraEvento::badge($value))
                ->html()
                ->footer(fn($rows) => $this->total() . ' hecho(s)'),
        ];

        // En el historial del usuario lo que importa es SOBRE QUE actuo.
        if ($this->esDeUsuario()) {
            $columnas[] = Column::make('Sobre qué', 'auditable_id')
                ->format(function ($value, $row) {
                    $sujeto = $row->sujeto();

                    return $sujeto['url']
                        ? '<a href="' . e($sujeto['url']) . '" class="font-semibold text-brand-600 hover:underline dark:text-brand-400">'
                            . e($sujeto['etiqueta']) . '</a>'
                        : '<span class="text-gray-600 dark:text-gray-300">' . e($sujeto['etiqueta']) . '</span>';
                })
                ->html();
        }

        $columnas[] = Column::make('Descripción', 'descripcion')
            ->format(fn($value, $row) => view('livewire.bitacora.descripcion', [
                'descripcion' => $value,
                'cambios' => $row->cambios,
            ]));

        // Y en el de un sujeto, QUIEN lo hizo.
        if (!$this->esDeUsuario()) {
            $columnas[] = Column::make('Usuario', 'user.name')
                ->sortable()
                ->format(fn($value) => $value ?: '—')
                ->collapseOnTablet();
        }

        return $columnas;
    }
}
