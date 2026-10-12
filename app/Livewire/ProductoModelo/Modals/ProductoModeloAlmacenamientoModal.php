<?php

namespace App\Livewire\ProductoModelo\Modals;

use App\Models\ProductoModelo;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoModeloAlmacenamientoModal extends Component
{
    public $openModal = false;
    public $productomodelo;
    public $precios = [];
    public $precios_clientes = [];
    public $costos = [];

    public function render()
    {
        return view('livewire.producto-modelo.modals.producto-modelo-almacenamiento-modal');
    }

    #[On('openProductoModeloAlmacenamientoModal')]
    public function openModal($id)
    {
        $this->productomodelo = ProductoModelo::with('almacenamientos')->find($id);

        foreach ($this->productomodelo->almacenamientos as $almacenamiento) {
            $this->precios[$almacenamiento->id] = $almacenamiento->precio;
            $this->precios_clientes[$almacenamiento->id] = $almacenamiento->precio_cliente;
            $this->costos[$almacenamiento->id] = $almacenamiento->costo;
        }

        $this->openModal = true;
    }

    public function updateAlmacenamientos()
    {
        foreach ($this->productomodelo->almacenamientos as $almacenamiento) {
            $almacenamiento->update([
                'precio' => $this->precios[$almacenamiento->id],
                'precio_cliente' => $this->precios_clientes[$almacenamiento->id],
                'costo' => $this->costos[$almacenamiento->id],
            ]);
        }
        toastr()->success('Almacenamientos actualizados correctamente');
    }

    public function closeModal()
    {
        $this->reset(['openModal', 'productomodelo', 'precios']);
        $this->resetErrorBag();
    }
}
