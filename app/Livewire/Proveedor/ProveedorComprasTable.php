<?php

namespace App\Livewire\Proveedor;

use App\Models\Compra;
use App\Models\Proveedor;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use App\Support\Dinero;

/** Las compras de un proveedor, con su estado y lo que se le debe de cada una. */
class ProveedorComprasTable extends DataTableComponent
{
    protected $model = Compra::class;

    public $proveedor;

    public function mount($proveedor_id): void
    {
        $this->proveedor = Proveedor::findOrFail($proveedor_id);
    }

    public function configure(): void
    {
        $this->setTableName('compras_proveedor');
        $this->setPrimaryKey('id')
            ->setDefaultSort('fecha', 'desc')
            ->setSearchDisabled()
            ->setEmptyMessage('Todavía no hay compras a este proveedor.');
        // finalizada_at: estado() la lee y un label() no la selecciona.
        $this->setAdditionalSelects(['compras.id', 'compras.finalizada_at']);
    }

    #[On('pagosProveedorActualizados')]
    #[On('reclamosActualizados')]
    public function refrescar(): void
    {
    }

    public function builder(): Builder
    {
        return Compra::query()->where('compras.proveedor_id', $this->proveedor->id)->conConteoReclamos();
    }

    public function columns(): array
    {
        $usd = fn($v) => Dinero::usd((float) $v);

        return [
            Column::make('Fecha', 'fecha')->sortable()->format(fn($v) => Carbon::parse($v)->format('d/m/Y')),
            Column::make('Compra', 'id')
                ->sortable()
                ->format(fn($v) => '<a href="' . route('compras.detalle', $v) . '" class="text-brand-600 hover:underline dark:text-brand-400">#' . (int) $v . '</a>')
                ->html(),
            Column::make('Estado')->label(fn($row) => $row->estado()->badge())->html(),
            Column::make('Total', 'total')->sortable()->format($usd),
            Column::make('Pagado', 'pagado')->sortable()->format($usd)->collapseOnTablet(),
            Column::make('Saldo', 'saldo')
                ->sortable()
                ->format(fn($v) => (float) $v > 0
                    ? '<span class="font-semibold text-amber-700 dark:text-amber-300">' . Dinero::usd((float) $v) . '</span>'
                    : '<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Pagada</span>')
                ->html(),
        ];
    }
}
