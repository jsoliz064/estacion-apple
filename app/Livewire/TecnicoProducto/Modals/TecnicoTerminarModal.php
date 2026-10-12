<?php

namespace App\Livewire\TecnicoProducto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\ProductoReparacion;
use App\Models\Sucursal;
use App\Models\Tecnicos;
use App\Services\EstadoProductoService;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TecnicoTerminarModal extends Component
{
    public $openModal = false;
    public $tecnico;
    public $reparaciones;
    public $selectedReparaciones = [];

    public function render()
    {
        return view('livewire.tecnico-producto.modals.tecnico-terminar-modal');
    }

    #[On('openTecnicoTerminarModal')]
    public function openModal($tecnico_id)
    {
        $tecnico = Tecnicos::find($tecnico_id);
        $this->tecnico = $tecnico;
        $this->reparaciones = $tecnico->reparaciones()->where('estado', 'Pendiente')->get();
        $this->openModal = true;
    }

    public function toggleReparacion($id)
    {
        if (in_array($id, $this->selectedReparaciones)) {
            // Si ya está, lo quitamos
            $this->selectedReparaciones = array_diff($this->selectedReparaciones, [$id]);
        } else {
            // Si no está, lo agregamos
            $this->selectedReparaciones[] = $id;
        }
    }

    public function toggleSeleccionarTodo()
    {
        // Si ya todos están seleccionados → limpiar lista
        if (count($this->selectedReparaciones) === count($this->reparaciones)) {
            $this->selectedReparaciones = [];
        }
        // Si no → seleccionar todos los IDs
        else {
            $this->selectedReparaciones = $this->reparaciones->pluck('id')->toArray();
        }
    }

    public function terminarReparaciones()
    {
        if (sizeof($this->selectedReparaciones) == 0) {
            toastr()->warning('No hay reparaciones seleccionadas para terminar.');
            return;
        }

        $estados = app(EstadoProductoService::class);

        try {
            $reparacionesPendientes = ProductoReparacion::whereIn('id', $this->selectedReparaciones)->get();
            $sucursalAlmacen = Sucursal::where('nombre', Sucursal::ALMACEN)->first();

            // UNA transaccion para el lote entero, no una por reparacion.
            // Antes el DB::transaction estaba DENTRO del foreach: fallar en la
            // quinta de diez dejaba cuatro terminadas, seis sin tocar, y el
            // usuario viendo "ocurrio un error" sin forma de saber cuales.
            DB::transaction(function () use ($reparacionesPendientes, $sucursalAlmacen, $estados) {
                foreach ($reparacionesPendientes as $reparacion) {
                    $reparacion->update([
                        'estado' => 'Terminado',
                        'fecha_recogida' => now()->format('Y-m-d')
                    ]);

                    $producto = $reparacion->producto;
                    $esLaUltima = $producto->ultimaReparacion()?->id === $reparacion->id;
                    $descripcion = "Reparacion finalizada con el tecnico {$reparacion->tecnico->nombre}";

                    if ($producto->estado === ProductoEstado::Reparacion->value && $esLaUltima) {
                        // El estado y su fila de historial, de una pieza. Antes
                        // el producto pasaba a Inventario pero el historial se
                        // escribia con 'Reparacion' a mano: la traza mentia, y
                        // el auditor encontro cuatro productos asi.
                        $producto = $estados->cambiar(
                            $producto->id,
                            ProductoEstado::Reparacion,
                            ProductoEstado::Inventario,
                            $descripcion,
                            ['producto_reparacion_id' => $reparacion->id],
                        );

                        // Al terminar, el equipo se muda al Almacen.
                        if ($sucursalAlmacen) {
                            $producto->update(['sucursal_id' => $sucursalAlmacen->id]);
                        }

                        $producto->refresh();
                        $producto->recalcularCosto();
                    } else {
                        // La reparacion se cierra pero el producto se queda
                        // donde esta (no era la ultima, o ya lo movieron). Queda
                        // la nota, con el estado REAL y no con uno inventado.
                        Bitacora::registrar(
                            $producto,
                            $producto->estado,
                            $descripcion,
                            ['producto_reparacion_id' => $reparacion->id],
                        );
                    }
                }
            });

            toastr()->success('Reparaciones terminadas exitosamente.');

            $this->dispatch('refreshTecnicoProductoTable');
            $this->dispatch('refreshTecnicoProductoIndex');

            $this->closeModal();
        } catch (ValidationException $e) {
            // El mensaje de la precondicion tiene que llegar: dice QUE producto
            // ya no estaba en reparacion, que es lo que hay que destildar.
            toastr()->error(implode(' ', $e->validator->errors()->all()));
        } catch (\Throwable $e) {
            // El catch de antes descartaba $e sin registrarlo: cualquier causa
            // real se perdia y solo quedaba el mensaje generico.
            Log::error('Fallo al terminar reparaciones', [
                'reparaciones' => $this->selectedReparaciones,
                'excepcion' => $e,
            ]);

            toastr()->error('Ocurrió un error al procesar las reparaciones. No se aplicó ninguna.');
        }
    }

    public function closeModal()
    {
        $this->reset(['openModal', 'tecnico', 'reparaciones', 'selectedReparaciones']);
    }
}
