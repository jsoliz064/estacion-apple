<?php

namespace App\Livewire\ProductoHistorial\Modals;

use App\Models\Bitacora;
use Livewire\Component;
use Livewire\Attributes\On;

class ProductoHistorialModal extends Component
{
    public $openModal = false;
    public $productoHistorial;

    public function render()
    {
        // El load va AQUI y no en openModal(): Livewire guarda el modelo por su
        // ID y lo vuelve a buscar en cada peticion, sin relaciones. Cargarlas
        // al abrir funcionaria solo en el primer render.
        $this->productoHistorial?->load([
            'reparacion.tecnico',
            'reparacion.repuestos.repuesto.categoria',
            'reparacion.repuestos.repuesto.modelo',
            'venta.user',
            'venta.fichaCliente',
            'venta.detalles.producto.modelo',
            'venta.detalles.repuesto',
            'venta.detalles.accesorio',
            'venta.detalles.reparacionRepuesto',
            // Para las filas de repuesto cobrado, que muestran lo suyo y no la
            // venta del telefono.
            'repuesto',
        ]);

        return view('livewire.producto-historial.modals.producto-historial-modal');
    }

    #[On('openProductoHistorialModal')]
    public function openModal($id)
    {
        // Se sigue llamando $productoHistorial: el blade y x-repuesto-cobrado-
        // detalle lo leen con ese nombre, y la bitacora conserva los mismos
        // enlaces (reparacion, venta, repuesto) con los mismos nombres de
        // columna. Solo `estado` paso a llamarse `evento`.
        $this->productoHistorial = Bitacora::find($id);
        $this->openModal = true;
    }

    public function closeModal()
    {
        $this->reset();
    }
}
