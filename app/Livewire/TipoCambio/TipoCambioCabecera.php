<?php

namespace App\Livewire\TipoCambio;

use App\Models\TipoCambio;
use App\Services\TipoCambioService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * El tipo de cambio del dia, en la cabecera de todas las pantallas: verlo es
 * libre y actualizarlo (a mano, cada dia) pide tipo-cambio.actualizar. En
 * ambar si todavia no se cargo el de hoy.
 */
class TipoCambioCabecera extends Component
{
    public bool $openModal = false;

    public $valor = '';

    public function abrir(): void
    {
        $this->resetErrorBag();
        $this->valor = TipoCambio::actual();
        $this->openModal = true;
    }

    public function actualizar(): void
    {
        abort_unless(Auth::user()?->can('tipo-cambio.actualizar'), 403);

        $this->validate(
            ['valor' => 'required|numeric|gt:0|decimal:0,4'],
            ['valor.gt' => 'El tipo de cambio tiene que ser mayor a cero.', 'valor.decimal' => 'Hasta cuatro decimales.'],
        );

        DB::transaction(fn() => app(TipoCambioService::class)->actualizar((float) $this->valor, Auth::user()));

        toastr()->success('Tipo de cambio actualizado.');
        $this->openModal = false;
        // Los que muestran Bs en vivo (el inicio, el catalogo) se repintan.
        $this->dispatch('tipoCambioActualizado');
    }

    public function render()
    {
        return view('livewire.tipo-cambio.tipo-cambio-cabecera', [
            'vigente' => TipoCambio::vigente(),
            'alDia' => TipoCambio::actualizadoHoy(),
            'historial' => $this->openModal ? TipoCambio::with('user:id,name')->latest('id')->limit(10)->get() : collect(),
        ]);
    }
}
