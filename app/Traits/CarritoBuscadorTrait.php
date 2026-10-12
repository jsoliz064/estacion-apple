<?php

namespace App\Traits;

use App\Enums\LineaTipo;
use App\Services\BuscadorArticulosService;
use Livewire\Attributes\On;

/**
 * El buscador de las pantallas de venta y compra: un solo campo que acepta
 * IMEI, codigo de barras, SKU o nombre (BuscadorArticulosService).
 *
 *  - Escribir muestra la lista de coincidencias.
 *  - Enter (lo manda la pistola USB al terminar de "teclear" el codigo, y la
 *    camara al leerlo) agrega directo si hay UNA coincidencia exacta por
 *    codigo; si no, deja la lista. El codigo llega como argumento: $busqueda
 *    va con debounce y todavia no lo tiene.
 *  - El catalogo con filtros (ArticuloSelectorModal) devuelve varios a la vez.
 *
 * El componente declara que se puede buscar, de que sucursal, y como se agrega
 * una linea. Sin sucursal no se busca: no hay stock del que hablar.
 */
trait CarritoBuscadorTrait
{
    public string $busqueda = '';
    public array $resultados = [];

    /** @return LineaTipo[] */
    abstract protected function tiposBuscables(): array;

    abstract public function sucursalDelDocumento(): ?int;

    /** Claves "Tipo:id" ya cargadas, para no ofrecerlas otra vez. */
    abstract protected function clavesEnCarrito(): array;

    abstract protected function agregarLinea(string $tipo, int $id): void;

    /** Venta: solo equipos vendibles y articulos con stock. Compra: no exige. */
    protected function esVenta(): bool
    {
        return true;
    }

    public function motivoSinSucursal(): string
    {
        return 'Elige primero la sucursal.';
    }

    public function updatedBusqueda($valor): void
    {
        if ($this->sucursalDelDocumento() === null || mb_strlen(trim((string) $valor)) < 2) {
            $this->resultados = [];

            return;
        }

        $enCarrito = $this->clavesEnCarrito();

        $this->resultados = app(BuscadorArticulosService::class)
            ->buscar((string) $valor, $this->tiposBuscables(), $this->sucursalDelDocumento(), $this->esVenta())
            ->reject(fn($r) => in_array($r['tipo'] . ':' . $r['id'], $enCarrito, true))
            ->values()
            ->all();
    }

    /** Enter en el buscador: lo que manda el lector de codigos. */
    public function agregarPorCodigo(?string $codigo = null): void
    {
        if ($codigo !== null) {
            $this->busqueda = trim($codigo);
        }

        if ($this->sucursalDelDocumento() === null) {
            toastr()->warning($this->motivoSinSucursal());

            return;
        }

        $fila = app(BuscadorArticulosService::class)
            ->porCodigo($this->busqueda, $this->tiposBuscables(), $this->sucursalDelDocumento(), $this->esVenta());

        if ($fila) {
            $this->seleccionarResultado($fila['tipo'], $fila['id']);

            return;
        }

        $this->updatedBusqueda($this->busqueda);

        if ($this->resultados === []) {
            toastr()->warning('No se encontró nada con «' . trim($this->busqueda) . '».');
        }
    }

    public function seleccionarResultado(string $tipo, int $id): void
    {
        if ($this->sucursalDelDocumento() === null) {
            toastr()->warning($this->motivoSinSucursal());

            return;
        }

        if (in_array($tipo . ':' . $id, $this->clavesEnCarrito(), true)) {
            toastr()->info('Ya está en la lista.');
        } else {
            $this->agregarLinea(LineaTipo::from($tipo)->value, $id);
        }

        $this->busqueda = '';
        $this->resultados = [];
    }

    public function abrirSelectorArticulos(): void
    {
        if ($this->sucursalDelDocumento() === null) {
            toastr()->warning($this->motivoSinSucursal());

            return;
        }

        $this->dispatch(
            'openArticuloSelectorModal',
            excluidos: $this->clavesEnCarrito(),
            sucursalId: $this->sucursalDelDocumento(),
            soloConStock: $this->esVenta(),
        );
    }

    #[On('articulosSeleccionados')]
    public function agregarArticulosSeleccionados(array $items): void
    {
        foreach ($items as $item) {
            $this->seleccionarResultado($item['tipo'], (int) $item['id']);
        }
    }
}
