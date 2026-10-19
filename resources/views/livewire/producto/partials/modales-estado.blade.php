{{-- El modal de estado y los que abre por evento: reservar (con su cliente),
     cancelar la reserva y las piezas de una reparacion. Montarlo sin ellos deja
     botones mudos: en el detalle de la compra «Reservar» no hacia nada. --}}
@livewire('producto.modals.producto-estado-modal')
@livewire('reserva.modals.reserva-create-modal')
@livewire('reserva.modals.reserva-cancelar-modal')
@livewire('cliente.modals.cliente-selector-modal')
@livewire('cliente.modals.cliente-create-modal')
@livewire('reparacion.modals.repuestos-reparacion-modal')
