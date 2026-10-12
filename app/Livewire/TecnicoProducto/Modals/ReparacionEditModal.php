<?php

namespace App\Livewire\TecnicoProducto\Modals;

use App\Enums\ProductoEstado;
use App\Models\Bitacora;
use App\Models\ProductoModelo;
use App\Models\ProductoReparacion;
use App\Models\ProductoReparacionRepuesto;
use App\Models\Repuesto;
use App\Models\RepuestoCategoria;
use App\Models\Sucursal;
use App\Services\EstadoProductoService;
use Illuminate\Support\Facades\DB;
use App\Models\Producto;
use App\Traits\RepuestosReparacionFormTrait;
use Livewire\Component;
use Livewire\Attributes\On;
use Illuminate\Support\Facades\Auth;

class ReparacionEditModal extends Component
{
    use RepuestosReparacionFormTrait;

    use \App\Traits\PiezasCobradasTrait;

    public $openModal = false;
    public $reparacionModel;
    public $reparacion = [];

    public $repuestos = [];

    public $repuestosOriginales = [];
    public $repuestosEliminados = [];


    public function render()
    {
        return view('livewire.tecnico-producto.modals.reparacion-edit-modal');
    }

    #[On('openReparacionEditModal')]
    public function openModal($id)
    {
        $reparacion = ProductoReparacion::find($id);
        // Sin esto, cerrar con Escape (que no pasa por closeModal) y reabrir
        // duplicaba las lineas.
        $this->repuestos = [];
        $this->repuestosEliminados = [];
        $this->reparacionModel = $reparacion;
        $this->reparacion = $reparacion->toArray();
        $this->reparacion['garantia_tecnico'] = (bool) $this->reparacion['garantia_tecnico'];

        foreach ($reparacion->repuestos as $reparacionRepuesto) {
            $modelo = $reparacionRepuesto->repuesto->modelo;
            $this->repuestos[] = [
                'id' => $reparacionRepuesto->id,
                'repuesto_id' => $reparacionRepuesto->repuesto_id,
                'nombre' => $reparacionRepuesto->repuesto->nombre,
                'modelo' => $modelo ? $modelo->nombre : '',
                'fabricante' => $reparacionRepuesto->repuesto->fabricante,
                'costo' => $reparacionRepuesto->costo,
                'cantidad' => $reparacionRepuesto->cantidad,
                'subtotal_costo' => $reparacionRepuesto->subtotal_costo,
            ];
        }

        $this->repuestosOriginales = $this->repuestos;
        $this->openModal = true;
    }

    protected function equipoDeLaReparacion(): ?Producto
    {
        return $this->reparacionModel?->producto;
    }

    public function updatedRepuestos()
    {
        $this->calcularTotalRepuestos();
    }

    public function calcularTotalRepuestos()
    {
        $costoTotalRepuestosBs = 0;

        foreach ($this->repuestos as $index => $repuesto) {
            $costo = is_numeric($repuesto['costo']) ? (float) $repuesto['costo'] : 0;
            $cantidad = is_numeric($repuesto['cantidad']) ? (int) $repuesto['cantidad'] : 0;
            if ($cantidad <= 0) {
                $this->repuestos[$index]['cantidad'] = 1;
                return;
            }

            $subtotalCostoRepuesto = $costo * $cantidad;
            $this->repuestos[$index]['subtotal_costo'] = $subtotalCostoRepuesto;
            $costoTotalRepuestosBs += $subtotalCostoRepuesto;
        }
        $this->reparacion['costo_repuestos'] = $costoTotalRepuestosBs;
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionCosto()
    {
        $this->calcularTotalReparacion();
    }

    public function updatedReparacionCostoRepuestos()
    {
        $this->calcularTotalReparacion();
    }


    public function calcularTotalReparacion()
    {
        // Todo en Bs: mano de obra del tecnico + repuestos.
        $this->reparacion['costo_total'] = round(
            floatval($this->reparacion['costo'] ?? 0) + floatval($this->reparacion['costo_repuestos'] ?? 0),
            2
        );
    }

    public function eliminarRepuesto($index)
    {
        if (isset($this->repuestos[$index]['id']) && !is_null($this->repuestos[$index]['id'])) {
            $this->repuestosEliminados[] = $this->repuestos[$index]['id'];
        }
        unset($this->repuestos[$index]);
        $this->repuestos = array_values($this->repuestos);
        $this->calcularTotalRepuestos();
    }

    public function update()
    {
        // Fuera de la transaccion: validar no escribe nada, y abrirla para
        // descubrir que falta un campo deja los locks tomados mientras se
        // compone el mensaje de error.
        $this->validate([
            'reparacion.costo' => 'required|numeric|min:0',
            'reparacion.costo_repuestos' => 'required|numeric|min:0',
            'reparacion.costo_total' => 'required|numeric|min:0',
            'reparacion.fecha_entrega' => 'required',
            'reparacion.garantia_tecnico' => 'required',
        ]);

        // Antes de tocar nada: una pieza ya cobrada en una venta no se quita ni
        // cambia de cantidad (la FK de cobro va en RESTRICT).
        $this->exigirPiezasNoCobradas();

        $stock = app(\App\Services\StockService::class);
        $estados = app(EstadoProductoService::class);

        $reparacionGuardada = DB::transaction(function () use ($stock, $estados) {
            $descripcion = "Actualizacion de reparacion del producto";
            $productoReparacion = ProductoReparacion::find($this->reparacion['id']);
            $producto = $productoReparacion->producto;

            // El cambio de estado se DECIDE aqui y se APLICA al final, junto con
            // su fila de historial: antes el producto pasaba a Inventario aqui
            // arriba y cien lineas mas abajo se escribia un historial que decia
            // 'Reparacion'. La traza mentia, y el auditor encontro cuatro
            // productos asi.
            $terminaYSeMuda = false;
            $sucursalAlmacen = null;

            if ($this->reparacion['estado'] == 'Terminado') {
                $this->reparacion['fecha_recogida'] = $this->reparacion['fecha_entrega'] ?: now()->format('Y-m-d');

                $terminaYSeMuda = $producto->estado === ProductoEstado::Reparacion->value
                    && $producto->ultimaReparacion()?->id === $productoReparacion->id;

                if ($terminaYSeMuda) {
                    $sucursalAlmacen = Sucursal::where('nombre', Sucursal::ALMACEN)->first();
                }

                $descripcion = "Reparacion finalizada";
            }

            // 1. Devolver stock de detalles eliminados
            if (!empty($this->repuestosEliminados)) {
                $repuestosABorrar = ProductoReparacionRepuesto::whereIn('id', $this->repuestosEliminados)
                    ->orderBy('repuesto_id')
                    ->get();

                foreach ($repuestosABorrar as $repuestoReparacion) {
                    // A la sucursal CONGELADA en la linea. Leer
                    // $producto->sucursal_id aqui daria el Almacen, porque unas
                    // lineas mas arriba este mismo metodo reasigna el equipo
                    // alli al terminar la reparacion.
                    $stock->ingresar(
                        \App\Enums\ArticuloTipo::Repuesto,
                        $repuestoReparacion->repuesto_id,
                        $repuestoReparacion->sucursal_id,
                        (int) $repuestoReparacion->cantidad,
                    );
                }

                ProductoReparacionRepuesto::destroy($this->repuestosEliminados);
            }

            // 2. Actualizar/Crear detalles y ajustar stock
            $mapRepuestosOriginales = collect($this->repuestosOriginales)->keyBy('id');

            foreach ($this->repuestos as $repuestoRaparacion) {
                $repuesto = Repuesto::find($repuestoRaparacion['repuesto_id']);
                if (!$repuesto)
                    continue;

                if (isset($repuestoRaparacion['id']) && !is_null($repuestoRaparacion['id'])) {
                    // Se relee la fila: de ahi salen la cantidad ANTERIOR y la
                    // sucursal congelada. Antes el "antes" venia de
                    // $this->repuestosOriginales, un array publico de Livewire
                    // sin #[Locked], asi que un payload con 999 ahi devolvia
                    // 998 unidades que nunca existieron.
                    $lineaDB = ProductoReparacionRepuesto::find($repuestoRaparacion['id']);

                    if ($lineaDB) {
                        // ajustarSalida: en una reparacion, subir la cantidad
                        // RETIRA mas stock. La direccion vive en el nombre del
                        // metodo, asi que no se puede copiar invertida del
                        // modulo de compras.
                        $stock->ajustarSalida(
                        \App\Enums\ArticuloTipo::Repuesto,
                            $lineaDB->repuesto_id,
                            $lineaDB->sucursal_id,
                            (int) $lineaDB->cantidad,
                            (int) $repuestoRaparacion['cantidad'],
                        );

                        $lineaDB->update([
                            'costo' => $repuestoRaparacion['costo'],
                            'cantidad' => $repuestoRaparacion['cantidad'],
                            'subtotal_costo' => $repuestoRaparacion['subtotal_costo'],
                        ]);
                    }
                } else {
                    // Detalle nuevo: Crear y descontar stock
                    ProductoReparacionRepuesto::create([
                        'producto_reparacion_id' => $this->reparacionModel->id,
                        'repuesto_id' => $repuestoRaparacion['repuesto_id'],
                        // Congelada: de aqui salio la pieza.
                        'sucursal_id' => $this->sucursalDeLinea($repuestoRaparacion),
                        'costo' => $repuestoRaparacion['costo'],
                        'cantidad' => $repuestoRaparacion['cantidad'],
                        'subtotal_costo' => $repuestoRaparacion['subtotal_costo'],
                    ]);

                    $stock->retirar(
                        \App\Enums\ArticuloTipo::Repuesto,
                        $repuesto->id,
                        $this->sucursalDeLinea($repuestoRaparacion),
                        (int) $repuestoRaparacion['cantidad'],
                    );
                }
            }

            $stock->recalcularTotales();

            $productoReparacion->update(collect($this->reparacion)->except(['id', 'producto_id', 'created_at', 'updated_at', 'tecnico', 'producto', 'repuestos'])->all());

            if ($terminaYSeMuda) {
                // Estado e historial de una pieza, con la precondicion: si otro
                // ya saco el equipo de reparacion, esto no lo pisa.
                $producto = $estados->cambiar(
                    $producto->id,
                    ProductoEstado::Reparacion,
                    ProductoEstado::Inventario,
                    $descripcion,
                    ['producto_reparacion_id' => $productoReparacion->id],
                );

                // Al terminar, el equipo se muda al Almacen.
                if ($sucursalAlmacen) {
                    $producto->update(['sucursal_id' => $sucursalAlmacen->id]);
                }
            } else {
                // No cambia de estado, pero la edicion deja nota. Con el estado
                // REAL del producto, no con 'Reparacion' a palo seco.
                Bitacora::registrar(
                    $producto,
                    $producto->estado,
                    $descripcion,
                    ['producto_reparacion_id' => $productoReparacion->id],
                );
            }

            $producto->refresh();
            $producto->recalcularCosto();
            $productoReparacion->refresh();

            return $productoReparacion;
        });

        $this->dispatch('refreshTecnicoProductoTable');
        $this->dispatch('refreshProductoTable');
        toastr()->success('Reparacion actualizada exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }
}
