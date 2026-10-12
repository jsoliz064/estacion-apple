<?php

namespace App\Traits;

use App\Enums\ArticuloTipo;
use App\Models\Bitacora;
use App\Models\Sucursal;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

/**
 * El stock por sucursal dentro del formulario de crear y editar un repuesto o
 * un accesorio. El componente declara articuloTipo().
 *
 * El formulario trabaja con `$stockSucursales`, un array [sucursal_id => cantidad]
 * con una entrada por sucursal elegida. Al guardar se traduce a movimientos de
 * StockService, nunca a una escritura directa del total cacheado.
 */
trait ArticuloStockSucursalTrait
{
    /** [sucursal_id => cantidad]. Una fila por sucursal cargada. */
    public array $stockSucursales = [];

    /** [sucursal_id => minimo], mismas claves que $stockSucursales. 0 = sin minimo. */
    public array $minimosSucursales = [];

    /** La sucursal que el usuario esta por agregar. */
    public $sucursalNueva = '';

    abstract protected function articuloTipo(): ArticuloTipo;

    /** Las sucursales activas que todavia no estan en el formulario. */
    public function sucursalesDisponibles()
    {
        // Solo activas: agregar stock a una sucursal desactivada no tiene
        // sentido. sucursalesPorId() sigue con todas para pintar las filas viejas.
        return Sucursal::activas()->orderBy('nombre')
            ->get()
            ->reject(fn($s) => array_key_exists($s->id, $this->stockSucursales));
    }

    /** Catalogo para pintar los nombres de las filas ya cargadas. */
    public function sucursalesPorId()
    {
        return Sucursal::orderBy('nombre')->get()->keyBy('id');
    }

    /**
     * El total. Es la SUMA y no un campo editable: la subtabla es la unica
     * verdad, y un total escrito a mano no tendria filas que lo respalden.
     */
    public function totalStock(): int
    {
        return array_sum(array_map('intval', $this->stockSucursales));
    }

    public function agregarSucursalStock(): void
    {
        if ($this->sucursalNueva === '' || $this->sucursalNueva === null) {
            return;
        }

        $id = (int) $this->sucursalNueva;

        if (!array_key_exists($id, $this->stockSucursales)) {
            $this->stockSucursales[$id] = 0;
            $this->minimosSucursales[$id] = 0;
        }

        $this->sucursalNueva = '';
    }

    public function quitarSucursalStock($sucursalId): void
    {
        unset($this->stockSucursales[(int) $sucursalId], $this->minimosSucursales[(int) $sucursalId]);
    }

    /** Carga el reparto actual de un articulo ya guardado. */
    protected function cargarStockSucursales(int $articuloId): void
    {
        $stock = app(StockService::class);
        $this->stockSucursales = $stock->porSucursal($this->articuloTipo(), $articuloId);
        $minimos = $stock->minimosPorSucursal($this->articuloTipo(), $articuloId);
        $this->minimosSucursales = collect($this->stockSucursales)->map(fn($c, $id) => $minimos[$id] ?? 0)->all();
    }

    /**
     * Lleva el stock de cada sucursal al valor del formulario, por diferencia,
     * con ajustarEntrada(): el mismo camino que compras, ventas y reparaciones.
     * Una sucursal que se quito del formulario se lleva a cero.
     *
     * Debe llamarse DENTRO de una transaccion del llamador.
     */
    protected function guardarStockSucursales(int $articuloId): void
    {
        $tipo = $this->articuloTipo();
        $stock = app(StockService::class);
        $actual = $stock->porSucursal($tipo, $articuloId);

        // Las del formulario mas las que estaban y se quitaron (van a cero).
        // Ordenadas para no bloquear en InnoDB.
        $ids = array_unique(array_merge(array_keys($actual), array_keys($this->stockSucursales)));
        sort($ids);

        $movidas = [];

        foreach ($ids as $sucursalId) {
            $antes = $actual[$sucursalId] ?? 0;
            $ahora = (int) ($this->stockSucursales[$sucursalId] ?? 0);

            $stock->ajustarEntrada($tipo, $articuloId, (int) $sucursalId, $antes, $ahora);

            if ($antes !== $ahora) {
                $movidas[(int) $sucursalId] = [$antes, $ahora];
            }
        }

        $stock->recalcularTotales();

        // El minimo, por la misma puerta. Una sucursal quitada vuelve a 0.
        $minimosAntes = $stock->minimosPorSucursal($tipo, $articuloId);
        $minimosMovidos = [];

        foreach ($ids as $sucursalId) {
            $antes = $minimosAntes[$sucursalId] ?? 0;
            $ahora = (int) ($this->minimosSucursales[$sucursalId] ?? 0);

            if ($antes !== $ahora) {
                $stock->fijarMinimo($tipo, $articuloId, (int) $sucursalId, $ahora);
                $minimosMovidos[(int) $sucursalId] = [$antes, $ahora];
            }
        }

        $this->registrarAjusteDeStock($articuloId, $movidas, esAlta: $actual === [], minimos: $minimosMovidos);
    }

    /**
     * El ajuste a mano, en la bitacora del articulo. Tiene que escribirse aqui:
     * StockService mueve el stock con SQL crudo y ningun observer se entera.
     * Una fila por guardado, con las sucursales que cambiaron como pares
     * [antes, despues] por nombre (la forma de un diff del observer).
     */
    protected function registrarAjusteDeStock(int $articuloId, array $movidas, bool $esAlta, array $minimos = []): void
    {
        if ($movidas === [] && $minimos === []) {
            return;
        }

        $tipo = $this->articuloTipo();
        $nombres = Sucursal::whereIn('id', array_keys($movidas + $minimos))->pluck('nombre', 'id');

        $cambios = [];
        foreach ($movidas as $sucursalId => $par) {
            $cambios[$nombres[$sucursalId] ?? "Sucursal #{$sucursalId}"] = $par;
        }
        foreach ($minimos as $sucursalId => $par) {
            $cambios['Mínimo ' . ($nombres[$sucursalId] ?? "Sucursal #{$sucursalId}")] = $par;
        }

        $neto = array_sum(array_map(fn($par) => $par[1] - $par[0], $movidas));

        $descripcion = match (true) {
            $movidas === [] => 'Stock mínimo ajustado desde la ficha',
            $esAlta => 'Stock inicial cargado desde la ficha',
            default => 'Stock ajustado a mano desde la ficha (neto ' . ($neto >= 0 ? '+' : '') . $neto . ')',
        };

        $sucursales = array_keys($movidas + $minimos);

        Bitacora::registrar(
            ($tipo->modelo())::findOrFail($articuloId),
            'stock',
            $descripcion,
            // El enlace a la sucursal solo cuando es una: con varias, el detalle
            // esta en `cambios`.
            count($sucursales) === 1 ? ['sucursal_id' => $sucursales[0]] : [],
            $cambios,
        );
    }

    /** Reglas del bloque de stock, para mezclar con las del formulario. */
    protected function reglasDeStock(): array
    {
        return [
            'stockSucursales' => 'array',
            'stockSucursales.*' => 'required|integer|min:0',
            'minimosSucursales' => 'array',
            'minimosSucursales.*' => 'nullable|integer|min:0',
        ];
    }
}
