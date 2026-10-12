<?php

namespace App\Livewire\Producto\Modals;

use App\Models\Producto;
use App\Services\RepuestosDeReparacionService;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Elegir cuales de los repuestos montados en las reparaciones de un telefono
 * se cobran ademas como venta de repuesto, al vender el equipo.
 *
 * A diferencia de RepuestoSelectorModal, que devuelve solo ids, este devuelve
 * las lineas completas: el precio se edita aqui dentro, asi que el padre no
 * podria reconstruirlo a partir de un id. Es una desviacion consciente del
 * contrato de la casa.
 */
class ProductoRepuestosVentaModal extends Component
{
    public bool $openModal = false;

    public $productoId = null;
    public $productoImei = '';
    public $productoDescripcion = '';

    /** Ids de linea de reparacion marcados. */
    public array $seleccionados = [];

    /** id de linea de reparacion => precio editado. */
    public array $precios = [];

    #[On('openProductoRepuestosVentaModal')]
    public function openModal($productoId, array $yaElegidos = []): void
    {
        $this->resetEstado();

        $producto = Producto::find($productoId);
        if (!$producto) {
            return;
        }

        $this->productoId = $producto->id;
        $this->productoImei = $producto->imei;
        $this->productoDescripcion = $producto->descripcion;

        // Se restaura lo que el vendedor ya habia marcado y el precio que le
        // habia puesto: reabrir el modal no debe perder su trabajo.
        foreach ($yaElegidos as $linea) {
            $id = (string) ($linea['producto_reparacion_repuesto_id'] ?? '');
            if ($id !== '') {
                $this->seleccionados[] = $id;
                $this->precios[$id] = $linea['precio'] ?? 0;
            }
        }

        $this->openModal = true;
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->resetEstado();
    }

    /** Siempre reset dirigido, nunca $this->reset() a secas. */
    protected function resetEstado(): void
    {
        $this->reset(['productoId', 'productoImei', 'productoDescripcion', 'seleccionados', 'precios']);
    }

    public function confirmar(RepuestosDeReparacionService $servicio): void
    {
        $elegibles = $servicio->elegibles($this->productoId)->keyBy('id');
        $lineas = [];

        foreach ($this->seleccionados as $id) {
            $origen = $elegibles->get((int) $id);
            if (!$origen) {
                continue;
            }

            $precio = round((float) ($this->precios[$id] ?? 0), 2);

            if ($precio <= 0) {
                $this->addError('precios.' . $id, 'Indica un precio mayor a cero.');
                return;
            }

            $lineas[] = [
                'producto_reparacion_repuesto_id' => $origen->id,
                'repuesto_id' => $origen->repuesto_id,
                'nombre' => $origen->repuesto?->nombre ?? 'Repuesto',
                'cantidad' => $origen->cantidad,
                'precio' => $precio,
                'subtotal' => round($precio * $origen->cantidad, 2),
            ];
        }

        $this->dispatch(
            'repuestosDeReparacionSeleccionados',
            productoId: $this->productoId,
            lineas: $lineas,
        );

        $this->closeModal();
    }

    public function render(RepuestosDeReparacionService $servicio)
    {
        // El catalogo va en render() y solo con el modal abierto: con el modal
        // cerrado el componente no hace ninguna consulta.
        $elegibles = collect();
        $total = 0.0;

        if ($this->openModal && $this->productoId) {
            $elegibles = $servicio->elegibles($this->productoId);

            foreach ($elegibles as $linea) {
                $clave = (string) $linea->id;

                // Precio sembrado con el del catalogo, editable antes de
                // confirmar.
                $this->precios[$clave] ??= $linea->repuesto?->precio ?? 0;

                if (in_array($clave, array_map('strval', $this->seleccionados), true)) {
                    $total += round((float) $this->precios[$clave], 2) * $linea->cantidad;
                }
            }
        }

        return view('livewire.producto.modals.producto-repuestos-venta-modal', [
            'elegibles' => $elegibles,
            'totalSeleccionado' => round($total, 2),
        ]);
    }
}
