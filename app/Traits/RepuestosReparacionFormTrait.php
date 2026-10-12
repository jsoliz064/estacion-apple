<?php

namespace App\Traits;

use App\Models\Producto;
use App\Models\Repuesto;
use App\Models\Sucursal;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * Las piezas de una reparacion, del lado de los tres modales que la editan
 * (estado del producto, editar reparacion, garantia/trabajo externo). La
 * busqueda vive en su propio modal (RepuestosReparacionModal); antes era una
 * seccion con filtros, sucursal y buscador copiada en los tres.
 *
 * Cada linea nueva lleva su `sucursal_id`: la que se eligio en el selector. Se
 * congela en productos_reparaciones_repuestos y es a donde vuelve la pieza si
 * se quita. Dos piezas pueden salir de sucursales distintas.
 */
trait RepuestosReparacionFormTrait
{
    /** El equipo de la reparacion: propone su modelo y su sucursal en el selector. */
    abstract protected function equipoDeLaReparacion(): ?Producto;

    abstract public function calcularTotalRepuestos();

    public function abrirSelectorRepuestos(): void
    {
        $equipo = $this->equipoDeLaReparacion();

        $this->dispatch(
            'openRepuestosReparacionModal',
            origen: $this->getId(),
            excluidos: collect($this->repuestos)->pluck('repuesto_id')->map(fn($id) => (int) $id)->values()->all(),
            modeloId: $equipo?->producto_modelo_id,
            sucursalId: $equipo?->sucursal_id,
        );
    }

    /**
     * La respuesta del selector. El evento le llega a todos los modales
     * montados en la pantalla: solo lo toma el que lo abrio.
     */
    #[On('repuestosReparacionElegidos')]
    public function agregarRepuestosElegidos(string $origen, int $sucursalId, array $ids): void
    {
        if ($origen !== $this->getId() || !$this->openModal) {
            return;
        }

        $sucursal = Sucursal::activas()->find($sucursalId);

        if (!$sucursal) {
            return;
        }

        $yaCargados = collect($this->repuestos)->pluck('repuesto_id')->map(fn($id) => (int) $id)->all();

        $nuevos = Repuesto::with('modelo:id,nombre')
            ->whereIn('id', array_map('intval', $ids))
            ->whereNotIn('id', $yaCargados)
            ->orderByDesc('nombre')
            ->get();

        foreach ($nuevos as $repuesto) {
            array_unshift($this->repuestos, [
                'id' => null,
                'repuesto_id' => $repuesto->id,
                'nombre' => $repuesto->nombre,
                'modelo' => $repuesto->modelo?->nombre ?? '',
                'fabricante' => $repuesto->fabricante,
                'costo' => (float) $repuesto->costo,
                'cantidad' => 1,
                'subtotal_costo' => (float) $repuesto->costo,
                'sucursal_id' => $sucursal->id,
                'sucursal' => $sucursal->nombre,
            ]);
        }

        $this->calcularTotalRepuestos();
    }

    /**
     * La sucursal de una linea nueva, revalidada: el array de lineas llega del
     * cliente, y una sucursal desactivada no puede ser origen de stock.
     */
    protected function sucursalDeLinea(array $linea): int
    {
        $id = (int) ($linea['sucursal_id'] ?? 0);

        if ($id <= 0 || !Sucursal::activas()->whereKey($id)->exists()) {
            throw ValidationException::withMessages([
                'repuestos' => 'No se sabe de qué sucursal sale «' . ($linea['nombre'] ?? 'la pieza') . '»: quítala y vuelve a agregarla.',
            ]);
        }

        return $id;
    }
}
