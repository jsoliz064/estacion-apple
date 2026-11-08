<?php

namespace App\Traits;

use App\Enums\Moneda;
use App\Models\TipoCambio;
use Illuminate\Validation\Rule;

/**
 * El lado de la pantalla de MontosEnMonedaTrait: costo y precio en USD (por
 * defecto) o en Bs con un tipo de cambio para los dos. Lo usan el alta y la
 * edicion del equipo y el formulario de repuestos y accesorios, con la vista
 * livewire.partials.montos-moneda.
 *
 * En Bs, el costo y el precio en USD se calculan aqui mientras se escribe: son
 * los que validan las reglas del componente (el precio no menor que el costo,
 * en USD) y los que se ven. El modelo los vuelve a calcular al guardar.
 */
trait MontosEnMonedaFormTrait
{
    /**
     * Donde vive cada campo en el componente (propiedad o ruta con punto):
     * ['moneda', 'costo', 'precio', 'costo_bs', 'precio_bs', 'tc'].
     */
    abstract protected function camposMoneda(): array;

    protected function campoMoneda(string $campo): mixed
    {
        return data_get($this, $this->camposMoneda()[$campo]);
    }

    protected function fijarCampoMoneda(string $campo, mixed $valor): void
    {
        data_set($this, $this->camposMoneda()[$campo], $valor);
    }

    public function enBs(): bool
    {
        return $this->campoMoneda('moneda') === Moneda::alterna()->value;
    }

    /**
     * Llamar desde el updated() del componente con la propiedad que cambio.
     * Pasar a Bs propone los Bs de lo que ya decia en USD, al TC del dia.
     */
    protected function montoEnMonedaCambio(string $propiedad): void
    {
        $campos = $this->camposMoneda();

        if ($propiedad === $campos['moneda'] && $this->enBs()) {
            $tc = (float) ($this->campoMoneda('tc') ?: 0) ?: TipoCambio::actual();
            $this->fijarCampoMoneda('tc', $tc);

            foreach (['costo', 'precio'] as $monto) {
                if (!(float) ($this->campoMoneda($monto . '_bs') ?: 0)) {
                    $this->fijarCampoMoneda($monto . '_bs', round((float) ($this->campoMoneda($monto) ?: 0) * $tc, 2));
                }
            }
        }

        if (in_array($propiedad, [$campos['moneda'], $campos['costo_bs'], $campos['precio_bs'], $campos['tc']], true)) {
            $this->sincronizarMontosEnMoneda();
        }
    }

    /** En Bs, el USD de cada monto = Bs / TC. En USD no hay nada que calcular. */
    protected function sincronizarMontosEnMoneda(): void
    {
        if (!$this->enBs()) {
            return;
        }

        $tc = (float) ($this->campoMoneda('tc') ?: 0);

        foreach (['costo', 'precio'] as $monto) {
            $bs = $this->campoMoneda($monto . '_bs');

            if ($tc > 0 && is_numeric($bs)) {
                $this->fijarCampoMoneda($monto, round((float) $bs / $tc, 2));
            }
        }
    }

    /** Las reglas de la moneda; las del costo y el precio en USD son del componente. */
    protected function reglasMontosEnMoneda(): array
    {
        $c = $this->camposMoneda();
        $enBs = $this->enBs();

        return [
            $c['moneda'] => ['required', Rule::in(Moneda::values())],
            $c['costo_bs'] => $enBs ? 'required|numeric|min:0|decimal:0,2' : 'nullable',
            $c['precio_bs'] => $enBs ? 'required|numeric|min:0|decimal:0,2' : 'nullable',
            $c['tc'] => $enBs ? 'required|numeric|gt:0|decimal:0,4' : 'nullable',
        ];
    }

    protected function atributosMontosEnMoneda(): array
    {
        $c = $this->camposMoneda();

        return [
            $c['costo_bs'] => 'costo en Bs',
            $c['precio_bs'] => 'precio en Bs',
            $c['tc'] => 'tipo de cambio',
        ];
    }

    /** Las columnas de moneda para guardar (el modelo pone NULL las de Bs en USD). */
    protected function datosMontosEnMoneda(): array
    {
        $enBs = $this->enBs();

        return [
            'moneda' => $enBs ? Moneda::alterna()->value : Moneda::base()->value,
            'costo_moneda_monto' => $enBs ? $this->campoMoneda('costo_bs') : null,
            'precio_moneda_monto' => $enBs ? $this->campoMoneda('precio_bs') : null,
            'tipo_cambio' => $enBs ? $this->campoMoneda('tc') : null,
        ];
    }
}
