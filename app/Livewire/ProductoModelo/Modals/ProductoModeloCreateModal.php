<?php

namespace App\Livewire\ProductoModelo\Modals;

use App\Enums\ProductoAlmacenamiento;
use App\Models\ProductoCategoria;
use App\Models\ProductoModelo;
use App\Models\ProductoModeloAlmacenamiento;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoModeloCreateModal extends Component
{
    public $openModal = false;
    public $productomodelo = [];

    protected $rules = [
        'productomodelo.nombre' => 'required|string|max:255',
        'productomodelo.producto_categoria_id' => 'required',
    ];

    protected $messages = [
        'productomodelo.nombre' => 'Debe ingresar un nombre',
        'productomodelo.producto_categoria_id' => 'Debe ingresar una categoria',
    ];

    #[On('openProductoModeloCreateModal')]
    public function openModal()
    {
        $this->openModal = true;
    }

    public function store()
    {
        try {
            $this->validate();
            DB::transaction(function () {
                $productoModelo = ProductoModelo::create($this->productomodelo);
                $almacenamientos = ProductoAlmacenamiento::cases();
                foreach ($almacenamientos as $almacenamiento) {
                    ProductoModeloAlmacenamiento::create([
                        'almacenamiento' => $almacenamiento->value,
                        'precio' => 0,
                        'costo' => 0,
                        'producto_modelo_id' => $productoModelo->id
                    ]);
                }
            });


            $this->dispatch('refreshProductoModeloTable');
            toastr()->success('Modelo creado exitosamente');
            $this->reset();
        } catch (\Throwable $th) {
            toastr()->error($th->getMessage());
        }
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        $categorias = ProductoCategoria::all();
        return view('livewire.producto-modelo.modals.producto-modelo-create-modal', compact('categorias'));
    }
}
