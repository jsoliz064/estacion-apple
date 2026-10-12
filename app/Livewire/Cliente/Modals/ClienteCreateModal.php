<?php

namespace App\Livewire\Cliente\Modals;

use App\Models\Cliente;
use Illuminate\Database\QueryException;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Dar de alta un cliente.
 *
 * Lo declaran DOS clases de pantalla: el indice de clientes y las cinco de venta,
 * que lo abren para crear la ficha al vuelo sin salir de la venta. De ahi el
 * evento `clienteCreado`: ver su comentario en store().
 */
class ClienteCreateModal extends Component
{
    public $openModal = false;
    public $cliente = [];

    /** El texto con el que se abrio desde un buscador, para no teclearlo dos veces. */
    public string $nombreSugerido = '';

    protected function rules(): array
    {
        return [
            'cliente.nombre' => 'required|string|max:255',
            // Unico en la base, y aqui tambien para dar el error en el campo y no
            // un 1062 crudo. El indice es quien lo garantiza de verdad: una regla
            // `unique` valida con un SELECT previo y dos pestanas a la vez la
            // pasan las dos. De ahi el catch de store().
            'cliente.ci' => ['nullable', 'string', 'max:20', Rule::unique('clientes', 'ci')],
            'cliente.telefono' => 'nullable|string|max:30',
            'cliente.correo' => 'nullable|email|max:255',
        ];
    }

    protected function messages(): array
    {
        // Con el sufijo de la regla. Sin el, Laravel ignora la clave y muestra su
        // mensaje generico en ingles: es un fallo que varios modales del repo
        // arrastran y que aqui no se repite.
        return [
            'cliente.nombre.required' => 'Debe ingresar el nombre del cliente',
            'cliente.ci.unique' => 'Ya existe un cliente con ese CI',
            'cliente.correo.email' => 'El correo no tiene un formato valido',
        ];
    }

    /**
     * @param  string  $nombre  lo que se habia tecleado en el buscador que abrio
     *                          este modal, para arrancar con el nombre puesto.
     */
    #[On('openClienteCreateModal')]
    public function openModal(string $nombre = '')
    {
        $this->reset();
        $this->nombreSugerido = trim($nombre);
        $this->cliente['nombre'] = $this->nombreSugerido;
        $this->openModal = true;
    }

    public function store()
    {
        $this->validate();

        try {
            $cliente = Cliente::create($this->cliente);
        } catch (QueryException $e) {
            // El indice unico del CI, que es la garantia real. Llega aqui cuando
            // dos pestanas guardan el mismo CI a la vez y las dos pasaron la
            // regla de validacion.
            if (($e->errorInfo[1] ?? null) === 1062 && str_contains($e->getMessage(), 'clientes_ci_unico')) {
                $this->addError('cliente.ci', 'Ya existe un cliente con ese CI.');

                return;
            }

            throw $e;
        }

        $this->dispatch('refreshClienteTable');

        // Evento PROPIO y no colgado de refreshClienteTable, aunque el repo haga
        // eso en RepuestoCreateModal: ese evento lo despachan ademas los modales
        // de editar, eliminar, transferir y tipo de cambio masivo SIN argumento,
        // asi que un oyente que espere el id reventaria en cuanto coincidieran en
        // pantalla. Aqui las pantallas de venta escuchan solo `clienteCreado`.
        $this->dispatch('clienteCreado', id: $cliente->id);

        toastr()->success('Cliente creado exitosamente');
        $this->reset();
    }

    public function closeModal()
    {
        $this->reset();
    }

    public function render()
    {
        return view('livewire.cliente.modals.cliente-create-modal');
    }
}
