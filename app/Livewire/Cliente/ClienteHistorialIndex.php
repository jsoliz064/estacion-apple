<?php

namespace App\Livewire\Cliente;

use App\Models\Cliente;
use App\Models\Venta;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * La ficha del cliente: sus cifras, sus compras, sus pagos y sus garantias
 * vigentes (docs/04), y sus reservas con seña (docs/03).
 */
class ClienteHistorialIndex extends Component
{
    public $cliente;

    /** compras | pagos | reservas | garantias */
    public string $pestana = 'compras';

    public function mount($cliente_id)
    {
        $this->cliente = Cliente::findOrFail($cliente_id);
    }

    public function verPestana(string $pestana): void
    {
        $this->pestana = in_array($pestana, ['compras', 'pagos', 'reservas', 'garantias'], true) ? $pestana : 'compras';
    }

    public function cobrar(): void
    {
        abort_unless(auth()->user()->can('pago.create'), 403);

        $this->dispatch('openCobroModal', clienteId: $this->cliente->id);
    }

    #[On('pagosActualizados')]
    #[On('reservasActualizadas')]
    public function refrescar(): void
    {
    }

    public function render()
    {
        // Van aqui y no en la tabla a proposito: el pie de la tabla dice "esto es
        // lo que estoy mirando"; estas tarjetas dicen "esto es lo que este cliente
        // lleva". Estrictamente por cliente_id: es lo unico que sigue a la persona.
        // En render() y no en mount(): un cobro las cambia.
        $fila = Venta::where('cliente_id', $this->cliente->id)
            ->toBase()
            ->selectRaw('
                COUNT(*) as ordenes,
                COALESCE(SUM(total), 0) as total_gastado,
                COALESCE(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END), 0) as deuda,
                MAX(created_at) as ultima
            ')
            ->first();

        return view('livewire.cliente.cliente-historial-index', [
            'ordenes' => (int) ($fila->ordenes ?? 0),
            'totalGastado' => round((float) ($fila->total_gastado ?? 0), 2),
            'deuda' => round((float) ($fila->deuda ?? 0), 2),
            'ultimaCompra' => $fila->ultima ?? null,
            'garantias' => $this->pestana === 'garantias' ? $this->cliente->garantiasVigentes() : collect(),
            'reservas' => $this->pestana === 'reservas'
                ? $this->cliente->reservas()->with(['producto.modelo', 'metodo'])->orderByRaw("estado = 'Activa' DESC")->latest('id')->get()
                : collect(),
            'reservasActivas' => $this->cliente->reservas()->activas()->count(),
        ]);
    }
}
