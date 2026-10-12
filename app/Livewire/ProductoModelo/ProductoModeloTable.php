<?php

namespace App\Livewire\ProductoModelo;

use App\Enums\ProductoAlmacenamiento;
use App\Enums\ProductoEstado;
use App\Models\ProductoModelo;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;
use Livewire\Attributes\On;


class ProductoModeloTable extends DataTableComponent
{
    protected $model = ProductoModelo::class;

    public function configure(): void
    {
        $this->setPrimaryKey('id');
    }

    public function columns(): array
    {
        $columns = [
            Column::make("Id", "id")
                ->sortable(),
            Column::make("Nombre", "nombre")
                ->sortable()
                ->searchable(),
            Column::make("Categoria", "productoCategoria.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Marca", "productoCategoria.productoMarca.nombre")
                ->sortable()
                ->searchable(),
            Column::make("Total Productos", "id")
                ->sortable()
                ->format(function ($value, $row, Column $column) {
                    return count($row->productos->whereNotIn('estado', ProductoEstado::vendidos())->whereNull('dado_de_baja_at'));
                })->searchable(),
        ];

        $almacenamientos = ProductoAlmacenamiento::cases();
        foreach ($almacenamientos as $almacenamiento) {
            array_push(
                $columns,
                Column::make($almacenamiento->value)
                    ->label(function ($row) use ($almacenamiento) {
                        return count($row->productos->whereNotIn('estado', ProductoEstado::vendidos())->whereNull('dado_de_baja_at')->where('almacenamiento', $almacenamiento->value));
                    }),
            );
        }

        array_push(
            $columns,
            Column::make('Acciones', 'id')
                ->format(function ($value, $row, Column $column) {
                    return view('livewire.producto-modelo.actions-buttons', [
                        'row' => $row
                    ]);
                }),
        );

        return $columns;
    }

    #[On('refreshProductoModeloTable')]
    public function refreshProductoModeloTable()
    {
        $this->builder();
    }

    public function openProductoModeloEditModal($id)
    {
        $this->dispatch('openProductoModeloEditModal', $id);
    }

    public function openProductoModeloAlmacenamientoModal($id)
    {
        $this->dispatch('openProductoModeloAlmacenamientoModal', $id);
    }

    public function openProductoModeloDestroyModal($id)
    {
        $this->dispatch('openProductoModeloDestroyModal', $id);
    }
}
