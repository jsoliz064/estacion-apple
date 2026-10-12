<?php

namespace App\Livewire\Comision\Modals;

use App\Models\Comision;
use App\Models\ComisionLiquidacion;
use App\Models\Tecnicos;
use App\Models\User;
use App\Services\ComisionService;
use App\Traits\GuardadoIdempotenteTrait;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * La liquidacion (docs/05): se elige la persona y el periodo, se ve lo ganado y
 * sin pagar, se destilda lo que no entra y se confirma. Lo ganado ANTES del
 * periodo y todavia sin pagar se avisa, con un boton para incluirlo: si no,
 * quedaria olvidado para siempre fuera de todos los meses.
 */
class ComisionLiquidarModal extends Component
{
    use GuardadoIdempotenteTrait;

    public bool $openModal = false;

    /** U-5 (vendedor) o T-3 (tecnico). */
    public string $beneficiario = '';
    public string $desde = '';
    public string $hasta = '';
    public string $nota = '';

    /** Los ids marcados para pagar. */
    public array $seleccion = [];

    #[On('openComisionLiquidarModal')]
    public function openModal(?string $beneficiario = null, ?string $desde = null, ?string $hasta = null): void
    {
        abort_unless(Auth::user()?->can('comision.liquidar'), 403);

        $this->resetErrorBag();
        $this->reset(['nota', 'seleccion']);
        $this->beneficiario = (string) $beneficiario;
        $this->desde = $desde ?: now()->startOfMonth()->toDateString();
        $this->hasta = $hasta ?: now()->endOfMonth()->toDateString();

        // Una clave por apertura, nunca en render().
        $this->nuevaClaveIdempotencia();
        $this->marcarTodo();
        $this->openModal = true;
    }

    public function updatedBeneficiario(): void
    {
        $this->marcarTodo();
    }

    public function updatedDesde(): void
    {
        $this->marcarTodo();
    }

    public function updatedHasta(): void
    {
        $this->marcarTodo();
    }

    /** Lleva el inicio del periodo a la comision por pagar mas vieja. */
    public function incluirAnteriores(): void
    {
        $primera = $this->porPagar()->min('ganada_at');

        if ($primera) {
            $this->desde = Carbon::parse($primera)->toDateString();
            $this->marcarTodo();
        }
    }

    private function marcarTodo(): void
    {
        $this->seleccion = $this->items()->pluck('id')->map(fn($id) => (string) $id)->all();
    }

    /** Lo ganado y sin pagar de la persona, con monto. */
    private function porPagar()
    {
        if (!Comision::partirClave($this->beneficiario)[0]) {
            return Comision::query()->whereRaw('1 = 0');
        }

        return Comision::query()->deBeneficiario($this->beneficiario)->porPagar()->where('comisiones.monto', '>', 0);
    }

    /** @return array{0: string, 1: string} */
    private function periodo(): array
    {
        $desde = rescue(fn() => Carbon::parse($this->desde)->toDateString(), now()->startOfMonth()->toDateString(), false);
        $hasta = rescue(fn() => Carbon::parse($this->hasta)->toDateString(), now()->toDateString(), false);

        return $desde <= $hasta ? [$desde, $hasta] : [$hasta, $desde];
    }

    private function items()
    {
        [$desde, $hasta] = $this->periodo();

        return $this->porPagar()
            ->whereDate('comisiones.ganada_at', '>=', $desde)
            ->whereDate('comisiones.ganada_at', '<=', $hasta)
            ->orderBy('ganada_at')
            ->orderBy('id')
            ->get();
    }

    public function guardar(): void
    {
        abort_unless(Auth::user()?->can('comision.liquidar'), 403);

        $this->validate([
            'beneficiario' => ['required', 'regex:/^[UT]-\d+$/'],
            'nota' => 'nullable|string|max:255',
        ], [
            'beneficiario.required' => 'Elige a quién se le paga.',
            'beneficiario.regex' => 'Elige a quién se le paga.',
        ]);

        // Solo las de la lista actual: los ids marcados llegan del navegador.
        $ids = $this->items()->pluck('id')->intersect(array_map('intval', $this->seleccion))->values()->all();

        if ($ids === []) {
            $this->addError('seleccion', 'Marca al menos una comisión para pagar.');

            return;
        }

        if ($ya = $this->yaGuardado(ComisionLiquidacion::class)) {
            $this->yaRegistrada($ya);

            return;
        }

        [$desde, $hasta] = $this->periodo();

        try {
            $liquidacion = DB::transaction(fn() => app(ComisionService::class)->liquidar(
                $this->beneficiario, $ids, $desde, $hasta, $this->nota, Auth::user(), $this->claveIdempotencia,
            ));
        } catch (QueryException $e) {
            if ($this->esClaveDuplicada($e) && $ya = $this->yaGuardado(ComisionLiquidacion::class)) {
                $this->yaRegistrada($ya);

                return;
            }

            throw $e;
        } catch (ValidationException $e) {
            $this->marcarTodo();

            throw $e;
        }

        toastr()->success('Liquidación #' . $liquidacion->id . ' registrada: Bs ' . number_format((float) $liquidacion->total, 2) . '.');
        $this->despues();
    }

    private function yaRegistrada(ComisionLiquidacion $liquidacion): void
    {
        toastr()->info('Esta liquidación ya se había registrado (#' . $liquidacion->id . '). No se pagó dos veces.');
        $this->despues();
    }

    private function despues(): void
    {
        $this->dispatch('comisionesActualizadas');
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->reset(['beneficiario', 'desde', 'hasta', 'nota', 'seleccion']);
    }

    /** Las personas con algo por pagar, para el selector. */
    private function personas(): array
    {
        $usuarios = User::whereIn('id', Comision::porPagar()->where('monto', '>', 0)->whereNotNull('user_id')->select('user_id'))
            ->orderBy('name')->pluck('name', 'id');
        $tecnicos = Tecnicos::whereIn('id', Comision::porPagar()->where('monto', '>', 0)->whereNotNull('tecnico_id')->select('tecnico_id'))
            ->orderBy('nombre')->pluck('nombre', 'id');

        $personas = [];
        foreach ($usuarios as $id => $nombre) {
            $personas['U-' . $id] = $nombre . ' (vendedor)';
        }
        foreach ($tecnicos as $id => $nombre) {
            $personas['T-' . $id] = $nombre . ' (técnico)';
        }

        // La elegida sigue en la lista aunque ya no le quede nada.
        if ($this->beneficiario !== '' && !isset($personas[$this->beneficiario])) {
            [$tipo, $id] = Comision::partirClave($this->beneficiario);
            $nombre = $tipo === 'U' ? User::whereKey($id)->value('name') : Tecnicos::whereKey($id)->value('nombre');
            if ($nombre) {
                $personas[$this->beneficiario] = $nombre;
            }
        }

        return $personas;
    }

    public function render()
    {
        $data = ['personas' => [], 'items' => collect(), 'anteriores' => null, 'total' => 0.0];

        if ($this->openModal) {
            [$desde] = $this->periodo();
            $items = $this->items();
            $marcados = array_map('intval', $this->seleccion);

            $data = [
                'personas' => $this->personas(),
                'items' => $items,
                'anteriores' => $this->porPagar()->whereDate('comisiones.ganada_at', '<', $desde)->toBase()
                    ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(monto), 0) as monto')->first(),
                'total' => round((float) $items->whereIn('id', $marcados)->sum('monto'), 2),
            ];
        }

        return view('livewire.comision.modals.comision-liquidar-modal', $data);
    }
}
