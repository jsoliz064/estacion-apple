<?php

namespace App\Livewire\User;

use App\Models\Bitacora;
use App\Models\Comision;
use App\Models\User;
use Livewire\Component;

/**
 * Todo lo que hizo un usuario, sobre cualquier cosa.
 *
 * No existia forma de contestarlo: cada tabla guardaba su user_id a su manera
 * (o no lo guardaba -- repuestos y reparaciones no tienen), y las cabeceras de
 * compras y ventas de repuestos lo REESCRIBEN al editar, asi que su "usuario"
 * es quien la toco por ultima vez. La bitacora registra a cada uno en cada hecho.
 */
class UserHistorialIndex extends Component
{
    public $usuario;

    /** Cifras FIJAS de la cabecera: no se mueven con los filtros de la tabla. */
    public int $acciones = 0;
    public int $accionesHoy = 0;
    public ?string $ultimaAccion = null;

    /** comisiones | bitacora */
    public string $pestana = 'comisiones';

    public function mount($user_id)
    {
        abort_unless(auth()->user()?->can('user.historial'), 403);

        $this->usuario = User::findOrFail($user_id);

        // Mismo reparto que el historial del cliente: el pie de la tabla dice
        // "esto es lo que estoy mirando"; estas tarjetas, "esto es lo que lleva".
        $fila = Bitacora::where('user_id', $this->usuario->id)
            ->toBase()
            ->selectRaw('COUNT(*) as acciones, MAX(created_at) as ultima,
                         SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as hoy')
            ->first();

        $this->acciones = (int) ($fila->acciones ?? 0);
        $this->accionesHoy = (int) ($fila->hoy ?? 0);
        $this->ultimaAccion = $fila->ultima ?? null;
    }

    public function verPestana(string $pestana): void
    {
        $this->pestana = in_array($pestana, ['comisiones', 'bitacora'], true) ? $pestana : 'comisiones';
    }

    public function render()
    {
        return view('livewire.user.user-historial-index', [
            // Lo que vendio y, si es tecnico, lo que reparo (docs/01).
            'cifras' => Comision::cifras(Comision::query()->deUsuario($this->usuario)),
        ]);
    }
}
