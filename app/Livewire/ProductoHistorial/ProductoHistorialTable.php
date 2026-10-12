<?php

namespace App\Livewire\ProductoHistorial;

use App\Enums\BitacoraEvento;
use App\Models\Bitacora;
use App\Models\Producto;
use App\Models\User;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Rappasoft\LaravelLivewireTables\Views\Filters\MultiSelectDropdownFilter;
use Illuminate\Database\Eloquent\Builder;

/**
 * El historial de un telefono, leido de la bitacora.
 *
 * Antes leia `productos_historiales`, que solo sabia de cambios de estado. Ahora
 * ademas sale lo que nunca se registro: el precio, el IMEI, el costo o la
 * sucursal cambiados a mano, cada uno con su antes y su despues.
 */
class ProductoHistorialTable extends DataTableComponent
{
    protected $model = Bitacora::class;

    public $producto;

    /** Memo por request de las opciones del filtro de usuario. */
    protected ?array $usuarioOptions = null;

    public function mount($producto_id)
    {
        $this->producto = Producto::find($producto_id);
    }

    public function configure(): void
    {
        // Nombre propio: el default del paquete es 'table' para TODAS las tablas,
        // y esta pantalla monta dos a la vez (query string, id del DOM y el evento
        // Alpine de filas colapsadas se pisarían entre sí).
        $this->setTableName('historial');

        $this->setPrimaryKey('id')
            ->setDefaultSort('id', 'desc')
            ->setEmptyMessage('Este producto no tiene movimientos registrados.');

        // El paquete solo pide al SELECT los campos de las columnas declaradas.
        // cambios es lo que pinta x-bitacora-cambios debajo de la descripcion.
        $this->setAdditionalSelects([
            'bitacoras.cambios',
        ]);
    }

    public function columns(): array
    {
        return [
            Column::make('ID', 'id')
                ->sortable(),

            Column::make('Evento', 'evento')
                ->sortable()
                ->format(fn($value) => BitacoraEvento::badge($value))
                ->html(),

            Column::make('Descripción', 'descripcion')
                ->format(fn($value, $row) => view('livewire.bitacora.descripcion', [
                    'descripcion' => $value,
                    'cambios' => $row->cambios,
                ]))
                ->searchable(),

            Column::make('Fecha', 'created_at')
                ->sortable()
                ->format(fn($value) => $value?->format('d/m/Y H:i')),

            Column::make('Usuario', 'user.name')
                ->sortable()
                ->searchable()
                ->format(fn($value) => $value ?: '—'),

            Column::make('Acciones', 'id')
                ->format(function ($value, $row) {
                    return view('livewire.producto-historial.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        ];
    }

    public function filters(): array
    {
        return [
            MultiSelectDropdownFilter::make('Evento')
                ->options(BitacoraEvento::opcionesConEstados())
                ->filter(function (Builder $builder, array $values) {
                    if (empty($values)) {
                        return;
                    }
                    $builder->whereIn('bitacoras.evento', $values);
                }),
            MultiSelectDropdownFilter::make('Usuario')
                ->options($this->getUsuarioOptions())
                ->filter(function (Builder $builder, array $values) {
                    if (empty($values)) {
                        return;
                    }
                    $builder->whereIn('bitacoras.user_id', $values);
                }),
        ];
    }

    /**
     * Solo los usuarios que registraron movimientos de ESTE producto.
     * Subconsulta para resolverlo en una sola query.
     */
    protected function getUsuarioOptions(): array
    {
        return $this->usuarioOptions ??= User::query()
            ->whereIn('id', $this->producto->bitacoras()
                ->select('user_id')
                ->whereNotNull('user_id'))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->toArray();
    }

    public function builder(): Builder
    {
        // Sin orderBy aquí: applySorting() acumula orderBy en vez de reemplazarlos,
        // así que un orden fijo en el builder dejaría el del usuario como criterio
        // secundario. El orden por defecto va en setDefaultSort().
        //
        // Por id y no por created_at: las filas copiadas conservan su fecha
        // original y las nuevas llevan la de hoy, pero dos hechos del mismo
        // segundo (vender y mudar de sucursal) solo los ordena bien el id.
        return Bitacora::query()
            ->where('bitacoras.auditable_type', $this->producto->getMorphClass())
            ->where('bitacoras.auditable_id', $this->producto->id);
    }

    public function openProductoHistorialModal($id)
    {
        $this->dispatch('openProductoHistorialModal', $id);
    }
}
