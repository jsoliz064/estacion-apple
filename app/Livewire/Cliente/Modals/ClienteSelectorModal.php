<?php

namespace App\Livewire\Cliente\Modals;

use App\Models\Cliente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal para elegir un cliente, con buscador y paginacion.
 *
 * Hermano de RepuestoSelectorModal y ProductoSelectorModal, con una diferencia
 * de fondo: la seleccion es UNICA y se cierra al elegir. Una venta tiene un
 * cliente, asi que no hay casillas ni boton de "agregar N seleccionados": se
 * pulsa la fila y listo.
 *
 * Contrato: despacha SOLO el id (evento `clienteSeleccionado`), igual que sus dos
 * hermanos. Quien escucha decide que hacer con el -- ClienteBuscadorTrait escribe
 * el enlace y el nombre congelado en la cabecera del documento.
 */
class ClienteSelectorModal extends Component
{
    public bool $openModal = false;

    public string $search = '';

    /** El que ya esta elegido, para marcarlo en la lista. */
    public ?int $elegido = null;

    public int $pagina = 1;
    public int $porPagina = 10;

    #[On('openClienteSelectorModal')]
    public function openModal(?int $elegido = null): void
    {
        $this->resetEstado();
        $this->elegido = $elegido;
        $this->openModal = true;
    }

    /**
     * Resetear la pagina al buscar no es cosmetico: filtrando desde la pagina 3,
     * sin esto la lista queda vacia y parece que no hay resultados.
     */
    public function updatedSearch(): void
    {
        $this->pagina = 1;
    }

    public function irAPagina(int $p): void
    {
        $this->pagina = max(1, $p);
    }

    public function limpiarFiltros(): void
    {
        $this->reset(['search', 'pagina']);
    }

    /** Se elige y se cierra: no hay multi-seleccion que confirmar. */
    public function elegir(int $id): void
    {
        $this->dispatch('clienteSeleccionado', id: $id);
        $this->closeModal();
    }

    public function closeModal(): void
    {
        $this->openModal = false;
        $this->resetEstado();
    }

    /** Siempre reset dirigido, nunca $this->reset() a secas. */
    protected function resetEstado(): void
    {
        $this->reset(['search', 'elegido', 'pagina']);
    }

    protected function clientesQuery(): LengthAwarePaginator
    {
        return Cliente::query()
            ->when($this->search !== '', function ($q) {
                $like = '%' . addcslashes($this->search, '%_\\') . '%';

                $q->where(fn($sub) => $sub->where('nombre', 'like', $like)
                    ->orWhere('ci', 'like', $like)
                    ->orWhere('telefono', 'like', $like)
                    ->orWhere('correo', 'like', $like));
            })
            // Cuantas ordenes tiene, como subconsulta: ayuda a distinguir dos
            // fichas con el mismo nombre, que es el caso que el CI no cubre
            // cuando ninguna de las dos lo tiene.
            ->withCount('ventas as ordenes')
            ->orderBy('nombre')
            // Paginacion manual: WithPagination registra `page` en el query string
            // con history:true y reescribiria la URL de la venta de fondo.
            ->paginate($this->porPagina, ['*'], 'page', $this->pagina);
    }

    public function render()
    {
        // La consulta va en render() y con el guard del modal cerrado: asi cerrado
        // cuesta 0 consultas y nada viaja en el payload de Livewire.
        return view('livewire.cliente.modals.cliente-selector-modal', [
            'clientes' => $this->openModal ? $this->clientesQuery() : null,
        ]);
    }
}
